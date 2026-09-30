<?php

namespace Tests\Feature\Http\Middleware;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class EnsureIdempotencyTest extends TestCase
{
    use RefreshDatabase;

    private const KEY = 'order-2026-0001';

    private int $executions = 0;

    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware(['api', 'idempotent'])->post('/api/v1/testing/orders', function (Request $request) {
            $this->executions++;

            return response()->json(['data' => ['execution' => $this->executions]], 201);
        });
    }

    public function test_replays_the_stored_response_without_running_the_action_again(): void
    {
        $first = $this->withHeader('Idempotency-Key', self::KEY)->postJson('/api/v1/testing/orders', ['item' => 'a']);
        $second = $this->withHeader('Idempotency-Key', self::KEY)->postJson('/api/v1/testing/orders', ['item' => 'a']);

        $first->assertCreated()->assertHeaderMissing('Idempotent-Replayed');
        $second
            ->assertCreated()
            ->assertHeader('Idempotent-Replayed', 'true')
            ->assertJsonPath('data.execution', 1);
        $this->assertSame(1, $this->executions);
    }

    public function test_runs_every_request_when_there_is_no_key(): void
    {
        $this->postJson('/api/v1/testing/orders', ['item' => 'a'])->assertCreated();
        $this->postJson('/api/v1/testing/orders', ['item' => 'a'])->assertCreated();

        $this->assertSame(2, $this->executions);
    }

    public function test_rejects_a_reused_key_with_another_payload_with_422(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $this->withHeader('Idempotency-Key', self::KEY)->postJson('/api/v1/testing/orders', ['item' => 'a'])->assertCreated();

        $this->withHeader('Idempotency-Key', self::KEY)
            ->postJson('/api/v1/testing/orders', ['item' => 'b'])
            ->assertUnprocessable()
            ->assertJsonPath('code', 'idempotency_key_reused');
        $this->assertSame(1, $this->executions);
    }

    public function test_rejects_a_malformed_key_with_400(): void
    {
        foreach (['short', 'has spaces in it', str_repeat('a', 129), 'bad/char/in/key'] as $key) {
            $this->withHeader('Idempotency-Key', $key)
                ->postJson('/api/v1/testing/orders', ['item' => 'a'])
                ->assertBadRequest()
                ->assertJsonPath('code', 'idempotency_key_invalid');
        }

        $this->assertSame(0, $this->executions);
    }

    public function test_answers_409_while_the_first_request_with_the_same_key_is_still_running(): void
    {
        $concurrent = null;

        Route::middleware(['api', 'idempotent'])->post('/api/v1/testing/slow', function () use (&$concurrent) {
            $this->executions++;

            $concurrent = $this->withHeader('Idempotency-Key', self::KEY)->postJson('/api/v1/testing/slow', ['item' => 'a']);

            return response()->json(['ok' => true], 201);
        });

        $this->withHeader('Idempotency-Key', self::KEY)->postJson('/api/v1/testing/slow', ['item' => 'a'])->assertCreated();

        $concurrent->assertStatus(409)
            ->assertHeader('Retry-After', '1')
            ->assertJsonPath('code', 'idempotency_request_in_progress');
        $this->assertSame(1, $this->executions);
    }

    public function test_does_not_store_a_failed_response_so_the_client_can_retry(): void
    {
        Route::middleware(['api', 'idempotent'])->post('/api/v1/testing/flaky', function () {
            $this->executions++;

            return $this->executions === 1
                ? response()->json(['message' => 'try later'], 503)
                : response()->json(['ok' => true], 201);
        });

        $this->withHeader('Idempotency-Key', self::KEY)->postJson('/api/v1/testing/flaky')->assertStatus(503);
        $retry = $this->withHeader('Idempotency-Key', self::KEY)->postJson('/api/v1/testing/flaky');

        $retry->assertCreated()->assertHeaderMissing('Idempotent-Replayed');
        $this->assertSame(2, $this->executions);
    }

    public function test_keeps_the_keys_of_two_users_apart(): void
    {
        $alice = User::factory()->create();
        $bruno = User::factory()->create();

        Sanctum::actingAs($alice);
        $this->withHeader('Idempotency-Key', self::KEY)->postJson('/api/v1/testing/orders', ['item' => 'a'])->assertCreated();

        Sanctum::actingAs($bruno);
        $this->withHeader('Idempotency-Key', self::KEY)
            ->postJson('/api/v1/testing/orders', ['item' => 'a'])
            ->assertCreated()
            ->assertHeaderMissing('Idempotent-Replayed');

        $this->assertSame(2, $this->executions);
    }

    public function test_keeps_the_keys_of_two_endpoints_apart(): void
    {
        Route::middleware(['api', 'idempotent'])->post('/api/v1/testing/refunds', function () {
            $this->executions++;

            return response()->json(['ok' => true], 201);
        });

        $this->withHeader('Idempotency-Key', self::KEY)->postJson('/api/v1/testing/orders', ['item' => 'a'])->assertCreated();
        $this->withHeader('Idempotency-Key', self::KEY)
            ->postJson('/api/v1/testing/refunds', ['item' => 'a'])
            ->assertCreated()
            ->assertHeaderMissing('Idempotent-Replayed');

        $this->assertSame(2, $this->executions);
    }

    public function test_a_guest_key_is_scoped_to_the_payload_so_another_payload_is_another_request(): void
    {
        $this->withHeader('Idempotency-Key', self::KEY)->postJson('/api/v1/testing/orders', ['item' => 'a'])->assertCreated();

        $this->withHeader('Idempotency-Key', self::KEY)
            ->postJson('/api/v1/testing/orders', ['item' => 'b'])
            ->assertCreated()
            ->assertHeaderMissing('Idempotent-Replayed');
        $this->withHeader('Idempotency-Key', self::KEY)
            ->postJson('/api/v1/testing/orders', ['item' => 'a'])
            ->assertHeader('Idempotent-Replayed', 'true');

        $this->assertSame(2, $this->executions);
    }

    public function test_a_guest_key_is_scoped_to_the_ip_address(): void
    {
        $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.1'])
            ->withHeader('Idempotency-Key', self::KEY)
            ->postJson('/api/v1/testing/orders', ['item' => 'a'])
            ->assertCreated();

        $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.2'])
            ->withHeader('Idempotency-Key', self::KEY)
            ->postJson('/api/v1/testing/orders', ['item' => 'a'])
            ->assertCreated()
            ->assertHeaderMissing('Idempotent-Replayed');

        $this->assertSame(2, $this->executions);
    }

    public function test_secret_fields_do_not_feed_the_fingerprint_nor_the_stored_keys(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->withHeader('Idempotency-Key', self::KEY)
            ->postJson('/api/v1/testing/orders', ['item' => 'a', 'password' => 'first-secret-value', 'code' => '111111'])
            ->assertCreated();

        // Only the secret fields differ: same fingerprint, so a replay rather than a 422.
        $this->withHeader('Idempotency-Key', self::KEY)
            ->postJson('/api/v1/testing/orders', ['item' => 'a', 'password' => 'other-secret-value', 'code' => '222222', 'current_password' => 'x', 'password_confirmation' => 'y'])
            ->assertCreated()
            ->assertHeader('Idempotent-Replayed', 'true');

        $this->assertSame(1, $this->executions);
    }

    public function test_the_fingerprint_ignores_the_order_of_the_fields(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->withHeader('Idempotency-Key', self::KEY)->postJson('/api/v1/testing/orders', ['item' => 'a', 'qty' => 2])->assertCreated();
        $this->withHeader('Idempotency-Key', self::KEY)
            ->postJson('/api/v1/testing/orders', ['qty' => 2, 'item' => 'a'])
            ->assertHeader('Idempotent-Replayed', 'true');

        $this->assertSame(1, $this->executions);
    }

    public function test_never_stores_a_response_that_carries_a_token(): void
    {
        foreach (['/api/v1/testing/token-field' => ['data' => ['token' => 'opaque-value']], '/api/v1/testing/token-prefix' => ['data' => ['value' => '1|bdl_secret']]] as $uri => $body) {
            Route::middleware(['api', 'idempotent'])->post($uri, function () use ($body) {
                $this->executions++;

                return response()->json($body, 201);
            });
        }

        foreach (['/api/v1/testing/token-field', '/api/v1/testing/token-prefix'] as $uri) {
            $this->withHeader('Idempotency-Key', self::KEY)->postJson($uri, ['item' => 'a'])->assertCreated();
            $this->withHeader('Idempotency-Key', self::KEY)
                ->postJson($uri, ['item' => 'a'])
                ->assertCreated()
                ->assertHeaderMissing('Idempotent-Replayed');
        }

        $this->assertSame(4, $this->executions);
    }

    public function test_the_sign_in_route_is_never_idempotent_even_with_a_key(): void
    {
        Route::middleware(['api', 'idempotent'])->post('/api/v1/testing/login', fn () => response()->json(['ok' => true], 201))
            ->name('api.v1.auth.login');

        $this->withHeader('Idempotency-Key', self::KEY)->postJson('/api/v1/testing/login')->assertCreated();
        $this->withHeader('Idempotency-Key', self::KEY)->postJson('/api/v1/testing/login')->assertHeaderMissing('Idempotent-Replayed');
    }

    public function test_keys_are_stored_in_the_limiter_store(): void
    {
        config(['cache.stores.never-evicted' => ['driver' => 'array'], 'cache.limiter' => 'never-evicted']);

        $this->withHeader('Idempotency-Key', self::KEY)->postJson('/api/v1/testing/orders', ['item' => 'a'])->assertCreated();

        // Clearing the default (evictable) cache does not lose the key...
        Cache::store()->flush();
        $this->withHeader('Idempotency-Key', self::KEY)->postJson('/api/v1/testing/orders', ['item' => 'a'])->assertHeader('Idempotent-Replayed', 'true');

        // ...because it lives in the limiter store.
        Cache::store('never-evicted')->flush();
        $this->withHeader('Idempotency-Key', self::KEY)->postJson('/api/v1/testing/orders', ['item' => 'a'])->assertHeaderMissing('Idempotent-Replayed');

        $this->assertSame(2, $this->executions);
    }

    public function test_ignores_the_key_on_methods_other_than_post(): void
    {
        Route::middleware(['api', 'idempotent'])->get('/api/v1/testing/read', function () {
            $this->executions++;

            return response()->json(['ok' => true]);
        });

        $this->withHeader('Idempotency-Key', self::KEY)->getJson('/api/v1/testing/read')->assertOk();
        $this->withHeader('Idempotency-Key', self::KEY)->getJson('/api/v1/testing/read')->assertOk();

        $this->assertSame(2, $this->executions);
    }
}
