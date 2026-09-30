<?php

namespace Tests\Feature\Livewire\Account;

use App\Enums\UserStatus;
use App\Livewire\Account\Show;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\Support\InteractsWithContactCodes;
use Tests\TestCase;

class ShowTest extends TestCase
{
    use InteractsWithContactCodes;
    use RefreshDatabase;

    public function test_a_guest_is_redirected_to_login(): void
    {
        $this->get(route('account.show'))->assertRedirect(route('login'));
    }

    public function test_displays_the_name_status_and_masked_contacts_never_in_clear(): void
    {
        $user = User::factory()->phoneVerified()->create([
            'name' => 'Aminetou Sy',
            'email' => 'aminetou@example.com',
            'phone' => '+22241111111',
        ]);

        $response = $this->actingAs($user)->get(route('account.show'));

        $response->assertOk();
        $response->assertSee('Aminetou Sy');
        $response->assertDontSee('aminetou@example.com');
        $response->assertDontSee('+22241111111');
        $response->assertDontSee('41111111');
    }

    public function test_updates_the_password_with_the_correct_current_password(): void
    {
        $user = User::factory()->create(['password' => self::PASSWORD]);

        Livewire::actingAs($user)
            ->test(Show::class)
            ->set('current_password', self::PASSWORD)
            ->set('password', 'brand-new-password-2026')
            ->set('password_confirmation', 'brand-new-password-2026')
            ->call('updatePassword')
            ->assertHasNoErrors()
            ->assertSet('passwordUpdated', __('auth.account.password_updated'));

        $this->assertTrue(Hash::check('brand-new-password-2026', $user->fresh()?->password));
    }

    public function test_a_wrong_current_password_is_rejected_and_nothing_changes(): void
    {
        $user = User::factory()->create(['password' => self::PASSWORD]);

        Livewire::actingAs($user)
            ->test(Show::class)
            ->set('current_password', 'wrong-password-123')
            ->set('password', 'brand-new-password-2026')
            ->set('password_confirmation', 'brand-new-password-2026')
            ->call('updatePassword')
            ->assertHasErrors(['current_password']);

        $this->assertTrue(Hash::check(self::PASSWORD, $user->fresh()?->password));
    }

    public function test_password_confirmation_must_match(): void
    {
        $user = User::factory()->create(['password' => self::PASSWORD]);

        Livewire::actingAs($user)
            ->test(Show::class)
            ->set('current_password', self::PASSWORD)
            ->set('password', 'brand-new-password-2026')
            ->set('password_confirmation', 'something-else-2026')
            ->call('updatePassword')
            ->assertHasErrors(['password']);
    }

    public function test_an_account_pending_verification_cannot_reach_the_account_page(): void
    {
        $user = User::factory()->unverified()->create(['status' => UserStatus::PendingVerification]);

        $response = $this->actingAs($user)->get(route('account.show'));

        $response->assertRedirect(route('verification.show'));
        $this->assertAuthenticated();
    }

    public function test_a_suspended_account_is_signed_out_when_reaching_the_account_page(): void
    {
        $user = User::factory()->suspended()->create();

        $response = $this->actingAs($user)->get(route('account.show'));

        $response->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_a_successful_change_clears_the_errors_of_a_previous_attempt(): void
    {
        $user = User::factory()->create(['password' => self::PASSWORD]);

        Livewire::actingAs($user)
            ->test(Show::class)
            ->set('current_password', 'wrong-password-2026')
            ->set('password', 'brand-new-password-2026')
            ->set('password_confirmation', 'brand-new-password-2026')
            ->call('updatePassword')
            ->assertHasErrors(['current_password'])
            ->set('current_password', self::PASSWORD)
            ->set('password', 'brand-new-password-2026')
            ->set('password_confirmation', 'brand-new-password-2026')
            ->call('updatePassword')
            ->assertHasNoErrors()
            ->assertSet('passwordUpdated', __('auth.account.password_updated'));
    }

    public function test_notification_preferences_are_saved_and_an_unsupported_language_is_refused(): void
    {
        $user = User::factory()->create(['locale' => 'fr']);

        Livewire::actingAs($user)
            ->test(Show::class)
            ->assertSet('email_notifications', true)
            ->set('email_notifications', false)
            ->set('locale', 'ar')
            ->call('savePreferences')
            ->assertHasNoErrors()
            ->assertSet('preferencesSaved', __('notifications.preferences.saved'))
            ->set('locale', 'en')
            ->call('savePreferences')
            ->assertHasErrors(['locale' => 'in']);

        $this->assertDatabaseHas('users', ['id' => $user->id, 'email_notifications' => false, 'locale' => 'ar']);
    }
}
