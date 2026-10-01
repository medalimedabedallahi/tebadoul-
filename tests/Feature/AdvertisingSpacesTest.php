<?php

namespace Tests\Feature;

use App\Enums\AdPlacement;
use App\Models\Advertisement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The layout shows the advertisement of the page's placement, plus the footer one, always labelled
 * as advertising, with a sponsored link and the title as the banner's alternative text.
 */
class AdvertisingSpacesTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_home_page_shows_its_advertisement_and_the_footer_one(): void
    {
        $home = Advertisement::factory()->placement(AdPlacement::Home)->create(['title' => 'Bannière accueil', 'link_url' => 'https://example.org/accueil']);
        $footer = Advertisement::factory()->placement(AdPlacement::Footer)->create(['title' => 'Bannière pied', 'link_url' => null]);
        $member = Advertisement::factory()->placement(AdPlacement::Member)->create();

        $this->get(route('home'))
            ->assertOk()
            ->assertSee(__('common.advertisement.label'))
            ->assertSee('data-advertisement="'.$home->public_id.'"', false)
            ->assertSee('alt="Bannière accueil"', false)
            ->assertSee('rel="sponsored noopener noreferrer"', false)
            ->assertSee('data-advertisement="'.$footer->public_id.'"', false)
            ->assertDontSee('data-advertisement="'.$member->public_id.'"', false);
    }

    public function test_sign_in_and_member_pages_show_their_own_placement(): void
    {
        $auth = Advertisement::factory()->placement(AdPlacement::Auth)->create();
        $member = Advertisement::factory()->placement(AdPlacement::Member)->create();

        $this->get(route('login'))
            ->assertSee('data-advertisement="'.$auth->public_id.'"', false)
            ->assertDontSee('data-advertisement="'.$member->public_id.'"', false);

        $this->actingAs(User::factory()->create())->get(route('notifications.index'))
            ->assertOk()
            ->assertSee('data-advertisement="'.$member->public_id.'"', false)
            ->assertDontSee('data-advertisement="'.$auth->public_id.'"', false);
    }

    public function test_nothing_is_shown_without_a_displayable_advertisement_for_the_language(): void
    {
        $french = Advertisement::factory()->placement(AdPlacement::Home)->create(['locale' => 'fr']);
        $hidden = Advertisement::factory()->placement(AdPlacement::Footer)->inactive()->create();

        $this->withSession(['locale' => 'ar'])->get(route('home'))
            ->assertOk()
            ->assertDontSee('data-advertisement="'.$french->public_id.'"', false)
            ->assertDontSee('data-advertisement="'.$hidden->public_id.'"', false)
            ->assertDontSee(__('common.advertisement.label', locale: 'ar'));
    }
}
