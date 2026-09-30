<?php

namespace Tests\Feature\Http\Middleware;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class SetLocaleTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['app.locale' => 'fr', 'app.supported_locales' => ['fr', 'ar']]);

        Route::middleware('web')->get('/testing/locale', fn () => app()->getLocale());
        Route::middleware('api')->get('/api/v1/testing/locale', fn () => response()->json(['locale' => app()->getLocale()]));
    }

    public function test_the_session_locale_wins_over_the_cookie_and_the_header(): void
    {
        $response = $this
            ->withSession(['locale' => 'ar'])
            ->withCookie('locale', 'fr')
            ->withHeader('Accept-Language', 'fr')
            ->get('/testing/locale');

        $response->assertContent('ar');
        $response->assertHeader('Content-Language', 'ar');
    }

    public function test_the_cookie_locale_wins_over_the_header_when_the_session_has_none(): void
    {
        $this
            ->withCookie('locale', 'ar')
            ->withHeader('Accept-Language', 'fr')
            ->get('/testing/locale')
            ->assertContent('ar');
    }

    public function test_the_accept_language_header_is_used_when_there_is_no_session_or_cookie(): void
    {
        $this->withHeader('Accept-Language', 'ar')->get('/testing/locale')->assertContent('ar');
    }

    public function test_falls_back_to_the_configured_locale_when_nothing_is_supplied(): void
    {
        config(['app.locale' => 'ar']);

        $this->get('/testing/locale')->assertContent('ar');
    }

    public function test_skips_unsupported_candidates_instead_of_trusting_them(): void
    {
        $this
            ->withSession(['locale' => 'de'])
            ->withCookie('locale', '../../etc')
            ->withHeader('Accept-Language', 'de, en;q=0.9, ar;q=0.5')
            ->get('/testing/locale')
            ->assertContent('ar');
    }

    public function test_falls_back_to_the_configured_locale_when_no_candidate_is_supported(): void
    {
        config(['app.locale' => 'ar']);

        $this
            ->withHeader('Accept-Language', 'de, en;q=0.9')
            ->get('/testing/locale')
            ->assertContent('ar');
    }

    public function test_a_regional_accept_language_matches_its_primary_language(): void
    {
        config(['app.locale' => 'ar']);

        $this->withHeader('Accept-Language', 'fr-FR,fr;q=0.9')->get('/testing/locale')->assertContent('fr');
    }

    public function test_the_accept_language_quality_values_decide_the_order(): void
    {
        config(['app.locale' => 'fr']);

        $this->withHeader('Accept-Language', 'fr;q=0.4, ar;q=0.9')->get('/testing/locale')->assertContent('ar');
    }

    public function test_the_locale_of_one_request_does_not_leak_into_the_next(): void
    {
        $this->withHeader('Accept-Language', 'ar')->get('/testing/locale')->assertContent('ar');

        $this->withoutHeader('Accept-Language')->get('/testing/locale')->assertContent('fr');
    }

    public function test_the_api_group_resolves_the_locale_from_the_accept_language_header(): void
    {
        $this
            ->withHeader('Accept-Language', 'ar')
            ->getJson('/api/v1/testing/locale')
            ->assertJsonPath('locale', 'ar')
            ->assertHeader('Content-Language', 'ar');
    }
}
