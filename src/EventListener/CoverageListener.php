<?php

declare(strict_types=1);

namespace Kinoba\DeadCodeBundle\EventListener;

use Kinoba\DeadCodeBundle\Service\CoverageCollector;
use Kinoba\DeadCodeBundle\Service\RedisStorage;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\TerminateEvent;

class CoverageListener
{
    private CoverageCollector $collector;
    private RedisStorage $storage;
    private ?string $requestId = null;

    public function __construct(CoverageCollector $collector, RedisStorage $storage)
    {
        $this->collector = $collector;
        $this->storage = $storage;
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest() || !$this->collector->shouldCollect()) {
            return;
        }

        $this->requestId = uniqid('req_', true);
        $this->collector->start();
    }

    public function onKernelTerminate(TerminateEvent $event): void
    {
        if ($this->requestId === null) {
            return;
        }

        $coverage = $this->collector->stop();
        if ($coverage) {
            $this->storage->saveCoverage($this->requestId, $coverage);
        }

        $this->requestId = null;
    }
}
