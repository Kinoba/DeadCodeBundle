<?php

declare(strict_types=1);

namespace Kinoba\DeadCodeBundle\Tests\Service;

use Kinoba\DeadCodeBundle\Service\CoverageCollector;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(CoverageCollector::class)]
final class CoverageCollectorTest extends TestCase
{
    public function testShouldNotCollectWhenDisabled(): void
    {
        $collector = new CoverageCollector(false, 100, []);

        static::assertFalse($collector->shouldCollect());
    }

    public function testShouldAlwaysCollectWithFullSamplingRate(): void
    {
        $collector = new CoverageCollector(true, 100, []);

        static::assertTrue($collector->shouldCollect());
    }

    public function testShouldNeverCollectWithZeroSamplingRate(): void
    {
        $collector = new CoverageCollector(true, 0, []);

        static::assertFalse($collector->shouldCollect());
    }

    public function testStartDoesNothingWhenCollectionIsDisabled(): void
    {
        $collector = new CoverageCollector(false, 100, []);
        $collector->start();

        static::assertFalse(self::isStarted($collector));
    }

    public function testStopReturnsNullWhenNothingWasStarted(): void
    {
        $collector = new CoverageCollector(true, 100, []);

        static::assertNull($collector->stop());
    }

    public function testStopReturnsEmptyArrayWhenNoFileIsWaitingForCollection(): void
    {
        $collector = new CoverageCollector(true, 100, []);
        $collector->start();

        static::assertSame([], $collector->stop());
    }

    public function testStopClampsNotExecutedSentinelsToZero(): void
    {
        if (\extension_loaded('pcov')) {
            static::markTestSkipped('Relies on the pcov stub to feed deterministic coverage data.');
        }

        $GLOBALS['__pcov_stub_coverage'] = [
            'src/Lead.php' => [10 => 3, 11 => -1],
            '/vendor/Dep.php' => [5 => 1],
        ];

        try {
            $collector = new CoverageCollector(true, 100, ['/vendor/']);
            $collector->start();

            static::assertSame(['src/Lead.php' => [10 => 3, 11 => 0]], $collector->stop());
        } finally {
            unset($GLOBALS['__pcov_stub_coverage']);
        }
    }

    #[DataProvider('ignoredPathProvider')]
    public function testIsIgnored(string $file, bool $expected): void
    {
        $collector = new CoverageCollector(true, 100, ['vendor/', 'var/cache/']);

        $method = new \ReflectionMethod($collector, 'isIgnored');

        static::assertSame($expected, $method->invoke($collector, $file));
    }

    /**
     * @return iterable<string, array{string, bool}>
     */
    public static function ignoredPathProvider(): iterable
    {
        yield 'app vendor file is ignored' => ['/app/vendor/acme/lib/Foo.php', true];
        yield 'vendor file is ignored' => ['/vendor/acme/lib/Foo.php', true];
        yield 'app cache file is ignored' => ['/app/var/cache/dev/Container.php', true];
        yield 'cache file is ignored' => ['/var/cache/dev/Container.php', true];
        yield 'app source file is collected' => ['/app/src/Controller/HomeController.php', false];
        yield 'source file is collected' => ['src/Controller/HomeController.php', false];
    }

    private static function isStarted(CoverageCollector $collector): bool
    {
        $property = new \ReflectionProperty($collector, 'started');

        return (bool) $property->getValue($collector);
    }
}
