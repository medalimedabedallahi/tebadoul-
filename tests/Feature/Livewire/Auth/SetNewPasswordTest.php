<?php

namespace Tests\Feature\Livewire\Auth;

use App\Actions\Auth\RequestPasswordReset;
use App\Livewire\Auth\SetNewPassword;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\PersonalAccessToken;
use Livewire\Livewire;
use Tests\Support\InteractsWithContactCodes;
use Tests\TestCase;

class SetNewPasswordTest extends TestCase
{
    use InteractsWithContactCodes;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->captureContactCodes();
    }

    public function test_prefills_the_contact_from_the_session(): void
    {
        session(['pending_password_reset_contact' => 'aminetou@example.com']);

        Livewire::test(SetNewPassword::class)
            ->assertSet('contact', 'aminetou@example.com');
    }

    public function test_resets_the_password_with_the_correct_code_and_redirects_to_login(): void
    {
        $user = User::factory()->create(['email' => 'aminetou@example.com', 'password' => self::PASSWORD]);
        app(RequestPasswordReset::class)->handle('aminetou@example.com');
        $code = $this->lastCode();

        Livewire::test(SetNewPassword::class)
            ->set('contact', 'aminetou@example.com')
            ->set('code', $code)
            ->set('password', 'brand-new-password-2026')
            ->set('password_confirmation', 'brand-new-password-2026')
            ->call('resetPassword')
            ->assertHasNoErrors()
            ->assertRedirect(route('login'));

        $this->assertTrue(Hash::check('brand-new-password-2026', $user->fresh()?->password));
        $this->assertSame(0, PersonalAccessToken::query()->count());
    }

    public function test_a_wrong_code_shows_a_generic_error(): void
    {
        User::factory()->create(['email' => 'aminetou@example.com']);
        app(RequestPasswordReset::class)->handle('aminetou@example.com');

        Livewire::test(SetNewPassword::class)
            ->set('contact', 'aminetou@example.com')
            ->set('code', '000000')
            ->set('password', 'brand-new-password-2026')
            ->set('password_confirmation', 'brand-new-password-2026')
            ->call('resetPassword')
            ->assertHasErrors(['code' => __('auth.password_reset.invalid_code')]);
    }

    public function test_password_confirmation_must_match(): void
    {
        User::factory()->create(['email' => 'aminetou@example.com']);
        app(RequestPasswordReset::class)->handle('aminetou@example.com');
        $code = $this->lastCode();

        Livewire::test(SetNewPassword::class)
            ->set('contact', 'aminetou@example.com')
            ->set('code', $code)
            ->set('password', 'brand-new-password-2026')
            ->set('password_confirmation', 'something-else-2026')
            ->call('resetPassword')
            ->assertHasErrors(['password']);
    }

    public function test_reset_attempts_are_throttled(): void
    {
        User::factory()->create(['email' => 'aminetou@example.com']);
        app(RequestPasswordReset::class)->handle('aminetou@example.com');

        for ($i = 0; $i < 5; $i++) {
            Livewire::test(SetNewPassword::class)
                ->set('contact', 'aminetou@example.com')
                ->set('code', '000000')
                ->set('password', 'brand-new-password-2026')
                ->set('password_confirmation', 'brand-new-password-2026')
                ->call('resetPassword')
                ->assertHasErrors(['code' => __('auth.password_reset.invalid_code')]);
        }

        Livewire::test(SetNewPassword::class)
            ->set('contact', 'aminetou@example.com')
            ->set('code', $this->lastCode())
            ->set('password', 'brand-new-password-2026')
            ->set('password_confirmation', 'brand-new-password-2026')
            ->call('resetPassword')
            ->assertHasErrors('code');
    }
}
