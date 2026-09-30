<?php

namespace Tests\Feature\Api\V1\Auth;

use App\Actions\Auth\AuthenticateCredentials;
use App\Livewire\Auth\Login;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Livewire\Livewire;
use Tests\Support\InteractsWithContactCodes;
use Tests\TestCase;

/**
 * V2 (security audit, fixed): registering a contact with a password of one's choice, then signing
 * in with it, used to reveal whether the contact was free (the new pending account signed in) or
 * already taken (401). An account pending verification now fails to sign in exactly like an
 * unknown identifier (see {@see AuthenticateCredentials}), on the API and on the web, so the two
 * outcomes are indistinguishable.
 */
class AccountEnumerationViaRegisterThenLoginTest extends TestCase
{
    use InteractsWithContactCodes;
    use RefreshDatabase;

    private const ATTACKER_PASSWORD = 'Attacker-chosen-password-1';

    public function test_v2_login_after_register_no_longer_reveals_whether_the_contact_was_taken(): void
    {
        $this->captureContactCodes();
        User::factory()->create(['email' => 'victime@example.com']); // password unknown to the attacker

        $freeContactOutcome = $this->registerThenLogin('libre@example.com');
        $takenContactOutcome = $this->registerThenLogin('victime@example.com');

        $this->assertSame($freeContactOutcome['register'], $takenContactOutcome['register']);
        $this->assertSame(401, $freeContactOutcome['login_status']);
        $this->assertSame($freeContactOutcome['login_status'], $takenContactOutcome['login_status']);
        $this->assertSame($freeContactOutcome['login'], $takenContactOutcome['login']);
    }

    public function test_v2_the_web_sign_in_after_register_no_longer_reveals_it_either(): void
    {
        $this->captureContactCodes();
        User::factory()->create(['email' => 'victime@example.com']);

        foreach (['libre@example.com', 'victime@example.com'] as $contact) {
            $this->postJson('/api/v1/auth/register', ['accept_terms' => true, 'name' => 'Attacker', 'email' => $contact, 'password' => self::ATTACKER_PASSWORD])
                ->assertAccepted();

            Livewire::test(Login::class)
                ->set('identifier', $contact)
                ->set('password', self::ATTACKER_PASSWORD)
                ->call('login')
                ->assertHasErrors(['identifier' => __('auth.failed')])
                ->assertNoRedirect();

            $this->assertGuest();
        }
    }

    /**
     * @return array{register: array<string, mixed>, login_status: int, login: array<string, mixed>}
     */
    private function registerThenLogin(string $contact): array
    {
        $register = $this->postJson('/api/v1/auth/register', [
            'accept_terms' => true,
            'name' => 'Attacker',
            'email' => $contact,
            'password' => self::ATTACKER_PASSWORD,
        ]);

        $login = $this->postJson('/api/v1/auth/login', [
            'identifier' => $contact,
            'password' => self::ATTACKER_PASSWORD,
        ]);

        return [
            'register' => $this->withoutRequestId($register),
            'login_status' => $login->getStatusCode(),
            'login' => $this->withoutRequestId($login),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function withoutRequestId(TestResponse $response): array
    {
        return collect($response->json())->except('request_id')->all();
    }
}
