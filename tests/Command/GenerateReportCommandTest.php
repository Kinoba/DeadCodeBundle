<?php

declare(strict_types=1);

namespace Kinoba\DeadCodeBundle\Tests\Command;

use Kinoba\DeadCodeBundle\Command\GenerateReportCommand;
use Kinoba\DeadCodeBundle\Service\CoverageReporter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

#[CoversClass(GenerateReportCommand::class)]
final class GenerateReportCommandTest extends TestCase
{
    public function testItDisplaysGlobalCoverage(): void
    {
        $tester = $this->createTester([
            'files' => [],
            'totalLines' => 120,
            'coveredLines' => 90,
            'coveragePercentage' => 75.0,
        ]);

        static::assertSame(Command::SUCCESS, $tester->execute([]));

        $display = $tester->getDisplay();
        static::assertStringContainsString('Rapport de Coverage', $display);
        static::assertStringContainsString('Coverage global: 75%', $display);
        static::assertStringContainsString('Lignes couvertes: 90/120', $display);
    }

    public function testItOnlyListsPartiallyCoveredFiles(): void
    {
        $tester = $this->createTester([
            'files' => [
                [
                    'path' => '/src/Dead.php',
                    'totalLines' => 4,
                    'coveredLines' => 1,
                    'coveragePercentage' => 25.0,
                ],
                [
                    'path' => '/src/Covered.php',
                    'totalLines' => 10,
                    'coveredLines' => 10,
                    'coveragePercentage' => 100.0,
                ],
            ],
            'totalLines' => 14,
            'coveredLines' => 11,
            'coveragePercentage' => 78.57,
        ]);

        $tester->execute([]);

        $display = $tester->getDisplay();
        static::assertStringContainsString('/src/Dead.php: 25% (1/4 lignes)', $display);
        static::assertStringNotContainsString('/src/Covered.php', $display);
    }

    public function testItSucceedsWithoutAnyCoverageData(): void
    {
        $tester = $this->createTester([
            'files' => [],
            'totalLines' => 0,
            'coveredLines' => 0,
            'coveragePercentage' => 0,
        ]);

        static::assertSame(Command::SUCCESS, $tester->execute([]));
        static::assertStringContainsString('Coverage global: 0%', $tester->getDisplay());
    }

    private function createTester(array $dashboardData): CommandTester
    {
        $reporter = $this->createMock(CoverageReporter::class);
        $reporter->expects(self::once())->method('getDashboardData')->willReturn($dashboardData);

        return new CommandTester(new GenerateReportCommand($reporter));
    }
}
