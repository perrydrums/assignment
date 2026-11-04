<?php

declare(strict_types=1);

namespace App\Tests\Integration\Controller;

use App\Tests\Support\TemporaryWorkbooks;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DomCrawler\Crawler;
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
        $crawler = $this->client->request('GET', '/');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('input[type="file"][name="analyze_exam[workbook]"]');
        self::assertSame(['20', '70', '100'], $this->values($crawler, '.caesura__points input[name$="[percentage]"]'));
        self::assertSame(['1', '5.5', '10'], $this->values($crawler, '.caesura__points input[name$="[grade]"]'));
        self::assertInputValueSame('analyze_exam[passPercentage]', '70');
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
        self::assertSame('Passed', $s2->filter('.status')->text());

        self::assertSelectorTextContains('#panel-console .terminal__output', 'Exam analysis');
        self::assertSelectorTextContains('.command-line__code', 'php bin/console app:analyze-exam');
    }

    public function testAppliesACustomCaesura(): void
    {
        $crawler = $this->submitAnalysis($this->workbooks->create(self::exam()), [
            'analyze_exam[caesuraPoints][0][percentage]' => '0',
            'analyze_exam[caesuraPoints][0][grade]' => '1',
            'analyze_exam[caesuraPoints][1][percentage]' => '60',
            'analyze_exam[caesuraPoints][1][grade]' => '6',
            'analyze_exam[passPercentage]' => '60',
        ]);

        self::assertResponseIsSuccessful();
        // S3 scores 25%: 1 + 25 / 60 * 5 = 3.08 with the custom caesura (1.5 with the default one).
        self::assertSame('3.1', $crawler->filter('#panel-students tbody tr[data-search="s3"] .grade-pill')->text());
        self::assertSelectorTextContains('.command-line__code', '--caesura=0:1.0,60:6.0,100:10.0 --pass-percentage=60');
    }

    public function testAcceptsCaesuraPointsInAnyOrder(): void
    {
        $this->submitAnalysis($this->workbooks->create(self::exam()), [
            'analyze_exam[caesuraPoints][0][percentage]' => '100',
            'analyze_exam[caesuraPoints][0][grade]' => '10',
            'analyze_exam[caesuraPoints][2][percentage]' => '20',
            'analyze_exam[caesuraPoints][2][grade]' => '1',
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
            'analyze_exam[caesuraPoints][0][grade]' => '7',
        ]);

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('ul.field-errors', 'Caesura grades must not decrease, got 5.5 after 7.');
    }

    public function testRejectsCrossSiteSubmissions(): void
    {
        $crawler = $this->client->request('GET', '/');
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

        $crawler = $this->client->request('GET', '/sample');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.kpi--pass-rate', '68 of 452 students passed');
        self::assertSame(452, $crawler->filter('#panel-students tbody tr[data-data-table-target="row"]')->count());
        self::assertSame(48, $crawler->filter('.item-map__dot')->count(), 'Question 24 has no rit-value and is not plotted.');
    }

    /**
     * @param array<string, string> $values
     */
    private function submitAnalysis(?string $workbook, array $values = []): Crawler
    {
        $crawler = $this->client->request('GET', '/');
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
