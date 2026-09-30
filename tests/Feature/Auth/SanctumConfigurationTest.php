<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class SanctumConfigurationTest extends TestCase
{
    use RefreshDatabase;

    public function test_tokens_carry_a_prefix_that_secret_scanners_can_recognize(): void
    {
        $token = User::factory()->create()->createToken('mobile')->plainTextToken;

        $this->assertMatchesRegularExpression('/^\d+\|bdl_[A-Za-z0-9]{40}/', $token);
    }

    public function test_a_token_authenticates_for_seven_days_then_expires(): void
    {
        Route::middleware(['api', 'auth:sanctum'])->get('/api/v1/testing/me', fn () => ['ok' => true]);
        $token = User::factory()->create()->createToken('mobile')->plainTextToken;

        $this->travel(6)->days();
        $this->withToken($token)->getJson('/api/v1/testing/me')->assertOk();

        $this->travel(2)->days();
        $this->forgetResolvedUser();
        $this->withToken($token)->getJson('/api/v1/testing/me')->assertUnauthorized();
    }

    public function test_the_scheduler_prunes_expired_tokens_every_day(): void
    {
        Artisan::call('schedule:list');

        $this->assertMatchesRegularExpression(
            '/0 0 \* \* \*\s+php artisan sanctum:prune-expired --hours=24/',
            Artisan::output(),
        );
    }

    public function test_the_prune_command_removes_tokens_expired_for_more_than_a_day_only(): void
    {
        $user = User::factory()->create();
        $user->createToken('long-expired', ['*'], now()->subDays(3));
        $user->createToken('just-expired', ['*'], now()->subHours(2));
        $user->createToken('valid', ['*'], now()->addDay());

        $this->artisan('sanctum:prune-expired', ['--hours' => 24])->assertSuccessful();

        $this->assertDatabaseMissing('personal_access_tokens', ['name' => 'long-expired']);
        $this->assertDatabaseHas('personal_access_tokens', ['name' => 'just-expired']);
        $this->assertDatabaseHas('personal_access_tokens', ['name' => 'valid']);
    }

    public function test_stateful_domains_default_to_the_app_and_frontend_hosts_without_a_localhost_list(): void
    {
        $config = $this->sanctumConfigWithEnvironment([
            'APP_URL' => 'https://badal.example:8443',
            'FRONTEND_URL' => 'https://app.badal.example',
            'SANCTUM_STATEFUL_DOMAINS' => null,
        ]);

        $this->assertSame(['badal.example:8443', 'app.badal.example'], $config['stateful']);
    }

    public function test_stateful_domains_can_be_set_explicitly(): void
    {
        $config = $this->sanctumConfigWithEnvironment([
            'APP_URL' => 'https://badal.example',
            'FRONTEND_URL' => 'https://badal.example',
            'SANCTUM_STATEFUL_DOMAINS' => 'one.example, two.example:8080,',
        ]);

        $this->assertSame(['one.example', 'two.example:8080'], $config['stateful']);
    }

    /**
     * Load config/sanctum.php as the framework would with the given environment variables.
     *
     * @param  array<string, string|null>  $variables  A null value unsets the variable.
     * @return array<string, mixed>
     */
    private function sanctumConfigWithEnvironment(array $variables): array
    {
        $environment = $_ENV;
        $server = $_SERVER;

        foreach ($variables as $name => $value) {
            if ($value === null) {
                unset($_ENV[$name], $_SERVER[$name]);
            } else {
                $_ENV[$name] = $_SERVER[$name] = $value;
            }
        }

        try {
            return require config_path('sanctum.php');
        } finally {
            $_ENV = $environment;
            $_SERVER = $server;
        }
    }

    /**
     * Drop the user cached by the guard so that the next request authenticates again.
     */
    private function forgetResolvedUser(): void
    {
        $this->app['auth']->forgetGuards();
    }
}
