<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Part of the pilot gate on French, Arabic and RTL: web error pages use the Tebadoul layout in the
 * visitor's language instead of the framework's English pages.
 */
class ErrorPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_unknown_page_answers_a_french_404_page(): void
    {
        $this->get('/page-qui-n-existe-pas')
            ->assertNotFound()
            ->assertSee('<html lang="fr" dir="ltr">', false)
            ->assertSee('Page introuvable')
            ->assertSee('Retour à l’accueil')
            ->assertDontSee('Not Found');
    }

    public function test_an_unknown_page_keeps_the_language_chosen_by_the_visitor(): void
    {
        $this->withSession(['locale' => 'ar'])
            ->get('/page-qui-n-existe-pas')
            ->assertNotFound()
            ->assertSee('<html lang="ar" dir="rtl">', false)
            ->assertSee('الصفحة غير موجودة');
    }

    public function test_an_unknown_api_path_still_answers_json(): void
    {
        $this->getJson('/api/v1/inconnu')
            ->assertNotFound()
            ->assertJsonPath('code', 'not_found');
    }

    public function test_a_refused_page_answers_in_arabic_right_to_left(): void
    {
        $this->actingAs(User::factory()->create())
            ->withSession(['locale' => 'ar'])
            ->get(route('admin.statistics.show'))
            ->assertForbidden()
            ->assertSee('<html lang="ar" dir="rtl">', false)
            ->assertSee('الوصول مرفوض')
            ->assertDontSee('Forbidden');
    }

    public function test_a_code_without_its_own_text_falls_back_to_the_generic_message(): void
    {
        $this->app['router']->get('/testing/payment-required', fn () => abort(402));

        $this->get('/testing/payment-required')
            ->assertStatus(402)
            ->assertSee('Une erreur est survenue')
            ->assertSee('402');
    }
}
