<?php

namespace Tests\Feature;

use Tests\TestCase;

class SwitchLocaleTest extends TestCase
{
    public function test_supported_locale_is_stored_in_session_and_cookie(): void
    {
        $response = $this->from(route('home'))->post(route('locale.switch'), ['locale' => 'ar']);

        $response->assertRedirect(route('home'));
        $response->assertSessionHas('locale', 'ar');
        $response->assertCookie('locale', 'ar');
    }

    public function test_locale_cookie_lasts_one_year(): void
    {
        $response = $this->post(route('locale.switch'), ['locale' => 'fr']);

        $cookie = $response->getCookie('locale');

        $this->assertNotNull($cookie);
        $this->assertEqualsWithDelta(time() + 60 * 60 * 24 * 365, $cookie->getExpiresTime(), 60);
    }

    public function test_redirects_to_home_without_previous_page(): void
    {
        $response = $this->post(route('locale.switch'), ['locale' => 'fr']);

        $response->assertRedirect(route('home'));
    }

    public function test_unsupported_locale_is_rejected_without_changing_anything(): void
    {
        $currentLocale = app()->getLocale();

        $response = $this->from(route('home'))->post(route('locale.switch'), ['locale' => 'xx']);

        $response->assertRedirect(route('home'));
        $response->assertSessionHasErrors('locale');
        $response->assertSessionMissing('locale');
        $response->assertCookieMissing('locale');
        $this->assertSame($currentLocale, app()->getLocale());
    }

    public function test_missing_locale_is_rejected(): void
    {
        $response = $this->from(route('home'))->post(route('locale.switch'), []);

        $response->assertSessionHasErrors('locale');
        $response->assertSessionMissing('locale');
        $response->assertCookieMissing('locale');
    }

    public function test_unsupported_locale_returns_422_for_json_clients(): void
    {
        $response = $this->postJson(route('locale.switch'), ['locale' => 'en']);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors('locale');
        $response->assertCookieMissing('locale');
    }

    public function test_locale_list_is_read_from_configuration(): void
    {
        config(['app.supported_locales' => ['fr']]);

        $response = $this->post(route('locale.switch'), ['locale' => 'ar']);

        $response->assertSessionHasErrors('locale');
        $response->assertSessionMissing('locale');
    }
}
