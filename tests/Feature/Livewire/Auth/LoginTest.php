<?php

namespace Tests\Feature\Livewire\Auth;

use App\Enums\UserStatus;
use App\Livewire\Auth\Login;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Support\InteractsWithContactCodes;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use InteractsWithContactCodes;
    use RefreshDatabase;

    public function test_an_active_account_is_signed_in_and_redirected_to_the_account_page(): void
    {
        $user = User::factory()->create(['email' => 'aminetou@example.com', 'password' => self::PASSWORD]);

        Livewire::test(Login::class)
            ->set('identifier', 'aminetou@example.com')
            ->set('password', self::PASSWORD)
            ->call('login')
            ->assertHasNoErrors()
            ->assertRedirect(route('account.show'));

        $this->assertAuthenticatedAs($user);
    }

    public function test_a_wrong_password_shows_a_generic_error_and_does_not_sign_in(): void
    {
        User::factory()->create(['email' => 'aminetou@example.com', 'password' => self::PASSWORD]);

        Livewire::test(Login::class)
            ->set('identifier', 'aminetou@example.com')
            ->set('password', 'wrong-password-123')
            ->call('login')
            ->assertHasErrors(['identifier']);

        $this->assertGuest();
    }

    public function test_an_unknown_identifier_shows_the_same_generic_error_as_a_wrong_password(): void
    {
        Livewire::test(Login::class)
            ->set('identifier', 'ghost@example.com')
            ->set('password', self::PASSWORD)
            ->call('login')
            ->assertHasErrors(['identifier' => __('auth.failed')]);

        $this->assertGuest();
    }

    public function test_the_password_is_not_retained_on_the_component_after_a_failed_attempt(): void
    {
        User::factory()->create(['email' => 'aminetou@example.com', 'password' => self::PASSWORD]);

        Livewire::test(Login::class)
            ->set('identifier', 'aminetou@example.com')
            ->set('password', 'wrong-password-123')
            ->call('login')
            ->assertSet('password', '');
    }

    public function test_a_suspended_account_shows_a_distinct_message_and_does_not_sign_in(): void
    {
        User::factory()->suspended()->create(['email' => 's@example.com', 'password' => self::PASSWORD]);

        Livewire::test(Login::class)
            ->set('identifier', 's@example.com')
            ->set('password', self::PASSWORD)
            ->call('login')
            ->assertHasErrors(['identifier' => __('auth.login.suspended')]);

        $this->assertGuest();
    }

    public function test_an_account_pending_verification_gets_exactly_the_outcome_of_an_unknown_identifier(): void
    {
        User::factory()->unverified()->create([
            'email' => 'pending@example.com',
            'status' => UserStatus::PendingVerification,
            'password' => self::PASSWORD,
        ]);

        foreach (['pending@example.com', 'ghost@example.com'] as $identifier) {
            Livewire::test(Login::class)
                ->set('identifier', $identifier)
                ->set('password', self::PASSWORD)
                ->call('login')
                ->assertHasErrors(['identifier' => __('auth.failed')])
                ->assertNoRedirect();

            $this->assertGuest();
            $this->assertNull(session('pending_verification_contact'));
        }
    }

    public function test_login_is_throttled_after_five_failed_attempts_for_the_same_identifier(): void
    {
        User::factory()->create(['email' => 'aminetou@example.com', 'password' => self::PASSWORD]);

        for ($i = 0; $i < 5; $i++) {
            Livewire::test(Login::class)
                ->set('identifier', 'aminetou@example.com')
                ->set('password', 'wrong-password-123')
                ->call('login')
                ->assertHasErrors(['identifier' => __('auth.failed')]);
        }

        Livewire::test(Login::class)
            ->set('identifier', 'aminetou@example.com')
            ->set('password', self::PASSWORD)
            ->call('login')
            ->assertHasErrors('identifier');

        $this->assertGuest();
    }

    public function test_guest_sees_the_login_form(): void
    {
        $this->get(route('login'))->assertOk();
    }

    public function test_an_authenticated_visitor_is_redirected_away_from_the_login_page(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('login'))->assertRedirect(route('account.show'));
    }
}
