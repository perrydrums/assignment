<?php

declare(strict_types=1);

namespace App\Tests\Integration\Controller;

use App\Tests\Support\TemporaryWorkbooks;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\BrowserKit\Cookie;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\DomCrawler\Field\ChoiceFormField;
use Symfony\Component\DomCrawler\Field\FileFormField;

final class ExamAnalysisControllerTest extends WebTestCase
{
    private const string SUPPLIED_WORKBOOK = __DIR__.'/../../../Assignment.xlsx';

    private KernelBrowser $client;
    private TemporaryWorkbooks $workbooks;

    protected function setUp(): void
    {
        $this->client = self::createClient();
        $this->workbooks = new TemporaryWorkbooks();
    }

    protected function tearDown(): void
    {
        $this->workbooks->removeAll();
        parent::tearDown();
    }

    public function testShowsTheUploadFormWithTheAssignmentCaesura(): void
    {
        $crawler = $this->client->request('GET', '/en');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('input[type="file"][name="analyze_exam[workbook]"]');
        self::assertSame(['20', '70', '100'], $this->values($crawler, '.caesura__points input[name$="[percentage]"]'));
        self::assertSame(['1', '5.5', '10'], $this->values($crawler, '.caesura__points input[name$="[grade]"]'));
        self::assertInputValueSame('analyze_exam[caesura][passPercentage]', '70');
    }

    public function testAnalysesAnUploadedWorkbook(): void
    {
        $crawler = $this->submitAnalysis($this->workbooks->create(self::exam()));

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.kpi--pass-rate', '2 of 3 students passed');
        self::assertSame(3, $crawler->filter('#panel-students tbody tr[data-data-table-target="row"]')->count());
        self::assertSame(2, $crawler->filter('#panel-questions tbody tr[data-data-table-target="row"]')->count());

        $s2 = $crawler->filter('#panel-students tbody tr[data-search="s2"]');
        self::assertStringContainsString('70.0%', $s2->text());
        self::assertSame('5.5', $s2->filter('.grade-pill')->text());
        self::assertStringContainsString('Passed', $s2->filter('.status')->text());
        self::assertSelectorNotExists('#panel-console');
        self::assertStringNotContainsString('bin/console', $this->client->getResponse()->getContent() ?: '');
    }

    public function testAppliesACustomCaesura(): void
    {
        $crawler = $this->submitAnalysis($this->workbooks->create(self::exam()), [
            'analyze_exam[caesura][points][0][percentage]' => '0',
            'analyze_exam[caesura][points][0][grade]' => '1',
            'analyze_exam[caesura][points][1][percentage]' => '60',
            'analyze_exam[caesura][points][1][grade]' => '6',
            'analyze_exam[caesura][passPercentage]' => '60',
        ]);

        self::assertResponseIsSuccessful();
        // S3 scores 25%: 1 + 25 / 60 * 5 = 3.08 with the custom caesura (1.5 with the default one).
        self::assertSame('3.1', $crawler->filter('#panel-students tbody tr[data-search="s3"] .grade-pill')->text());
    }

    public function testAcceptsCaesuraPointsInAnyOrder(): void
    {
        $this->submitAnalysis($this->workbooks->create(self::exam()), [
            'analyze_exam[caesura][points][0][percentage]' => '100',
            'analyze_exam[caesura][points][0][grade]' => '10',
            'analyze_exam[caesura][points][2][percentage]' => '20',
            'analyze_exam[caesura][points][2][grade]' => '1',
        ]);

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.kpi--pass-rate', '2 of 3 students passed');
    }

    public function testRequiresAWorkbook(): void
    {
        $this->submitAnalysis(null);

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('ul.field-errors', 'Choose an Excel workbook');
    }

    public function testRejectsFilesThatAreNotExcelWorkbooks(): void
    {
        $this->submitAnalysis($this->workbooks->createTextFile("ID,Q1\n", '.csv'));

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('ul.field-errors', 'Only Excel workbooks');
    }

    public function testShowsWhereAWorkbookIsInvalid(): void
    {
        $this->submitAnalysis($this->workbooks->create([['ID', 'Q1'], ['Max question score:', 1], ['S1', 'x']]));

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('ul.field-errors', 'Cell B3: the score of student "S1" on "Q1" must be a number, got text "x".');
    }

    public function testShowsDomainErrorsForAnInvalidCaesura(): void
    {
        $this->submitAnalysis($this->workbooks->create(self::exam()), [
            'analyze_exam[caesura][points][0][grade]' => '7',
        ]);

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('ul.field-errors', 'Grades must not go down. 5.5 follows 7.');
    }

    public function testRejectsCrossSiteSubmissions(): void
    {
        $crawler = $this->client->request('GET', '/en');
        $form = $crawler->selectButton('Analyse results')->form();
        $this->uploadField($form['analyze_exam[workbook]'])->upload($this->workbooks->create(self::exam()));

        // What a browser sends when another site submits the form.
        $this->client->submit($form, [], [
            'HTTP_SEC_FETCH_SITE' => 'cross-site',
            'HTTP_ORIGIN' => 'https://attacker.example',
            'HTTP_REFERER' => 'https://attacker.example/',
        ]);

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('ul.field-errors', 'The CSRF token is invalid');
    }

    public function testAnalysesTheSampleWorkbook(): void
    {
        if (!is_file(self::SUPPLIED_WORKBOOK)) {
            self::markTestSkipped('Assignment.xlsx is not in the repository (student data); place it in the project root to run this test.');
        }

        $crawler = $this->client->request('GET', '/en/example');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.kpi--pass-rate', '68 of 452 students passed');
        self::assertSame(452, $crawler->filter('#panel-students tbody tr[data-data-table-target="row"]')->count());
        self::assertSame(48, $crawler->filter('.item-map__dot')->count(), 'Question 24 has no rit-value and is not plotted.');
    }

    public function testRedirectsToThePreferredLanguage(): void
    {
        $this->client->request('GET', '/', [], [], ['HTTP_ACCEPT_LANGUAGE' => 'nl']);

        self::assertResponseRedirects('/nl');
    }

    public function testRendersTheDutchUploadPageAndADutchImporterError(): void
    {
        $crawler = $this->client->request('GET', '/nl');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Zie hoe je toets ging.');
        self::assertStringNotContainsString('bin/console', $this->client->getResponse()->getContent() ?: '');

        $form = $crawler->selectButton('Resultaten analyseren')->form();
        $this->uploadField($form['analyze_exam[workbook]'])->upload($this->workbooks->create([
            ['ID', 'Q1'],
            ['Max question score:', 1],
            ['S1', 'geen score'],
        ]));
        $this->client->submit($form);

        self::assertSelectorTextContains('ul.field-errors', 'Cel B3: de score van student "S1" op "Q1" moet een getal zijn, maar is de tekst "geen score".');
    }

    public function testRecalculatesAfterAQuestionIsRemoved(): void
    {
        $crawler = $this->submitAnalysis($this->workbooks->create([
            ['ID', 'Q1', 'Q2'],
            ['Max question score:', 10, 10],
            ['A', 10, 0],
            ['B', 10, 10],
        ]));
        $form = $crawler->selectButton('Update analysis')->form();
        $adjustment = $form['analysis_settings[adjustments][1]'];
        self::assertInstanceOf(ChoiceFormField::class, $adjustment);
        $adjustment->select('remove');
        $crawler = $this->client->submit($form);

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.adjustment-banner', '1 question was left out.');
        self::assertSelectorTextContains('.adjustment-banner', '1 student now passes who did not before.');
        self::assertSame('10', $crawler->filter('.meta-chips li')->eq(2)->filter('strong')->text());
    }

    public function testPrintsASummaryAndCollapsesTheQuestionAdjustments(): void
    {
        $crawler = $this->submitAnalysis($this->workbooks->create(self::exam()));

        self::assertSelectorTextContains('.result-actions', 'Print summary');
        self::assertSelectorNotExists('.result-actions input[type="checkbox"]');
        self::assertSelectorExists('section.results.no-print');

        $settings = $crawler->filter('dialog[aria-labelledby="settings-title"]');
        self::assertSame(
            ['Grading scale (caesura)', 'Review guidelines', 'Questions'],
            $settings->filter('.drawer__body > .drawer__section .drawer__section-title')->each(static fn (Crawler $title): string => $title->text()),
        );
        self::assertNull($settings->filter('details.collapsible')->attr('open'));
        self::assertSame(2, $settings->filter('details.collapsible select.select__input')->count());
    }

    public function testExportsAWorkbookWithDutchHeaders(): void
    {
        $crawler = $this->client->request('GET', '/nl');
        $form = $crawler->selectButton('Resultaten analyseren')->form();
        $this->uploadField($form['analyze_exam[workbook]'])->upload($this->workbooks->create(self::exam()));
        $crawler = $this->client->submit($form);
        $this->client->submit($crawler->selectButton('Excel downloaden')->form());

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

        $path = tempnam(sys_get_temp_dir(), 'analysis');
        self::assertNotFalse($path);
        file_put_contents($path, $this->client->getInternalResponse()->getContent());
        $spreadsheet = IOFactory::load($path);
        unlink($path);

        self::assertSame(['Samenvatting', 'Cijfers', 'Vragen'], $spreadsheet->getSheetNames());
        self::assertSame('Bestand', $spreadsheet->getSheet(0)->getCell('A1')->getValue());
        self::assertSame('Student', $spreadsheet->getSheet(1)->getCell('A1')->getValue());
        self::assertEquals(10, $spreadsheet->getSheet(1)->getCell('C2')->getValue());
    }

    public function testRendersTheThemeChosenInTheSwitcher(): void
    {
        $crawler = $this->client->request('GET', '/en');

        self::assertSelectorExists('html[data-theme="default"]');
        self::assertSame('Certify360', $crawler->filter('.brand-logo')->attr('alt'));
        self::assertSame(9, $crawler->filter('#theme-menu .theme-menu__option')->count());
        self::assertSame('Default', $crawler->filter('.theme-menu__option[aria-pressed="true"]')->text());

        $this->client->getCookieJar()->set(new Cookie('theme', 'foi'));
        $crawler = $this->client->request('GET', '/nl');

        self::assertSelectorExists('html[data-theme="foi"]');
        self::assertSame('FOI', $crawler->filter('.brand-logo')->attr('alt'));
        self::assertStringContainsString('images/brands/foi', $crawler->filter('.brand-logo')->attr('src') ?? '');
        self::assertSame('FOI', $crawler->filter('.theme-menu__option[aria-pressed="true"]')->text());

        $this->client->getCookieJar()->set(new Cookie('theme', '"><script>'));
        $this->client->request('GET', '/en');

        self::assertSelectorExists('html[data-theme="default"]');
    }

    public function testExplainsTheAnalysisWithoutDeveloperContent(): void
    {
        $this->client->request('GET', '/en/help');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'How the analysis works');
        self::assertStringNotContainsString('bin/console', $this->client->getResponse()->getContent() ?: '');

        $this->client->request('GET', '/nl/help');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Hoe de analyse werkt');
    }

    /**
     * @param array<string, string> $values
     */
    private function submitAnalysis(?string $workbook, array $values = []): Crawler
    {
        $crawler = $this->client->request('GET', '/en');
        $form = $crawler->selectButton('Analyse results')->form();
        if (null !== $workbook) {
            $this->uploadField($form['analyze_exam[workbook]'])->upload($workbook);
        }

        return $this->client->submit($form, $values);
    }

    private function uploadField(mixed $field): FileFormField
    {
        self::assertInstanceOf(FileFormField::class, $field);

        return $field;
    }

    /**
     * @return list<string|null>
     */
    private function values(Crawler $crawler, string $selector): array
    {
        return $crawler->filter($selector)->each(static fn (Crawler $input): ?string => $input->attr('value'));
    }

    /**
     * Max score 10. Totals: S1 = 10 (100%), S2 = 7 (70%, passes exactly), S3 = 2.5 (25%).
     *
     * @return array<int, array<int, mixed>>
     */
    private static function exam(): array
    {
        return [
            ['ID', 'Q1', 'Q2'],
            ['Max question score:', 4, 6],
            ['S1', 4, 6],
            ['S2', 3, 4],
            ['S3', 1, 1.5],
        ];
    }
}
