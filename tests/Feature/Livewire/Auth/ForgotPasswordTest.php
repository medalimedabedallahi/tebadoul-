<?php

namespace Tests\Feature\Livewire\Auth;

use App\Livewire\Auth\ForgotPassword;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Support\InteractsWithContactCodes;
use Tests\TestCase;

class ForgotPasswordTest extends TestCase
{
    use InteractsWithContactCodes;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->captureContactCodes();
    }

    public function test_requesting_a_reset_for_an_existing_contact_redirects_with_a_generic_message(): void
    {
        User::factory()->create(['email' => 'aminetou@example.com']);

        Livewire::test(ForgotPassword::class)
            ->set('contact', 'aminetou@example.com')
            ->call('request')
            ->assertHasNoErrors()
            ->assertRedirect(route('password.reset'));

        $this->assertNotNull($this->lastCode());
        $this->assertSame('aminetou@example.com', session('pending_password_reset_contact'));
    }

    public function test_requesting_a_reset_for_an_unknown_contact_gives_the_same_outcome(): void
    {
        Livewire::test(ForgotPassword::class)
            ->set('contact', 'ghost@example.com')
            ->call('request')
            ->assertHasNoErrors()
            ->assertRedirect(route('password.reset'));
    }

    public function test_an_invalid_contact_is_rejected(): void
    {
        Livewire::test(ForgotPassword::class)
            ->set('contact', 'not-a-contact')
            ->call('request')
            ->assertHasErrors(['contact']);
    }

    public function test_password_reset_requests_are_throttled(): void
    {
        User::factory()->create(['email' => 'aminetou@example.com']);

        for ($i = 0; $i < 3; $i++) {
            Livewire::test(ForgotPassword::class)
                ->set('contact', 'aminetou@example.com')
                ->call('request')
                ->assertHasNoErrors();
        }

        Livewire::test(ForgotPassword::class)
            ->set('contact', 'aminetou@example.com')
            ->call('request')
            ->assertHasErrors('contact');
    }
}
