<?php

namespace Tests\Feature;

use Illuminate\Redis\Connectors\PhpRedisConnector;
use Illuminate\Support\Env;
use Tests\TestCase;

/**
 * Locks in the Redis connection wiring so the Upstash TLS failure cannot return
 * silently: REDIS_HOST must win over REDIS_URL, and REDIS_SCHEME=tls must be the
 * value phpredis sees.
 *
 * The config file is loaded directly against a controlled environment
 * repository, because the full application re-reads .env and would overwrite
 * values set at runtime.
 */
class RedisConfigurationTest extends TestCase
{
    /**
     * @var array<string, string|null>
     */
    private array $original = [];

    /**
     * @param  array<string, string>  $env
     * @return array<string, mixed>
     */
    private function loadRedisConfig(array $env): array
    {
        $keys = ['REDIS_HOST', 'REDIS_PORT', 'REDIS_PASSWORD', 'REDIS_USERNAME', 'REDIS_SCHEME', 'REDIS_URL'];

        foreach ($keys as $key) {
            $this->original[$key] ??= Env::get($key);
        }

        foreach ($keys as $key) {
            $value = $env[$key] ?? null;

            if ($value === null) {
                Env::getRepository()->clear($key);
            } else {
                Env::getRepository()->set($key, $value);
            }
        }

        try {
            $config = require config_path('database.php');

            return $config['redis'];
        } finally {
            foreach ($keys as $key) {
                $original = $this->original[$key] ?? null;

                if ($original === null) {
                    Env::getRepository()->clear($key);
                } else {
                    Env::getRepository()->set($key, $original);
                }
            }
        }
    }

    public function test_upstash_connection_uses_tls_and_ignores_the_url(): void
    {
        $redis = $this->loadRedisConfig([
            'REDIS_HOST' => 'redis.upstash.example.com',
            'REDIS_PORT' => '6379',
            'REDIS_PASSWORD' => 'secret',
            'REDIS_USERNAME' => 'default',
            'REDIS_SCHEME' => 'tls',
            'REDIS_URL' => '',
        ]);

        foreach (['default', 'cache'] as $connection) {
            $this->assertSame('redis.upstash.example.com', $redis[$connection]['host']);
            $this->assertSame('tls', $redis[$connection]['scheme'], "{$connection} must enable TLS");
            $this->assertNull($redis[$connection]['url'], "{$connection} must not fall back to REDIS_URL");
            $this->assertSame('default', $redis[$connection]['username']);
        }

        $this->assertSame('0', $redis['default']['database']);
        $this->assertSame('1', $redis['cache']['database']);
    }

    public function test_key_value_url_is_used_when_no_host_is_set(): void
    {
        $redis = $this->loadRedisConfig([
            'REDIS_HOST' => '',
            'REDIS_PORT' => '6379',
            'REDIS_PASSWORD' => '',
            'REDIS_USERNAME' => '',
            'REDIS_SCHEME' => '',
            'REDIS_URL' => 'redis://:kvpass@10.0.0.5:6379',
        ]);

        foreach (['default', 'cache'] as $connection) {
            $this->assertSame('redis://:kvpass@10.0.0.5:6379', $redis[$connection]['url']);
        }
    }

    public function test_local_docker_connection_stays_plain_tcp(): void
    {
        $redis = $this->loadRedisConfig([
            'REDIS_HOST' => 'redis',
            'REDIS_PORT' => '6379',
            'REDIS_PASSWORD' => '',
            'REDIS_USERNAME' => '',
            'REDIS_SCHEME' => '',
            'REDIS_URL' => '',
        ]);

        $this->assertSame('redis', $redis['default']['host']);
        $this->assertNull($redis['default']['scheme'], 'local Redis must not be forced onto TLS');
        $this->assertNull($redis['default']['url']);
    }

    public function test_empty_scheme_is_not_turned_into_a_bare_scheme_prefix(): void
    {
        $redis = $this->loadRedisConfig([
            'REDIS_HOST' => 'redis',
            'REDIS_PORT' => '6379',
            'REDIS_PASSWORD' => '',
            'REDIS_USERNAME' => '',
            'REDIS_SCHEME' => '',
            'REDIS_URL' => '',
        ]);

        $connector = new PhpRedisConnector;
        $formatter = new \ReflectionMethod($connector, 'formatHost');
        $formatter->setAccessible(true);

        $target = $formatter->invoke($connector, $redis['default']);

        $this->assertSame('redis', $target);
        $this->assertStringStartsNotWith('://', $target);
    }
}
