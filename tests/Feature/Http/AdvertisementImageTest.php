<?php

namespace Tests\Feature\Http;

use App\Models\Advertisement;
use App\Models\User;
use Database\Factories\AdvertisementFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdvertisementImageTest extends TestCase
{
    use RefreshDatabase;

    public function test_anyone_loads_the_banner_of_a_displayable_advertisement_cached_for_good(): void
    {
        $advertisement = Advertisement::factory()->create();

        $response = $this->get($advertisement->imageUrl());

        $response->assertOk()
            ->assertHeader('Content-Type', 'image/png')
            ->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->assertStringContainsString('immutable', (string) $response->headers->get('Cache-Control'));
        $this->assertSame(base64_decode(AdvertisementFactory::PNG_BASE64, true), $response->getContent());
    }

    public function test_the_banner_of_a_hidden_advertisement_is_only_previewed_by_administrators(): void
    {
        $advertisement = Advertisement::factory()->inactive()->create();

        $this->get($advertisement->imageUrl())->assertNotFound();
        $this->actingAs(User::factory()->moderator()->create())->get($advertisement->imageUrl())->assertNotFound();

        $response = $this->actingAs(User::factory()->administrator()->create())->get($advertisement->imageUrl());

        $response->assertOk();
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
    }
}
