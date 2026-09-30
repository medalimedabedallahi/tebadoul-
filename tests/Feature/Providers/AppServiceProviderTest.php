<?php

namespace Tests\Feature\Providers;

use App\Exceptions\InsecureConfigurationException;
use App\Providers\AppServiceProvider;
use Illuminate\Support\Facades\RateLimiter;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AppServiceProviderTest extends TestCase
{
    /**
     * A configuration that is safe to expose: no debug, real mail and SMS delivery.
     *
     * @var array<string, mixed>
     */
    private const SAFE = [
        'app.debug' => false,
        'mail.default' => 'smtp',
        'services.sms.driver' => 'null',
        'services.sms.allow_log_driver' => false,
    ];

    /**
     * @return array<string, array{0: string}>
     */
    public static function exposedEnvironments(): array
    {
        return ['production' => ['production'], 'staging' => ['staging']];
    }

    /**
     * @return array<string, array{0: string, 1: array<string, mixed>, 2: string}>
     */
    public static function insecureConfigurations(): array
    {
        $cases = [];

        foreach (['production', 'staging'] as $environment) {
            $cases["$environment, debug enabled"] = [$environment, ['app.debug' => true], 'APP_DEBUG must be false'];
            $cases["$environment, SMS log driver without override"] = [$environment, ['services.sms.driver' => 'log'], 'SMS_DRIVER=log'];
            $cases["$environment, log mailer"] = [$environment, ['mail.default' => 'log'], 'MAIL_MAILER must not be'];
            $cases["$environment, array mailer"] = [$environment, ['mail.default' => 'array'], 'MAIL_MAILER must not be'];
        }

        return $cases;
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    #[DataProvider('insecureConfigurations')]
    public function test_refuses_to_boot_an_exposed_environment_with_an_insecure_setting(string $environment, array $overrides, string $message): void
    {
        $this->app->detectEnvironment(fn (): string => $environment);
        config([...self::SAFE, ...$overrides]);

        $this->expectException(InsecureConfigurationException::class);
        $this->expectExceptionMessage($message);

        (new AppServiceProvider($this->app))->boot();
    }

    #[DataProvider('exposedEnvironments')]
    public function test_boots_an_exposed_environment_with_a_safe_configuration(string $environment): void
    {
        $this->app->detectEnvironment(fn (): string => $environment);
        config(self::SAFE);

        (new AppServiceProvider($this->app))->boot();

        $this->assertNotNull(RateLimiter::limiter('login'));
    }

    public function test_the_sms_log_driver_is_allowed_in_staging_with_the_explicit_override(): void
    {
        $this->app->detectEnvironment(fn (): string => 'staging');
        config([...self::SAFE, 'services.sms.driver' => 'log', 'services.sms.allow_log_driver' => true]);

        (new AppServiceProvider($this->app))->boot();

        $this->assertNotNull(RateLimiter::limiter('otp-send'));
    }

    public function test_allows_debug_and_non_delivering_drivers_outside_exposed_environments(): void
    {
        $this->app->detectEnvironment(fn (): string => 'local');
        config(['app.debug' => true, 'mail.default' => 'log', 'services.sms.driver' => 'log']);

        (new AppServiceProvider($this->app))->boot();

        $this->assertNotNull(RateLimiter::limiter('api'));
    }
}
