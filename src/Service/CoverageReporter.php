<?php

declare(strict_types=1);

namespace Kinoba\DeadCodeBundle\Service;

class CoverageReporter
{
    private RedisStorage $storage;

    public function __construct(RedisStorage $storage)
    {
        $this->storage = $storage;
    }

    public function getDashboardData(): array
    {
        $coverage = $this->storage->getAllCoverage();
        $totalLines = 0;
        $coveredLines = 0;
        $files = [];

        foreach ($coverage as $file => $lines) {
            $totalFileLines = count($lines);
            $coveredFileLines = count(array_filter($lines, fn($count) => $count > 0));

            $totalLines += $totalFileLines;
            $coveredLines += $coveredFileLines;

            $files[] = [
                'path' => $file,
                'totalLines' => $totalFileLines,
                'coveredLines' => $coveredFileLines,
                'coveragePercentage' => $totalFileLines > 0 ? round(($coveredFileLines / $totalFileLines) * 100, 2) : 0,
                'lines' => $lines,
                'code' => $this->readSourceLines($file),
            ];
        }

        usort($files, fn($a, $b) => $a['coveragePercentage'] <=> $b['coveragePercentage']);

        return [
            'files' => $files,
            'totalLines' => $totalLines,
            'coveredLines' => $coveredLines,
            'coveragePercentage' => $totalLines > 0 ? round(($coveredLines / $totalLines) * 100, 2) : 0,
        ];
    }

    public function clear(): void
    {
        $this->storage->clear();
    }

    /**
     * Reads the source file content so the dashboard can display it annotated with coverage.
     *
     * @return array<int, string> Source lines indexed from 1, or empty if the file can't be read.
     */
    private function readSourceLines(string $path): array
    {
        if (!is_readable($path)) {
            return [];
        }

        $lines = @file($path, \FILE_IGNORE_NEW_LINES);
        if ($lines === false) {
            return [];
        }

        return array_combine(range(1, count($lines)), $lines);
    }
}
