<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class HomePageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    private function normalizedHtml(string $html): string
    {
        return preg_replace('/\s+/', ' ', $html) ?? $html;
    }

    /**
     * @return array<string, array{string, string, string, string}>
     */
    public static function localeProvider(): array
    {
        return [
            'français' => ['fr', 'ltr', 'Aller au contenu principal', 'Accueil'],
            'arabe' => ['ar', 'rtl', 'انتقل إلى المحتوى الرئيسي', 'الرئيسية'],
        ];
    }

    #[DataProvider('localeProvider')]
    public function test_home_page_exposes_language_and_direction(string $locale, string $direction, string $skipLabel, string $title): void
    {
        app()->setLocale($locale);

        $response = $this->withSession(['locale' => $locale])->get(route('home'));

        $response->assertOk();
        $response->assertSee('<html lang="'.$locale.'" dir="'.$direction.'"', false);
        $response->assertSee($skipLabel);
        $response->assertSee('<title>'.$title.' | '.config('app.name').'</title>', false);
    }

    public function test_home_page_has_skip_link_and_landmarks(): void
    {
        app()->setLocale('fr');

        $response = $this->get(route('home'));

        $response->assertOk();
        $html = $this->normalizedHtml($response->getContent());

        $this->assertStringContainsString('<a href="#main-content"', $html);
        $this->assertStringContainsString('<main id="main-content"', $html);
        $this->assertStringContainsString('<header', $html);
        $this->assertStringContainsString('<nav aria-label="Navigation principale"', $html);
        $this->assertStringContainsString('<footer', $html);
        $this->assertSame(1, substr_count($html, '<h1'));
    }

    public function test_home_page_offers_a_language_switcher_for_each_supported_locale(): void
    {
        app()->setLocale('fr');

        $response = $this->get(route('home'));

        $html = $this->normalizedHtml($response->getContent());

        $this->assertStringContainsString('action="'.route('locale.switch').'"', $html);
        $this->assertStringContainsString('role="group" aria-label="Choisir la langue"', $html);
        $this->assertStringContainsString('name="locale" value="fr" lang="fr" aria-current="true"', $html);
        $this->assertStringContainsString('name="locale" value="ar" lang="ar" class=', $html);
        $this->assertStringContainsString('العربية', $html);
    }

    public function test_home_page_marks_the_current_language_in_arabic(): void
    {
        app()->setLocale('ar');

        $response = $this->withSession(['locale' => 'ar'])->get(route('home'));

        $html = $this->normalizedHtml($response->getContent());

        $this->assertStringContainsString('name="locale" value="ar" lang="ar" aria-current="true"', $html);
        $this->assertStringNotContainsString('name="locale" value="fr" lang="fr" aria-current="true"', $html);
    }

    public function test_home_page_links_to_login_and_register_for_a_guest(): void
    {
        app()->setLocale('fr');

        $response = $this->get(route('home'));

        $response->assertSee('href="'.route('login').'"', false);
        $response->assertSee('href="'.route('register').'"', false);
        $response->assertDontSee('/dashboard', false);
    }

    public function test_home_page_links_to_account_and_offers_logout_for_a_signed_in_visitor(): void
    {
        app()->setLocale('fr');
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('home'));

        $response->assertSee('href="'.route('account.show').'"', false);
        $response->assertDontSee('href="'.route('login').'"', false);
    }
}
