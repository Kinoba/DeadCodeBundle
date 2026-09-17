<?php

declare(strict_types=1);

// No-op stand-in for the pcov extension, loaded only when it isn't actually installed.
namespace pcov;

// @mago-expect lint:constant-name
const all = 0;

// @mago-expect lint:constant-name
const inclusive = 1;

// @mago-expect lint:constant-name
const exclusive = 2;

function start(): void {}

function stop(): void {}

function clear(): void {}

function waiting(): array
{
    /** @var array<string, array<int, int>> $coverage */
    $coverage = $GLOBALS['__pcov_stub_coverage'] ?? [];

    return array_keys($coverage);
}

function collect(int $type = all, array $filter = []): array
{
    // @mago-expect lint:inline-variable-return (needed so the @var below narrows the return type)
    /** @var array<string, array<int, int>> $coverage */
    $coverage = $GLOBALS['__pcov_stub_coverage'] ?? [];

    return $coverage;
}
