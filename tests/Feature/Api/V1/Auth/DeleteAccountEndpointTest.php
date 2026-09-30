<?php

namespace Tests\Feature\Api\V1\Auth;

use App\Enums\TokenAbility;
use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\InteractsWithContactCodes;
use Tests\TestCase;

class DeleteAccountEndpointTest extends TestCase
{
    use InteractsWithContactCodes;
    use RefreshDatabase;

    public function test_the_owner_deletes_the_account_and_every_token_stops_working(): void
    {
        $user = User::factory()->create(['password' => self::PASSWORD]);
        $token = $this->tokenFor($user);
        $otherDevice = $this->tokenFor($user);

        $this->withToken($token)->deleteJson('/api/v1/me', ['password' => self::PASSWORD])->assertNoContent();

        $this->assertSame(UserStatus::Deleted, $user->fresh()->status);
        $this->app['auth']->forgetGuards();
        $this->withToken($otherDevice)->getJson('/api/v1/auth/me')->assertUnauthorized();
    }

    public function test_a_wrong_password_gets_422_and_keeps_the_account(): void
    {
        $user = User::factory()->create(['password' => self::PASSWORD]);

        $this->withToken($this->tokenFor($user))->deleteJson('/api/v1/me', ['password' => 'wrong-password-2026'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['password' => 'Le mot de passe fourni ne correspond pas à votre mot de passe actuel.']);

        $this->assertSame(UserStatus::Active, $user->fresh()->status);
    }

    private function tokenFor(User $user): string
    {
        return $user->createToken('test', [TokenAbility::AccessApi->value])->plainTextToken;
    }
}
