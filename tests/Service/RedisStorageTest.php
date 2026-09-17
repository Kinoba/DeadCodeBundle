<?php

declare(strict_types=1);

namespace Kinoba\DeadCodeBundle\Tests\Service;

use Kinoba\DeadCodeBundle\Service\RedisStorage;
use Kinoba\DeadCodeBundle\Tests\Stub\FakePredisClient;
use Kinoba\DeadCodeBundle\Tests\Stub\RecordingLogger;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Predis\Client;
use Predis\Connection\ConnectionException;
use Predis\Connection\NodeConnectionInterface;

#[CoversClass(RedisStorage::class)]
final class RedisStorageTest extends TestCase
{
    public function testSaveCoverageStoresOneHashPerFileAndTracksRequest(): void
    {
        $client = new FakePredisClient();
        $storage = $this->createStorage($client, 3600);

        $storage->saveCoverage('req_1', [
            '/app/src/Foo.php' => [10 => 1],
            '/app/src/Bar.php' => [4 => 0],
        ]);

        static::assertSame(
            [
                ['hset', 'coverage:files:/app/src/Foo.php', 'req_1', '{"10":1}'],
                ['expire', 'coverage:files:/app/src/Foo.php', 3600],
                ['hset', 'coverage:files:/app/src/Bar.php', 'req_1', '{"4":0}'],
                ['expire', 'coverage:files:/app/src/Bar.php', 3600],
                ['sadd', 'coverage:requests', 'req_1'],
                ['expire', 'coverage:requests', 3600],
            ],
            $client->calls,
        );
    }

    public function testSaveCoverageSwallowsConnectionException(): void
    {
        $client = new FakePredisClient();
        $client->exception = $this->createConnectionException();
        $logger = new RecordingLogger();
        $storage = $this->createStorage($client, 60, $logger);

        $storage->saveCoverage('req_1', ['/app/src/Foo.php' => [10 => 1]]);

        static::assertSame([], $client->calls);
        static::assertCount(1, $logger->records);
        static::assertStringContainsString('Connection refused', (string) $logger->records[0]['context']['message']);
    }

    public function testGetAllCoverageMergesLineHitsAcrossRequests(): void
    {
        $client = new FakePredisClient();
        $client->keys = ['coverage:files:/app/src/Foo.php'];
        $client->hashes = [
            'coverage:files:/app/src/Foo.php' => [
                'req_1' => '{"10":1,"11":0}',
                'req_2' => '{"10":2,"11":0}',
            ],
        ];

        $coverage = $this->createStorage($client, 60)->getAllCoverage();

        static::assertSame(['/app/src/Foo.php' => [10 => 3, 11 => 0]], $coverage);
    }

    public function testGetAllCoverageClampsNegativeNotExecutedSentinels(): void
    {
        $client = new FakePredisClient();
        $client->keys = ['coverage:files:/app/src/Foo.php'];
        $client->hashes = [
            'coverage:files:/app/src/Foo.php' => [
                'req_1' => '{"10":-1,"11":-1}',
                'req_2' => '{"10":-1,"11":2}',
                'req_3' => '{"10":-1,"11":-1}',
            ],
        ];

        $coverage = $this->createStorage($client, 60)->getAllCoverage();

        static::assertSame(['/app/src/Foo.php' => [10 => 0, 11 => 2]], $coverage);
    }

    public function testGetAllCoverageReturnsEmptyArrayWhenRedisIsUnavailable(): void
    {
        $client = new FakePredisClient();
        $client->exception = $this->createConnectionException();

        static::assertSame([], $this->createStorage($client, 60)->getAllCoverage());
    }

    public function testClearDeletesEveryCoverageKey(): void
    {
        $client = new FakePredisClient();
        $client->keys = ['coverage:files:/app/src/Foo.php', 'coverage:requests'];

        $this->createStorage($client, 60)->clear();

        static::assertSame(
            [
                ['keys', 'coverage:*'],
                ['del', ['coverage:files:/app/src/Foo.php', 'coverage:requests']],
            ],
            $client->calls,
        );
    }

    public function testClearSwallowsConnectionException(): void
    {
        $client = new FakePredisClient();
        $client->exception = $this->createConnectionException();

        $this->createStorage($client, 60)->clear();

        static::assertSame([], $client->calls);
    }

    private function createStorage(Client $client, int $ttl, ?RecordingLogger $logger = null): RedisStorage
    {
        $storage = (new \ReflectionClass(RedisStorage::class))->newInstanceWithoutConstructor();

        $redis = new \ReflectionProperty($storage, 'redis');
        $redis->setValue($storage, $client);

        $ttlProperty = new \ReflectionProperty($storage, 'ttl');
        $ttlProperty->setValue($storage, $ttl);

        $loggerProperty = new \ReflectionProperty($storage, 'logger');
        $loggerProperty->setValue($storage, $logger ?? new RecordingLogger());

        return $storage;
    }

    private function createConnectionException(): ConnectionException
    {
        return new ConnectionException($this->createStub(NodeConnectionInterface::class), 'Connection refused');
    }
}
