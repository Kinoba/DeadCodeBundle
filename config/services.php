<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Kinoba\DeadCodeBundle\Command\GenerateReportCommand;
use Kinoba\DeadCodeBundle\Controller\DashboardController;
use Kinoba\DeadCodeBundle\EventListener\CoverageListener;
use Kinoba\DeadCodeBundle\Service\CoverageCollector;
use Kinoba\DeadCodeBundle\Service\CoverageReporter;
use Kinoba\DeadCodeBundle\Service\RedisStorage;

return static function (ContainerConfigurator $container) {
    $services = $container->services();

    $services->set(CoverageCollector::class)->arg('$enabled', '%dead_code.enabled%')->arg(
        '$samplingRate',
        '%dead_code.sampling_rate%',
    )->arg('$ignoredPaths', '%dead_code.ignored_paths%');

    $services->set(RedisStorage::class)->arg('$dsn', '%dead_code.redis_dsn%')->arg(
        '$ttl',
        '%dead_code.cache_ttl%',
    )->arg('$logger', service('logger')->ignoreOnInvalid());

    $services->set(CoverageReporter::class)->arg('$storage', service(RedisStorage::class));

    $services->set(CoverageListener::class)->arg('$collector', service(CoverageCollector::class))->arg(
        '$storage',
        service(RedisStorage::class),
    )->tag('kernel.event_listener', [
        'event' => 'kernel.request',
        'method' => 'onKernelRequest',
    ])->tag('kernel.event_listener', ['event' => 'kernel.terminate', 'method' => 'onKernelTerminate']);

    // CoverageReporter is injected as a controller action argument, not in the constructor.
    // autowire() is required so AbstractController::setContainer() (#[Required]) gets called.
    $services->set(DashboardController::class)->autowire()->autoconfigure()->tag('controller.service_arguments');

    $services->set(GenerateReportCommand::class)->arg('$reporter', service(CoverageReporter::class))->tag(
        'console.command',
    );
};
