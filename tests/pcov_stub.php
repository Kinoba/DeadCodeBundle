<?php

declare(strict_types=1);

// No-op stand-in for the pcov extension, loaded only when it isn't actually installed.
namespace pcov;

const all = 0;
const inclusive = 1;
const exclusive = 2;

function start(): void
{
}

function stop(): void
{
}

function clear(): void
{
}

function waiting(): array
{
    return array_keys($GLOBALS['__pcov_stub_coverage'] ?? []);
}

function collect(int $type = all, array $filter = []): array
{
    return $GLOBALS['__pcov_stub_coverage'] ?? [];
}
