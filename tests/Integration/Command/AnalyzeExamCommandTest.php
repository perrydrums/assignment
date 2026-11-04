<?php

declare(strict_types=1);

namespace App\Tests\Integration\Command;

use App\Tests\Support\TemporaryWorkbooks;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class AnalyzeExamCommandTest extends KernelTestCase
{
    private TemporaryWorkbooks $workbooks;
    private CommandTester $tester;

    protected function setUp(): void
    {
        $this->workbooks = new TemporaryWorkbooks();
        $application = new Application(self::bootKernel());
        $this->tester = new CommandTester($application->find('app:analyze-exam'));
    }

    protected function tearDown(): void
    {
        $this->workbooks->removeAll();
        parent::tearDown();
    }

    public function testPrintsGradesAndQuestionStatistics(): void
    {
        $path = $this->workbooks->create([
            ['ID', 'Q1', 'Q2'],
            ['Max question score:', 4, 6],
            ['S1', 4, 6],
            ['S2', 3, 4],
            ['S3', 1, 1.5],
        ]);

        $exitCode = $this->tester->execute(['file' => $path]);
        $output = $this->tester->getDisplay();

        self::assertSame(Command::SUCCESS, $exitCode, $output);
        self::assertMatchesRegularExpression('/Students\s+3/', $output);
        self::assertMatchesRegularExpression('/Questions\s+2/', $output);
        self::assertMatchesRegularExpression('/Maximum score\s+10/', $output);
        self::assertMatchesRegularExpression('/S1\s+10\s+100\.0%\s+10\.0\s+passed/', $output);
        self::assertMatchesRegularExpression('/S2\s+7\s+70\.0%\s+5\.5\s+passed/', $output);
        self::assertMatchesRegularExpression('/S3\s+2\.5\s+25\.0%\s+1\.5\s+FAILED/', $output);
        // Q1: average 8/3 of 4 = 0.667
        self::assertMatchesRegularExpression('/Q1\s+4\s+0\.667\s+0\.\d{3}/', $output);
    }

    public function testAcceptsACustomCaesura(): void
    {
        $path = $this->workbooks->create([['ID', 'Q1'], ['Max question score:', 10], ['S1', 6], ['S2', 4]]);

        $exitCode = $this->tester->execute(['file' => $path, '--caesura' => '0:1,60:6,100:10', '--pass-percentage' => '60']);
        $output = $this->tester->getDisplay();

        self::assertSame(Command::SUCCESS, $exitCode, $output);
        self::assertMatchesRegularExpression('/S1\s+6\s+60\.0%\s+6\.0\s+passed/', $output);
        self::assertMatchesRegularExpression('/S2\s+4\s+40\.0%\s+4\.3\s+FAILED/', $output);
    }

    public function testShowsAnUndefinedRitValue(): void
    {
        $path = $this->workbooks->create([['ID', 'Q1', 'Q2'], ['Max question score:', 1, 1], ['S1', 1, 1], ['S2', 1, 0]]);

        $this->tester->execute(['file' => $path]);

        self::assertMatchesRegularExpression('/Q1\s+1\s+1\.000\s+n\/a/', $this->tester->getDisplay());
    }

    public function testReportsAnInvalidWorkbook(): void
    {
        $path = $this->workbooks->create([['ID', 'Q1'], ['Max question score:', 1], ['S1', 'x']]);

        $exitCode = $this->tester->execute(['file' => $path]);

        self::assertSame(Command::FAILURE, $exitCode);
        self::assertStringContainsString('Cell B3', $this->tester->getDisplay());
    }

    public function testReportsAnInvalidCaesura(): void
    {
        $exitCode = $this->tester->execute(['file' => 'irrelevant.xlsx', '--caesura' => '70:5.5,20:1']);

        self::assertSame(Command::INVALID, $exitCode);
        self::assertStringContainsString('Invalid caesura', $this->tester->getDisplay());
    }
}
