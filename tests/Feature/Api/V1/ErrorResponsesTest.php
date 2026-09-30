<?php

namespace Tests\Feature\Api\V1;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use RuntimeException;
use Tests\TestCase;

class ErrorResponsesTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_404_with_the_uniform_body_for_an_unknown_api_route(): void
    {
        $response = $this->getJson('/api/v1/does-not-exist');

        $this->assertUniformError($response, 404, 'not_found');
        $response->assertJsonMissingPath('errors');
    }

    public function test_returns_the_uniform_json_body_even_when_the_client_does_not_ask_for_json(): void
    {
        $response = $this->get('/api/v1/does-not-exist');

        $this->assertUniformError($response, 404, 'not_found');
    }

    public function test_returns_404_without_naming_the_model_when_a_bound_record_is_missing(): void
    {
        Route::middleware('api')->get('/api/v1/testing/users/{user}', fn (User $user) => $user->name);

        $response = $this->getJson('/api/v1/testing/users/'.Str::ulid());

        $this->assertUniformError($response, 404, 'not_found');
        $response->assertDontSee('Models')->assertDontSee('No query results');
    }

    public function test_returns_405_with_the_allowed_methods_when_the_method_is_wrong(): void
    {
        $response = $this->postJson('/api/v1/health');

        $this->assertUniformError($response, 405, 'method_not_allowed');
        $this->assertStringContainsString('GET', (string) $response->headers->get('Allow'));
    }

    public function test_returns_401_with_a_bearer_challenge_when_the_token_is_missing(): void
    {
        Route::middleware(['api', 'auth:sanctum'])->get('/api/v1/testing/protected', fn () => ['ok' => true]);

        $response = $this->getJson('/api/v1/testing/protected');

        $this->assertUniformError($response, 401, 'unauthenticated');
        $response->assertHeader('WWW-Authenticate', 'Bearer');
    }

    public function test_returns_401_with_the_uniform_body_when_the_token_is_invalid(): void
    {
        Route::middleware(['api', 'auth:sanctum'])->get('/api/v1/testing/protected', fn () => ['ok' => true]);

        $response = $this->withToken('bdl_1|not-a-real-token')->get('/api/v1/testing/protected');

        $this->assertUniformError($response, 401, 'unauthenticated');
    }

    public function test_returns_403_without_repeating_the_internal_reason(): void
    {
        Route::middleware('api')->get('/api/v1/testing/forbidden', fn () => abort(403, 'Internal reason: rule 42'));

        $response = $this->getJson('/api/v1/testing/forbidden');

        $this->assertUniformError($response, 403, 'forbidden');
        $response->assertDontSee('rule 42');
    }

    public function test_returns_422_with_the_messages_by_field(): void
    {
        Route::middleware('api')->post('/api/v1/testing/validated', function (Request $request) {
            $request->validate(['name' => ['required'], 'age' => ['integer']]);
        });

        $response = $this->postJson('/api/v1/testing/validated', ['age' => 'abc']);

        $this->assertUniformError($response, 422, 'validation_failed');
        $response
            ->assertJsonStructure(['errors' => ['name', 'age']])
            ->assertJsonValidationErrors(['name', 'age']);
    }

    public function test_returns_429_with_retry_after_when_the_rate_limit_is_exceeded(): void
    {
        Route::middleware(['api', 'throttle:2,1'])->get('/api/v1/testing/limited', fn () => ['ok' => true]);

        $this->getJson('/api/v1/testing/limited')->assertOk();
        $this->getJson('/api/v1/testing/limited')->assertOk();
        $response = $this->getJson('/api/v1/testing/limited');

        $this->assertUniformError($response, 429, 'too_many_requests');
        $this->assertGreaterThan(0, (int) $response->headers->get('Retry-After'));
    }

    public function test_returns_500_without_any_detail_when_debug_is_disabled(): void
    {
        Exceptions::fake();
        config(['app.debug' => false]);
        Route::middleware('api')->get('/api/v1/testing/crash', function (): never {
            throw new RuntimeException('secret detail: db password');
        });

        $response = $this->getJson('/api/v1/testing/crash');

        $this->assertUniformError($response, 500, 'server_error');
        $response
            ->assertJsonMissingPath('debug')
            ->assertDontSee('secret detail')
            ->assertDontSee('RuntimeException');
        Exceptions::assertReported(RuntimeException::class);
    }

    public function test_returns_500_with_debug_details_only_when_debug_is_enabled(): void
    {
        Exceptions::fake();
        config(['app.debug' => true]);
        Route::middleware('api')->get('/api/v1/testing/crash', function (): never {
            throw new RuntimeException('visible while debugging');
        });

        $response = $this->getJson('/api/v1/testing/crash');

        $this->assertUniformError($response, 500, 'server_error');
        $response
            ->assertJsonPath('debug.exception', RuntimeException::class)
            ->assertJsonPath('debug.message', 'visible while debugging');
    }

    public function test_leaves_errors_outside_the_api_to_the_default_renderer(): void
    {
        $response = $this->get('/does-not-exist');

        $response->assertNotFound();
        $this->assertStringNotContainsString('"code"', (string) $response->getContent());
    }

    /**
     * Assert the `{ message, code, errors?, request_id }` contract shared by every API error.
     */
    private function assertUniformError(TestResponse $response, int $status, string $code): void
    {
        $response
            ->assertStatus($status)
            ->assertHeader('X-Request-ID')
            ->assertJsonPath('code', $code)
            ->assertJsonStructure(['message', 'code', 'request_id']);

        $this->assertNotSame('', $response->json('message'));
        $this->assertSame($response->headers->get('X-Request-ID'), $response->json('request_id'));
    }
}
