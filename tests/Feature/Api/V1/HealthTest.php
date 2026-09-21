<?php

namespace Tests\Feature\Api\V1;

use Tests\TestCase;

class HealthTest extends TestCase
{
    public function test_health_endpoint_returns_the_versioned_service_status(): void
    {
        $response = $this->getJson('/api/v1/health');

        $response
            ->assertOk()
            ->assertHeader('X-Request-ID')
            ->assertJsonPath('data.status', 'ok')
            ->assertJsonPath('data.service', 'badal-api')
            ->assertJsonPath('data.version', 'v1')
            ->assertJsonStructure([
                'data' => [
                    'status',
                    'service',
                    'version',
                    'request_id',
                    'timestamp',
                ],
            ]);

        $this->assertSame(
            $response->headers->get('X-Request-ID'),
            $response->json('data.request_id'),
        );
    }

    public function test_cors_only_allows_the_configured_frontend_origin(): void
    {
        $allowed = $this
            ->withHeader('Origin', 'http://localhost:8098')
            ->getJson('/api/v1/health');

        $allowed->assertHeader('Access-Control-Allow-Origin', 'http://localhost:8098');

        $denied = $this
            ->withHeader('Origin', 'https://untrusted.example')
            ->getJson('/api/v1/health');

        $this->assertNotSame(
            'https://untrusted.example',
            $denied->headers->get('Access-Control-Allow-Origin'),
        );
    }
}
