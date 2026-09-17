<?php

declare(strict_types=1);

namespace Kinoba\DeadCodeBundle\Service;

class CoverageCollector
{
    private bool $enabled;
    private int $samplingRate;

    /** @var list<string> */
    private array $ignoredPaths;
    private bool $started = false;

    /**
     * @param list<string> $ignoredPaths
     */
    public function __construct(bool $enabled, int $samplingRate, array $ignoredPaths)
    {
        $this->enabled = $enabled;
        $this->samplingRate = $samplingRate;
        $this->ignoredPaths = $ignoredPaths;
    }

    public function shouldCollect(): bool
    {
        if (!$this->enabled || !$this->isPcovAvailable()) {
            return false;
        }

        return random_int(1, max: 100) <= $this->samplingRate;
    }

    public function start(): void
    {
        if ($this->started || !$this->enabled || !$this->isPcovAvailable()) {
            return;
        }

        \pcov\start();
        $this->started = true;
    }

    private function isPcovAvailable(): bool
    {
        return \extension_loaded('pcov') || \function_exists('pcov\\start');
    }

    public function stop(): ?array
    {
        if (!$this->started) {
            return null;
        }

        \pcov\stop();

        $files = \pcov\waiting();

        /** @var array<string, array<int, int>> $coverage */
        $coverage = $files !== [] ? \pcov\collect(\pcov\inclusive, $files) : [];
        \pcov\clear();
        $this->started = false;

        $filteredCoverage = [];
        foreach ($coverage as $file => $lines) {
            if ($this->isIgnored($file)) {
                continue;
            }
            $filteredCoverage[$file] = $this->normalizeLines($lines);
        }

        return $filteredCoverage;
    }

    /**
     * pcov reports -1 for "executable but not executed in this request". That is a sentinel, not a
     * count, so it is clamped to 0: the line stays known as executable while contributing no hits.
     *
     * @param array<int, int> $lines
     *
     * @return array<int, int>
     */
    private function normalizeLines(array $lines): array
    {
        return array_map(static fn(int $hits): int => max(0, $hits), $lines);
    }

    private function isIgnored(string $file): bool
    {
        foreach ($this->ignoredPaths as $path) {
            if (str_contains($file, $path)) {
                return true;
            }
        }
        return false;
    }
}
