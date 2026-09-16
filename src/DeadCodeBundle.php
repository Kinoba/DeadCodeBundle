<?php

declare(strict_types=1);

namespace Kinoba\DeadCodeBundle;

use Symfony\Component\HttpKernel\Bundle\Bundle;

class DeadCodeBundle extends Bundle
{
    public function getPath(): string
    {
        return \dirname(__DIR__);
    }
}
