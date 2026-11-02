<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Redis;
use Tests\TestCase;

/**
 * Verifies the Redis connection the cache/queue/session layer depends on.
 *
 * The test suite runs with CACHE_STORE=array and no Redis server, so this test
 * only asserts a real connection when one is actually reachable. When Redis is
 * not configured (the default host is empty/localhost) or not reachable, it is
 * marked skipped rather than failed, so CI stays green without a Redis service.
 * In an environment that has Redis (local docker-compose, Render, or a developer
 * machine with Upstash credentials) the same test exercises the real connection
 * and fails loudly if it cannot be established.
 */
class RedisConnectivityTest extends TestCase
{
    public function test_default_redis_connection_is_reachable_when_configured(): void
    {
        $host = (string) config('database.redis.default.host');
        $port = (int) config('database.redis.default.port');
        $scheme = config('database.redis.default.scheme');
        $hasUrl = (string) config('database.redis.default.url') !== '';

        // No real endpoint configured (default test env): nothing to verify.
        if (! $hasUrl && in_array($host, ['', '127.0.0.1', 'localhost'], true)) {
            $this->markTestSkipped(
                'No remote Redis endpoint configured (host='.$host.'); set REDIS_HOST/REDIS_PORT/REDIS_PASSWORD to run this check.'
            );
        }

        try {
            $ping = Redis::connection('default')->ping();
        } catch (\Throwable $e) {
            $this->fail(sprintf(
                'Redis connection failed (%s:%d scheme=%s): %s',
                $host,
                $port,
                $scheme ?: 'plain',
                $e->getMessage()
            ));
        }

        $this->assertTrue($ping === true || $ping === 'PONG' || $ping === '+PONG', 'Redis did not answer PING.');
    }
}
