<?php

namespace Tests\Feature\Exceptions;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class QueryExceptionReporterTest extends TestCase
{
    use RefreshDatabase;

    private const EMAIL = 'private.person@example.com';

    protected function setUp(): void
    {
        parent::setUp();

        User::factory()->create(['email' => self::EMAIL]);

        Route::middleware('api')->post('/api/v1/testing/duplicate', function () {
            User::create(['name' => 'Duplicate', 'email' => self::EMAIL, 'password' => 'secret-password']);
        });
    }

    public function test_a_failed_query_is_logged_without_the_bound_values(): void
    {
        config(['app.debug' => false]);
        Log::spy();

        $this->postJson('/api/v1/testing/duplicate')->assertStatus(500);

        Log::shouldHaveReceived('error')
            ->once()
            ->withArgs(function (string $message, array $context): bool {
                $logged = $message.json_encode($context);

                return $message === 'Database query failed.'
                    && isset($context['sql_state'], $context['sql'])
                    && ! str_contains($logged, 'private.person')
                    && ! str_contains($logged, 'secret-password');
            });
    }

    public function test_the_response_of_a_failed_query_does_not_expose_the_query(): void
    {
        config(['app.debug' => false]);
        Log::spy();

        $this->postJson('/api/v1/testing/duplicate')
            ->assertStatus(500)
            ->assertJsonPath('code', 'server_error')
            ->assertDontSee('private.person')
            ->assertDontSee('insert into');
    }
}
