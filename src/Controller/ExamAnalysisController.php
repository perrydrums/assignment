<?php

declare(strict_types=1);

namespace App\Controller;

use App\Application\AnalysisRunner;
use App\Application\AnalysisSettings;
use App\Application\CaesuraNotation;
use App\Domain\Exam\Exam;
use App\Domain\Exam\Question;
use App\Domain\Exam\Student;
use App\Domain\Grade\InvalidCaesuraException;
use App\Form\AnalysisSettingsType;
use App\Form\AnalyzeExamType;
use App\Form\Model\AnalysisSettingsInput;
use App\Form\Model\AnalyzeExamInput;
use App\Infrastructure\Excel\ExcelResultExporter;
use App\Infrastructure\Excel\ExcelResultImporter;
use App\Infrastructure\Excel\InvalidWorkbookException;
use App\Infrastructure\Web\ErrorTranslator;
use App\Infrastructure\Web\ExamAnalysisViewFactory;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Web front end for teachers. Uploaded results are analysed in memory and never stored;
 * adjustments are sent back with the page.
 */
#[Route('/{_locale}', requirements: ['_locale' => 'en|nl'])]
final class ExamAnalysisController extends AbstractController
{
    public function __construct(
        private readonly ExcelResultImporter $importer,
        private readonly AnalysisRunner $runner,
        private readonly CaesuraNotation $caesuraNotation,
        private readonly ExamAnalysisViewFactory $viewFactory,
        private readonly ExcelResultExporter $exporter,
        private readonly ErrorTranslator $errors,
        #[Autowire('%kernel.project_dir%/Assignment.xlsx')]
        private readonly string $sampleWorkbook,
    ) {
    }

    #[Route('', name: 'app_analysis_upload', methods: ['GET', 'POST'])]
    public function upload(Request $request): Response
    {
        $defaultCaesura = $this->caesuraNotation->parseDefault();
        $input = AnalyzeExamInput::withCaesura($defaultCaesura);
        $form = $this->createForm(AnalyzeExamType::class, $input);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid() && null !== $input->workbook) {
            try {
                $exam = $this->importer->import($input->workbook->getPathname());

                return $this->renderAnalysis($exam, $input->workbook->getClientOriginalName(), AnalysisSettings::initial($input->toCaesura(), \count($exam->questions)));
            } catch (InvalidWorkbookException $e) {
                $form->get('workbook')->addError(new FormError($this->errors->translate($e)));
            } catch (InvalidCaesuraException $e) {
                $form->get('caesura')->addError(new FormError($this->errors->translate($e)));
            }
        }

        return $this->render('analysis/upload.html.twig', [
            'form' => $form,
            'default_caesura' => $defaultCaesura,
            'sample_available' => is_file($this->sampleWorkbook),
        ]);
    }

    #[Route('/example', name: 'app_analysis_sample', methods: ['GET'])]
    public function sample(): Response
    {
        if (!is_file($this->sampleWorkbook)) {
            throw $this->createNotFoundException($this->errors->translate(new InvalidWorkbookException('The example workbook is not available.', 'workbook.example_missing')));
        }

        $exam = $this->importer->import($this->sampleWorkbook);

        return $this->renderAnalysis($exam, basename($this->sampleWorkbook), AnalysisSettings::initial($this->caesuraNotation->parseDefault(), \count($exam->questions)));
    }

    #[Route('/results', name: 'app_analysis_results', methods: ['POST'])]
    public function results(Request $request): Response
    {
        $form = $this->createForm(AnalysisSettingsType::class);
        $form->handleRequest($request);

        return $this->renderSubmitted($form, false);
    }

    #[Route('/results/export', name: 'app_analysis_export', methods: ['POST'])]
    public function export(Request $request): Response
    {
        $form = $this->createForm(AnalysisSettingsType::class);
        $form->handleRequest($request);

        return $this->renderSubmitted($form, true);
    }

    #[Route('/help', name: 'app_analysis_help', methods: ['GET'])]
    public function help(): Response
    {
        return $this->render('analysis/help.html.twig');
    }

    private function renderSubmitted(FormInterface $form, bool $export): Response
    {
        $input = $form->getData();
        if (!$input instanceof AnalysisSettingsInput || !$input->exam instanceof Exam) {
            $this->addFlash('error', 'flash.exam_data_invalid');

            return $this->redirectToRoute('app_analysis_upload');
        }

        if (!$form->isValid()) {
            $settings = $input->fallbackSettings();
            if (null === $settings) {
                $this->addFlash('error', 'flash.exam_data_invalid');

                return $this->redirectToRoute('app_analysis_upload');
            }

            return $this->renderAnalysis($input->exam, $input->fileName, $settings, $form);
        }

        try {
            $settings = $input->toSettings();
        } catch (InvalidCaesuraException $e) {
            $form->addError(new FormError($this->errors->translate($e)));

            return $this->renderAnalysis($input->exam, $input->fileName, $input->fallbackSettings() ?? AnalysisSettings::initial($this->caesuraNotation->parseDefault(), \count($input->exam->questions)), $form);
        }

        if ($export) {
            return $this->exportResponse($input->exam, $input->fileName, $settings);
        }

        return $this->renderAnalysis($input->exam, $input->fileName, $settings, $form);
    }

    private function renderAnalysis(Exam $exam, string $fileName, AnalysisSettings $settings, ?FormInterface $form = null): Response
    {
        $analyzed = $exam->adjusted($settings->adjustments);
        $outcome = $this->runner->run($exam, $settings);
        $view = $this->viewFactory->create($outcome, $fileName, $settings, $analyzed);
        $form ??= $this->createForm(AnalysisSettingsType::class, AnalysisSettingsInput::from($exam, $fileName, $settings));

        return $this->render('analysis/result.html.twig', [
            'view' => $view,
            'settings' => $settings,
            'settings_form' => $form,
            'exam_payload' => [
                'questions' => array_map(static fn (Question $question): array => ['label' => $question->label, 'maxScore' => $question->maxScore], $exam->questions),
                'students' => array_map(static fn (Student $student): array => ['id' => $student->id, 'scores' => $student->scores], $exam->students),
            ],
            'question_details' => $view->questionDetails(),
            'original_indexes' => implode(',', $view->originalIndexes),
        ]);
    }

    private function exportResponse(Exam $exam, string $fileName, AnalysisSettings $settings): Response
    {
        $analyzed = $exam->adjusted($settings->adjustments);
        $view = $this->viewFactory->create($this->runner->run($exam, $settings), $fileName, $settings, $analyzed);
        $download = preg_replace('/\.(xlsx|xls)$/i', '', $fileName).'-analysis.xlsx';

        return new Response($this->exporter->toXlsx($view), Response::HTTP_OK, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => HeaderUtils::makeDisposition(HeaderUtils::DISPOSITION_ATTACHMENT, $download),
        ]);
    }
}
