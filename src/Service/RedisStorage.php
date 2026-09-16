<?php

declare(strict_types=1);

namespace Kinoba\DeadCodeBundle\Service;

use Predis\Client;
use Predis\Connection\ConnectionException;

class RedisStorage
{
    private Client $redis;
    private int $ttl;

    public function __construct(string $dsn, int $ttl)
    {
        $this->redis = new Client($dsn);
        $this->ttl = $ttl;
    }

    public function saveCoverage(string $requestId, array $coverage): void
    {
        try {
            // Save each file's coverage separately
            foreach ($coverage as $file => $lines) {
                $key = "coverage:files:{$file}";
                $this->redis->hset($key, $requestId, json_encode($lines));
                $this->redis->expire($key, $this->ttl);
            }

            // Also store the list of requests for this file
            $this->redis->sadd('coverage:requests', $requestId);
            $this->redis->expire('coverage:requests', $this->ttl);
        } catch (ConnectionException $e) {
            // Log the error if Redis is unavailable
            error_log("Redis error: " . $e->getMessage());
        }
    }

    public function getAllCoverage(): array
    {
        try {
            $allFiles = $this->redis->keys('coverage:files:*');
            $coverage = [];

            foreach ($allFiles as $fileKey) {
                $file = str_replace('coverage:files:', '', $fileKey);
                $requestData = $this->redis->hgetall($fileKey);
                $mergedLines = [];

                foreach ($requestData as $linesJson) {
                    $lines = json_decode($linesJson, true);
                    foreach ($lines as $line => $count) {
                        // Negative values are pcov "not executed" sentinels, never hit counts.
                        $mergedLines[$line] = ($mergedLines[$line] ?? 0) + max(0, (int) $count);
                    }
                }

                $coverage[$file] = $mergedLines;
            }

            return $coverage;
        } catch (ConnectionException) {
            return [];
        }
    }

    public function clear(): void
    {
        try {
            $this->redis->del($this->redis->keys('coverage:*'));
        } catch (ConnectionException) {
        }
    }
}
