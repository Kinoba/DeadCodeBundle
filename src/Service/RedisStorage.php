<?php

declare(strict_types=1);

namespace Kinoba\DeadCodeBundle\Service;

use Predis\Client;
use Predis\Connection\ConnectionException;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

class RedisStorage
{
    private Client $redis;
    private int $ttl;
    private LoggerInterface $logger;

    public function __construct(string $dsn, int $ttl, ?LoggerInterface $logger = null)
    {
        $this->redis = new Client($dsn);
        $this->ttl = $ttl;
        $this->logger = $logger ?? new NullLogger();
    }

    public function saveCoverage(string $requestId, array $coverage): void
    {
        try {
            // Save each file's coverage separately
            foreach ($coverage as $file => $lines) {
                $key = "coverage:files:{$file}";
                $this->redis->hset($key, $requestId, json_encode($lines, \JSON_THROW_ON_ERROR));
                $this->redis->expire($key, $this->ttl);
            }

            // Also store the list of requests for this file
            $this->redis->sadd('coverage:requests', [$requestId]);
            $this->redis->expire('coverage:requests', $this->ttl);
        } catch (ConnectionException $e) {
            $this->logger->error('Redis error: {message}', ['message' => $e->getMessage(), 'exception' => $e]);
        }
    }

    /**
     * @return array<string, array<int, int>>
     */
    public function getAllCoverage(): array
    {
        try {
            $allFiles = $this->redis->keys('coverage:files:*');
            $coverage = [];

            foreach ($allFiles as $fileKey) {
                $fileKey = (string) $fileKey;
                $file = str_replace(search: 'coverage:files:', replace: '', subject: $fileKey);
                $requestData = $this->redis->hgetall($fileKey);
                $mergedLines = [];

                foreach ($requestData as $linesJson) {
                    $lines = json_decode((string) $linesJson, associative: true);
                    if (!\is_array($lines)) {
                        continue;
                    }

                    foreach ($lines as $line => $count) {
                        // Negative values are pcov "not executed" sentinels, never hit counts.
                        $mergedLines[(int) $line] = ($mergedLines[(int) $line] ?? 0) + max(0, (int) $count);
                    }
                }

                $coverage[$file] = $mergedLines;
            }

            return $coverage;
        } catch (ConnectionException $e) {
            $this->logger->error('Redis error: {message}', ['message' => $e->getMessage(), 'exception' => $e]);

            return [];
        }
    }

    public function clear(): void
    {
        try {
            $keys = $this->redis->keys('coverage:*');
            if ($keys !== []) {
                $this->redis->del(array_map('strval', $keys));
            }
        } catch (ConnectionException $e) {
            $this->logger->error('Redis error: {message}', ['message' => $e->getMessage(), 'exception' => $e]);
        }
    }
}
