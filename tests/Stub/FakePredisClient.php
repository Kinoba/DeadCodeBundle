<?php

declare(strict_types=1);

namespace Kinoba\DeadCodeBundle\Tests\Stub;

use Predis\Client;
use Predis\Connection\ConnectionException;

/**
 * Predis commands are handled through __call(), which cannot be mocked reliably,
 * so the few commands used by RedisStorage are implemented explicitly here.
 */
final class FakePredisClient extends Client
{
    /** @var list<array<int, mixed>> */
    public array $calls = [];

    /** @var list<string> */
    public array $keys = [];

    /** @var array<string, array<string, string>> */
    public array $hashes = [];

    public ?ConnectionException $exception = null;

    public function __construct($parameters = null, $options = null)
    {
    }

    public function hset($key, $field, $value)
    {
        $this->record('hset', $key, $field, $value);

        return 1;
    }

    public function expire($key, $seconds, $expireOption = '')
    {
        $this->record('expire', $key, $seconds);

        return 1;
    }

    public function sadd($key, array|string $member, ...$members)
    {
        $this->record('sadd', $key, $member);

        return 1;
    }

    public function smembers($key)
    {
        $this->record('smembers', $key);

        return array_keys($this->hashes[$key] ?? []);
    }

    public function keys($pattern)
    {
        $this->record('keys', $pattern);

        return $this->keys;
    }

    public function hgetall($key)
    {
        $this->record('hgetall', $key);

        return $this->hashes[$key] ?? [];
    }

    public function del(array|string $keyOrKeys, ...$keys): int
    {
        $this->record('del', $keyOrKeys);

        return \is_array($keyOrKeys) ? \count($keyOrKeys) : 1;
    }

    private function record(string $command, mixed ...$arguments): void
    {
        if (null !== $this->exception) {
            throw $this->exception;
        }

        $this->calls[] = [$command, ...$arguments];
    }
}
