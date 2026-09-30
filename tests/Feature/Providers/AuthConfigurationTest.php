<?php

namespace Tests\Feature\Providers;

use App\Exceptions\InvalidConfigurationException;
use App\Providers\AppServiceProvider;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;
use Tests\TestCase;

class AuthConfigurationTest extends TestCase
{
    public function test_refuses_to_boot_with_an_unknown_sms_driver(): void
    {
        config(['services.sms.driver' => 'twilio']);

        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('SMS_DRIVER must be one of [log, null]');

        (new AppServiceProvider($this->app))->boot();
    }

    public function test_refuses_to_boot_in_production_with_an_unknown_sms_driver(): void
    {
        $this->app->detectEnvironment(fn (): string => 'production');
        config(['app.debug' => false, 'mail.default' => 'smtp', 'services.sms.driver' => 'not-configured']);

        $this->expectException(InvalidConfigurationException::class);

        (new AppServiceProvider($this->app))->boot();
    }

    public function test_refuses_to_boot_without_an_sms_driver(): void
    {
        config(['services.sms.driver' => null]);

        $this->expectException(InvalidConfigurationException::class);

        (new AppServiceProvider($this->app))->boot();
    }

    public function test_boots_with_each_known_sms_driver(): void
    {
        foreach (['log', 'null'] as $driver) {
            config(['services.sms.driver' => $driver]);

            (new AppServiceProvider($this->app))->boot();
        }

        $this->addToAssertionCount(1);
    }

    public function test_the_default_password_policy_requires_twelve_characters_without_network_access(): void
    {
        Http::preventStrayRequests();

        $this->assertTrue(Validator::make(['password' => 'twelve-chars'], ['password' => Password::defaults()])->passes());
        $this->assertTrue(Validator::make(['password' => 'eleven-char'], ['password' => Password::defaults()])->fails());
    }

    public function test_in_production_the_default_password_policy_also_rejects_breached_passwords(): void
    {
        $this->app->detectEnvironment(fn (): string => 'production');
        $password = 'correct-horse-battery';
        $sha1 = strtoupper(sha1($password));
        Http::fake(['api.pwnedpasswords.com/range/*' => Http::response(substr($sha1, 5).':9999', 200)]);

        $validator = Validator::make(['password' => $password], ['password' => Password::defaults()]);

        $this->assertTrue($validator->fails());
        Http::assertSentCount(1);
    }

    public function test_in_production_a_password_absent_from_breaches_is_accepted(): void
    {
        $this->app->detectEnvironment(fn (): string => 'production');
        Http::fake(['api.pwnedpasswords.com/range/*' => Http::response('0000000000000000000000000000000000A:1', 200)]);

        $this->assertTrue(Validator::make(['password' => 'correct-horse-battery'], ['password' => Password::defaults()])->passes());
    }

    public function test_the_cache_redis_connection_uses_the_dedicated_instance_when_it_is_configured(): void
    {
        $redis = $this->redisConfigWith([
            'REDIS_HOST' => 'redis-queue',
            'REDIS_PASSWORD' => 'queue-secret',
            'REDIS_CACHE_HOST' => 'redis-cache',
            'REDIS_CACHE_PASSWORD' => 'cache-secret',
        ]);

        $this->assertSame('redis-queue', $redis['default']['host']);
        $this->assertSame('queue-secret', $redis['default']['password']);
        $this->assertSame('redis-cache', $redis['cache']['host']);
        $this->assertSame('cache-secret', $redis['cache']['password']);
    }

    public function test_the_cache_redis_connection_falls_back_to_the_default_instance(): void
    {
        $redis = $this->redisConfigWith([
            'REDIS_HOST' => 'redis',
            'REDIS_PASSWORD' => 'dev-secret',
            'REDIS_CACHE_HOST' => null,
            'REDIS_CACHE_PASSWORD' => null,
        ]);

        $this->assertSame('redis', $redis['cache']['host']);
        $this->assertSame('dev-secret', $redis['cache']['password']);
        $this->assertSame('1', (string) $redis['cache']['database']);
        $this->assertSame('0', (string) $redis['default']['database']);
    }

    /**
     * The `database.redis` array that config/database.php builds for the given environment variables.
     *
     * @param  array<string, string|null>  $variables  A null value removes the variable.
     * @return array<string, mixed>
     */
    private function redisConfigWith(array $variables): array
    {
        $previous = [];

        foreach ($variables as $name => $value) {
            $previous[$name] = getenv($name);
            $value === null ? putenv($name) : putenv("$name=$value");
            unset($_ENV[$name], $_SERVER[$name]);
        }

        try {
            return (require config_path('database.php'))['redis'];
        } finally {
            foreach ($previous as $name => $value) {
                $value === false ? putenv($name) : putenv("$name=$value");
            }
        }
    }
}
