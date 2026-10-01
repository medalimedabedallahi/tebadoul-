<?php

namespace Tests\Feature\Livewire\Auth;

use App\Livewire\Auth\Register;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\InteractsWithContactCodes;
use Tests\TestCase;

class RegisterTest extends TestCase
{
    use InteractsWithContactCodes;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->captureContactCodes();
    }

    public function test_registers_with_an_email_only_and_redirects_to_verification(): void
    {
        Livewire::test(Register::class)
            ->set('name', 'Aminetou Sy')
            ->set('email', 'aminetou@example.com')
            ->set('password', self::PASSWORD)
            ->set('password_confirmation', self::PASSWORD)
            ->set('accept_terms', true)
            ->call('register')
            ->assertHasNoErrors()
            ->assertRedirect(route('verification.show'));

        $user = User::query()->where('email', 'aminetou@example.com')->sole();
        $this->assertSame('Aminetou Sy', $user->name);
        $this->assertNull($user->email_verified_at);
        $this->assertSame('aminetou@example.com', session('pending_verification_contact'));
    }

    public function test_rejects_registration_with_a_phone_only(): void
    {
        Livewire::test(Register::class)
            ->set('name', 'Sidi Ahmed')
            ->set('phone', '41111111')
            ->set('password', self::PASSWORD)
            ->set('password_confirmation', self::PASSWORD)
            ->set('accept_terms', true)
            ->call('register')
            ->assertHasErrors(['email']);

        $this->assertSame(0, User::query()->count());
    }

    public function test_requires_an_email(): void
    {
        Livewire::test(Register::class)
            ->set('name', 'Sans Contact')
            ->set('password', self::PASSWORD)
            ->set('password_confirmation', self::PASSWORD)
            ->set('accept_terms', true)
            ->call('register')
            ->assertHasErrors(['email']);

        $this->assertSame(0, User::query()->count());
    }

    public function test_password_confirmation_must_match(): void
    {
        Livewire::test(Register::class)
            ->set('name', 'Aminetou Sy')
            ->set('email', 'aminetou@example.com')
            ->set('password', self::PASSWORD)
            ->set('password_confirmation', 'something-else-2026')
            ->set('accept_terms', true)
            ->call('register')
            ->assertHasErrors(['password']);

        $this->assertSame(0, User::query()->count());
    }

    public function test_a_weak_password_is_rejected(): void
    {
        Livewire::test(Register::class)
            ->set('name', 'Aminetou Sy')
            ->set('email', 'aminetou@example.com')
            ->set('password', 'short')
            ->set('password_confirmation', 'short')
            ->set('accept_terms', true)
            ->call('register')
            ->assertHasErrors(['password']);
    }

    public function test_registering_with_a_contact_already_taken_gives_the_same_outcome_as_success(): void
    {
        User::factory()->create(['email' => 'existing@example.com']);

        Livewire::test(Register::class)
            ->set('name', 'Quelqu’un')
            ->set('email', 'existing@example.com')
            ->set('password', self::PASSWORD)
            ->set('password_confirmation', self::PASSWORD)
            ->set('accept_terms', true)
            ->call('register')
            ->assertHasNoErrors()
            ->assertRedirect(route('verification.show'));

        $this->assertSame(1, User::query()->count());
    }

    public function test_the_terms_must_be_accepted_and_both_documents_are_linked(): void
    {
        Livewire::test(Register::class)
            ->assertSeeHtml('href="'.route('legal.terms').'"')
            ->assertSeeHtml('href="'.route('legal.privacy').'"')
            ->set('name', 'Aminetou Sy')
            ->set('email', 'aminetou@example.com')
            ->set('password', self::PASSWORD)
            ->set('password_confirmation', self::PASSWORD)
            ->call('register')
            ->assertHasErrors(['accept_terms' => 'accepted']);

        $this->assertSame(0, User::query()->count());
    }

    /**
     * @return array<string, array{string}>
     */
    public static function localeProvider(): array
    {
        return ['français' => ['fr'], 'arabe' => ['ar']];
    }

    #[DataProvider('localeProvider')]
    public function test_renders_in_the_given_locale(string $locale): void
    {
        app()->setLocale($locale);

        $response = $this->get(route('register'));

        $response->assertOk();
        $response->assertSee('<html lang="'.$locale.'" dir="'.($locale === 'ar' ? 'rtl' : 'ltr').'"', false);
        $response->assertSee(__('auth.register.heading', [], $locale));
    }
}
