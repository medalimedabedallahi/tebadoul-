<?php

namespace Tests\Feature\Api\V1;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Tests\TestCase;

class RequestIdTest extends TestCase
{
    public function test_every_api_response_carries_a_new_server_generated_request_id(): void
    {
        $first = $this->getJson('/api/v1/health');
        $second = $this->getJson('/api/v1/health');

        $this->assertTrue(Str::isUuid((string) $first->headers->get('X-Request-ID')));
        $this->assertNotSame($first->headers->get('X-Request-ID'), $second->headers->get('X-Request-ID'));
    }

    public function test_an_unknown_route_still_carries_a_request_id(): void
    {
        $response = $this->getJson('/api/v1/does-not-exist');

        $response->assertNotFound();
        $this->assertTrue(Str::isUuid((string) $response->headers->get('X-Request-ID')));
    }

    public function test_a_request_id_sent_by_the_client_is_ignored(): void
    {
        $response = $this->withHeader('X-Request-ID', 'forged-id')->getJson('/api/v1/health');

        $this->assertNotSame('forged-id', $response->headers->get('X-Request-ID'));
    }

    public function test_web_responses_carry_a_request_id_too(): void
    {
        $this->get('/up')->assertHeader('X-Request-ID');
    }

    public function test_the_request_id_is_shared_with_the_log_context(): void
    {
        $response = $this->getJson('/api/v1/health');

        $this->assertSame(
            $response->headers->get('X-Request-ID'),
            Log::sharedContext()['request_id'] ?? null,
        );
    }
}
