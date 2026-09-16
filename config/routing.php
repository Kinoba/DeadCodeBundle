<?php

use Kinoba\DeadCodeBundle\Controller\DashboardController;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

return static function (RoutingConfigurator $routes): void {
    $routes->add('dead_code_dashboard', '/dead-code/dashboard')
        ->controller([DashboardController::class, 'dashboard']);

    $routes->add('dead_code_api', '/dead-code/api')
        ->controller([DashboardController::class, 'api']);

    $routes->add('dead_code_clear', '/dead-code/clear')
        ->controller([DashboardController::class, 'clear'])
        ->methods(['POST']);
};
