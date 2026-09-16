<?php

declare(strict_types=1);

namespace Kinoba\DeadCodeBundle\Tests\Controller;

use Kinoba\DeadCodeBundle\Controller\DashboardController;
use Kinoba\DeadCodeBundle\Service\CoverageReporter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;

#[CoversClass(DashboardController::class)]
final class DashboardControllerTest extends TestCase
{
    private const DASHBOARD_DATA = [
        'files' => [
            [
                'path' => '/src/Foo.php',
                'totalLines' => 2,
                'coveredLines' => 1,
                'coveragePercentage' => 50.0,
                'lines' => [10 => 1, 11 => 0],
            ],
        ],
        'totalLines' => 2,
        'coveredLines' => 1,
        'coveragePercentage' => 50.0,
    ];

    public function testDashboardRendersTheTwigTemplate(): void
    {
        $twig = new class {
            public string $view = '';
            public array $parameters = [];

            public function render(string $view, array $parameters = []): string
            {
                $this->view = $view;
                $this->parameters = $parameters;

                return '<html>report</html>';
            }
        };

        $controller = $this->createController(['twig' => $twig]);

        $response = $controller->dashboard($this->createReporter());

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('<html>report</html>', $response->getContent());
        self::assertSame('@DeadCode/dashboard.html.twig', $twig->view);
        self::assertSame([
            'files' => self::DASHBOARD_DATA['files'],
            'totalLines' => 2,
            'coveredLines' => 1,
            'coveragePercentage' => 50.0,
        ], $twig->parameters);
    }

    public function testDashboardFailsWhenTwigIsNotAvailable(): void
    {
        $controller = $this->createController([]);

        $this->expectException(\LogicException::class);

        $controller->dashboard($this->createReporter());
    }

    public function testApiReturnsDashboardDataAsJson(): void
    {
        $controller = $this->createController([]);

        $response = $controller->api($this->createReporter());

        self::assertInstanceOf(JsonResponse::class, $response);
        self::assertSame(200, $response->getStatusCode());
        self::assertSame('application/json', $response->headers->get('Content-Type'));
        self::assertJsonStringEqualsJsonString(
            json_encode(self::DASHBOARD_DATA, \JSON_THROW_ON_ERROR),
            (string) $response->getContent()
        );
    }

    public function testClearInvokesReporterAndRedirectsToDashboard(): void
    {
        $router = new class {
            public function generate(string $name, array $parameters = [], int $referenceType = 1): string
            {
                return '/dead-code/dashboard';
            }
        };

        $reporter = $this->createMock(CoverageReporter::class);
        $reporter->expects(self::once())->method('clear');

        $controller = $this->createController(['router' => $router]);

        $response = $controller->clear($reporter);

        self::assertInstanceOf(RedirectResponse::class, $response);
        self::assertSame(302, $response->getStatusCode());
        self::assertSame('/dead-code/dashboard', $response->getTargetUrl());
    }

    private function createController(array $services): DashboardController
    {
        $controller = new DashboardController();
        $controller->setContainer($this->createContainer($services));

        return $controller;
    }

    private function createContainer(array $services): ContainerInterface
    {
        return new class($services) implements ContainerInterface {
            public function __construct(private array $services)
            {
            }

            public function get(string $id): mixed
            {
                return $this->services[$id];
            }

            public function has(string $id): bool
            {
                return isset($this->services[$id]);
            }
        };
    }

    private function createReporter(): CoverageReporter
    {
        $reporter = $this->createStub(CoverageReporter::class);
        $reporter->method('getDashboardData')->willReturn(self::DASHBOARD_DATA);

        return $reporter;
    }
}
