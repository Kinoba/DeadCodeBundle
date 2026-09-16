<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

if (!\extension_loaded('pcov') && !\function_exists('pcov\\start')) {
    require __DIR__ . '/pcov_stub.php';
}
