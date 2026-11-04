<?php

declare(strict_types=1);

namespace App\Controller;

use App\Application\CaesuraNotation;
use App\Application\ExamAnalyzer;
use App\Domain\Grade\Caesura;
use App\Form\AnalyzeExamType;
use App\Form\Model\AnalyzeExamInput;
use App\Infrastructure\Excel\ExcelResultImporter;
use App\Infrastructure\Excel\InvalidWorkbookException;
use App\Infrastructure\Web\ExamAnalysisViewFactory;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Web front end for the same analysis as the `app:analyze-exam` command.
 *
 * Uploaded workbooks are analysed straight from PHP's temporary upload file and never stored.
 */
final class ExamAnalysisController extends AbstractController
{
    public function __construct(
        private readonly ExcelResultImporter $importer,
        private readonly ExamAnalyzer $analyzer,
        private readonly CaesuraNotation $caesuraNotation,
        private readonly ExamAnalysisViewFactory $viewFactory,
        #[Autowire('%kernel.project_dir%/Assignment.xlsx')]
        private readonly string $sampleWorkbook,
    ) {
    }

    #[Route('/', name: 'app_analysis_upload', methods: ['GET', 'POST'])]
    public function upload(Request $request): Response
    {
        $defaultCaesura = $this->caesuraNotation->parseDefault();
        $input = AnalyzeExamInput::withCaesura($defaultCaesura);
        $form = $this->createForm(AnalyzeExamType::class, $input);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid() && null !== $input->workbook) {
            try {
                return $this->renderAnalysis($input->workbook->getPathname(), $input->workbook->getClientOriginalName(), $input->toCaesura());
            } catch (InvalidWorkbookException $e) {
                $form->get('workbook')->addError(new FormError($e->getMessage()));
            }
        }

        return $this->render('analysis/upload.html.twig', [
            'form' => $form,
            'default_caesura' => $defaultCaesura,
            'sample_available' => is_file($this->sampleWorkbook),
        ]);
    }

    #[Route('/sample', name: 'app_analysis_sample', methods: ['GET'])]
    public function sample(): Response
    {
        if (!is_file($this->sampleWorkbook)) {
            throw $this->createNotFoundException('The sample workbook is not available.');
        }

        return $this->renderAnalysis($this->sampleWorkbook, basename($this->sampleWorkbook), $this->caesuraNotation->parseDefault());
    }

    /**
     * @throws InvalidWorkbookException
     */
    private function renderAnalysis(string $path, string $fileName, Caesura $caesura): Response
    {
        $analysis = $this->analyzer->analyze($this->importer->import($path), $caesura);

        return $this->render('analysis/result.html.twig', [
            'view' => $this->viewFactory->create($analysis, $fileName),
        ]);
    }
}
