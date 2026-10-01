<?php

namespace Tests\Feature\Actions\Advertising;

use App\Actions\Advertising\ListAdvertisements;
use App\Actions\Advertising\PickAdvertisement;
use App\Enums\AdPlacement;
use App\Models\Advertisement;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PickAdvertisementTest extends TestCase
{
    use RefreshDatabase;

    public function test_picks_only_a_displayable_advertisement_of_the_placement_and_language(): void
    {
        $this->travelTo('2026-10-15 12:00:00');
        $shown = Advertisement::factory()->placement(AdPlacement::Member)->create(['locale' => 'ar']);
        Advertisement::factory()->placement(AdPlacement::Member)->inactive()->create();
        Advertisement::factory()->placement(AdPlacement::Member)->create(['starts_at' => '2026-10-16 00:00:00']);
        Advertisement::factory()->placement(AdPlacement::Member)->create(['ends_at' => '2026-10-15 12:00:00']);
        Advertisement::factory()->placement(AdPlacement::Member)->create(['locale' => 'fr']);
        Advertisement::factory()->placement(AdPlacement::Home)->create();

        $picked = app(PickAdvertisement::class)->handle(AdPlacement::Member, 'ar');

        $this->assertSame($shown->id, $picked?->id);
        $this->assertNull(app(PickAdvertisement::class)->handle(AdPlacement::Footer, 'ar'));
    }

    public function test_an_advertisement_for_every_language_is_shown_in_both(): void
    {
        $advertisement = Advertisement::factory()->placement(AdPlacement::Footer)->create(['locale' => null]);

        $this->assertSame($advertisement->id, app(PickAdvertisement::class)->handle(AdPlacement::Footer, 'fr')?->id);
        $this->assertSame($advertisement->id, app(PickAdvertisement::class)->handle(AdPlacement::Footer, 'ar')?->id);
    }

    public function test_only_administrators_list_every_advertisement(): void
    {
        Advertisement::factory()->inactive()->create();
        Advertisement::factory()->create();

        $this->assertSame(2, app(ListAdvertisements::class)->handle(User::factory()->administrator()->create())->total());

        $this->expectException(AuthorizationException::class);
        app(ListAdvertisements::class)->handle(User::factory()->moderator()->create());
    }
}
