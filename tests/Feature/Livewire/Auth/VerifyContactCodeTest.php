<?php

namespace Tests\Feature\Livewire\Auth;

use App\Actions\Auth\SendContactVerification;
use App\Enums\ContactPurpose;
use App\Enums\UserStatus;
use App\Livewire\Auth\VerifyContactCode;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Support\InteractsWithContactCodes;
use Tests\TestCase;

class VerifyContactCodeTest extends TestCase
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
        session(['pending_verification_contact' => 'aminetou@example.com']);

        Livewire::test(VerifyContactCode::class)
            ->assertSet('contact', 'aminetou@example.com');
    }

    public function test_verifies_with_the_correct_code_and_redirects_to_login(): void
    {
        $user = User::factory()->unverified()->create([
            'email' => 'aminetou@example.com',
            'status' => UserStatus::PendingVerification,
        ]);
        app(SendContactVerification::class)->handle('aminetou@example.com', ContactPurpose::ContactVerification);
        $code = $this->lastCode();

        Livewire::test(VerifyContactCode::class)
            ->set('contact', 'aminetou@example.com')
            ->set('code', $code)
            ->call('verify')
            ->assertHasNoErrors()
            ->assertRedirect(route('login'));

        $this->assertNotNull($user->fresh()?->email_verified_at);
        $this->assertSame(UserStatus::Active, $user->fresh()?->status);
    }

    public function test_a_wrong_code_shows_a_generic_error(): void
    {
        User::factory()->unverified()->create(['email' => 'aminetou@example.com']);
        app(SendContactVerification::class)->handle('aminetou@example.com', ContactPurpose::ContactVerification);

        Livewire::test(VerifyContactCode::class)
            ->set('contact', 'aminetou@example.com')
            ->set('code', '000000')
            ->call('verify')
            ->assertHasErrors(['code' => __('auth.verification.invalid_code')]);
    }

    public function test_resend_issues_a_new_code_without_redirecting(): void
    {
        User::factory()->unverified()->create(['email' => 'aminetou@example.com']);

        Livewire::test(VerifyContactCode::class)
            ->set('contact', 'aminetou@example.com')
            ->call('resend')
            ->assertHasNoErrors()
            ->assertNoRedirect()
            ->assertSet('resendStatus', __('auth.verification.resend_success'));

        $this->assertNotNull($this->lastCode());
    }

    public function test_resend_gives_the_same_generic_response_for_an_unknown_contact(): void
    {
        Livewire::test(VerifyContactCode::class)
            ->set('contact', 'ghost@example.com')
            ->call('resend')
            ->assertHasNoErrors()
            ->assertSet('resendStatus', __('auth.verification.resend_success'));
    }

    public function test_verify_attempts_are_throttled(): void
    {
        User::factory()->unverified()->create(['email' => 'aminetou@example.com']);
        app(SendContactVerification::class)->handle('aminetou@example.com', ContactPurpose::ContactVerification);

        for ($i = 0; $i < 5; $i++) {
            Livewire::test(VerifyContactCode::class)
                ->set('contact', 'aminetou@example.com')
                ->set('code', '000000')
                ->call('verify')
                ->assertHasErrors(['code' => __('auth.verification.invalid_code')]);
        }

        Livewire::test(VerifyContactCode::class)
            ->set('contact', 'aminetou@example.com')
            ->set('code', $this->lastCode())
            ->call('verify')
            ->assertHasErrors('code');
    }

    public function test_resend_is_throttled(): void
    {
        User::factory()->unverified()->create(['email' => 'aminetou@example.com']);

        for ($i = 0; $i < 3; $i++) {
            Livewire::test(VerifyContactCode::class)
                ->set('contact', 'aminetou@example.com')
                ->call('resend')
                ->assertHasNoErrors();
        }

        Livewire::test(VerifyContactCode::class)
            ->set('contact', 'aminetou@example.com')
            ->call('resend')
            ->assertHasErrors('contact');
    }
}
