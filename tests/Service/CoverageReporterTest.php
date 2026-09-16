<?php

declare(strict_types=1);

namespace Kinoba\DeadCodeBundle\Tests\Service;

use Kinoba\DeadCodeBundle\Service\CoverageReporter;
use Kinoba\DeadCodeBundle\Service\RedisStorage;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(CoverageReporter::class)]
final class CoverageReporterTest extends TestCase
{
    public function testGetDashboardDataWithoutCoverage(): void
    {
        $reporter = new CoverageReporter($this->createStorage([]));

        self::assertSame([
            'files' => [],
            'totalLines' => 0,
            'coveredLines' => 0,
            'coveragePercentage' => 0,
        ], $reporter->getDashboardData());
    }

    public function testGetDashboardDataAggregatesLines(): void
    {
        $reporter = new CoverageReporter($this->createStorage([
            'src/Foo.php' => [10 => 1, 11 => 0, 12 => 3, 13 => 0],
            'src/Bar.php' => [5 => 2, 6 => 1],
        ]));

        $data = $reporter->getDashboardData();

        self::assertSame(6, $data['totalLines']);
        self::assertSame(4, $data['coveredLines']);
        self::assertSame(66.67, $data['coveragePercentage']);
        self::assertCount(2, $data['files']);
    }

    public function testFilesAreSortedByAscendingCoverage(): void
    {
        $reporter = new CoverageReporter($this->createStorage([
            'src/Covered.php' => [1 => 1, 2 => 1],
            'src/Dead.php' => [1 => 0, 2 => 0],
            'src/Partial.php' => [1 => 1, 2 => 0],
        ]));

        $data = $reporter->getDashboardData();

        self::assertSame(
            ['src/Dead.php', 'src/Partial.php', 'src/Covered.php'],
            array_column($data['files'], 'path')
        );
        self::assertSame([0.0, 50.0, 100.0], array_column($data['files'], 'coveragePercentage'));
    }

    public function testFileWithoutLinesHasZeroPercentage(): void
    {
        $reporter = new CoverageReporter($this->createStorage(['src/Empty.php' => []]));

        $data = $reporter->getDashboardData();

        self::assertSame([
            'path' => 'src/Empty.php',
            'totalLines' => 0,
            'coveredLines' => 0,
            'coveragePercentage' => 0,
            'lines' => [],
            'code' => [],
        ], $data['files'][0]);
    }

    public function testClearDelegatesToStorage(): void
    {
        $storage = $this->createMock(RedisStorage::class);
        $storage->expects(self::once())->method('clear');

        $reporter = new CoverageReporter($storage);
        $reporter->clear();
    }

    private function createStorage(array $coverage): RedisStorage
    {
        $storage = $this->createStub(RedisStorage::class);
        $storage->method('getAllCoverage')->willReturn($coverage);

        return $storage;
    }
}
