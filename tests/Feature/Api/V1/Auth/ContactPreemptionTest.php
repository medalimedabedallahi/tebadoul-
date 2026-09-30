<?php

namespace Tests\Feature\Api\V1\Auth;

use App\Actions\Auth\RegisterUser;
use App\Enums\UserStatus;
use App\Models\ContactVerification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\InteractsWithContactCodes;
use Tests\TestCase;

/**
 * V3 (security audit, fixed): registering with someone else's contact used to reserve it for an
 * account whose name and password the attacker chose, usable at once through the API, and the
 * real owner's own registration was silently swallowed. Now (see {@see RegisterUser}):
 *
 * - the squatted, unverified account cannot sign in;
 * - the real owner's registration takes the unverified contact over (the squatting account, left
 *   without any contact, is deleted), and the owner completes the verification with the code sent
 *   to their own contact;
 * - an abandoned pending account is purged after the configured TTL anyway.
 */
class ContactPreemptionTest extends TestCase
{
    use InteractsWithContactCodes;
    use RefreshDatabase;

    public function test_v3_registering_an_unowned_contact_no_longer_pre_empts_it(): void
    {
        $this->captureContactCodes();

        $this->postJson('/api/v1/auth/register', [
            'name' => 'Attaquant',
            'email' => 'victime@example.com',
            'password' => 'Attacker-chosen-password-1',
        ])->assertAccepted();

        $squatted = User::query()->where('email', 'victime@example.com')->sole();
        $this->assertSame(UserStatus::PendingVerification, $squatted->status);

        // The attacker cannot authenticate against the contact with their own password.
        $this->postJson('/api/v1/auth/login', [
            'identifier' => 'victime@example.com',
            'password' => 'Attacker-chosen-password-1',
        ])->assertUnauthorized()->assertJsonPath('code', 'invalid_credentials');

        // The real owner's registration takes the contact over.
        $this->postJson('/api/v1/auth/register', [
            'name' => 'Vraie Victime',
            'email' => 'victime@example.com',
            'password' => 'Victims-own-real-password-1',
        ])->assertAccepted();

        $this->assertModelMissing($squatted);
        $owner = User::query()->where('email', 'victime@example.com')->sole();
        $this->assertSame('Vraie Victime', $owner->name);

        // Only the owner's code is still usable, and it activates the owner's account.
        $this->assertSame($owner->id, ContactVerification::query()->unconsumed()->sole()->user_id);
        $this->postJson('/api/v1/auth/contacts/verification/verify', ['contact' => 'victime@example.com', 'code' => $this->lastCode()])
            ->assertOk();
        $this->postJson('/api/v1/auth/login', ['identifier' => 'victime@example.com', 'password' => 'Victims-own-real-password-1'])
            ->assertOk();
        $this->postJson('/api/v1/auth/login', ['identifier' => 'victime@example.com', 'password' => 'Attacker-chosen-password-1'])
            ->assertUnauthorized();
    }

    public function test_v3_verifying_the_attackers_own_contact_drops_the_victims_unverified_one(): void
    {
        $this->captureContactCodes();

        $this->postJson('/api/v1/auth/register', [
            'name' => 'Attaquant',
            'email' => 'attaquant@example.com',
            'phone' => '41111111',
            'password' => 'Attacker-chosen-password-1',
        ])->assertAccepted();

        $emailCode = $this->lastCodeFor($this->activeVerification('attaquant@example.com'));
        $this->postJson('/api/v1/auth/contacts/verification/verify', ['contact' => 'attaquant@example.com', 'code' => $emailCode])
            ->assertOk();

        $attacker = User::query()->where('email', 'attaquant@example.com')->sole();
        $this->assertNull($attacker->phone);
        $this->assertNull($this->activeVerification('+22241111111'));
        $this->assertSame(0, User::query()->where('phone', '+22241111111')->count());
    }
}
