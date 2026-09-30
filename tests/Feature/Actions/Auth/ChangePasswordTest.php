<?php

namespace Tests\Feature\Actions\Auth;

use App\Actions\Auth\ChangePassword;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Tests\Support\InteractsWithContactCodes;
use Tests\TestCase;

class ChangePasswordTest extends TestCase
{
    use InteractsWithContactCodes;
    use RefreshDatabase;

    private const NEW_PASSWORD = 'Brand-new-passphrase-77';

    public function test_changes_the_password_and_revokes_the_other_tokens_only(): void
    {
        $user = User::factory()->create(['password' => self::PASSWORD]);
        $current = $user->createToken('phone');
        $user->createToken('laptop');
        $user->withAccessToken($current->accessToken);

        app(ChangePassword::class)->handle($user, self::PASSWORD, self::NEW_PASSWORD);

        $this->assertTrue(Hash::check(self::NEW_PASSWORD, $user->fresh()->password));
        $this->assertSame(['phone'], $user->tokens()->pluck('name')->all());
    }

    public function test_rejects_a_wrong_current_password_and_changes_nothing(): void
    {
        $user = User::factory()->create(['password' => self::PASSWORD]);
        $user->createToken('laptop');

        try {
            app(ChangePassword::class)->handle($user, 'not-my-password-1', self::NEW_PASSWORD);
            $this->fail('A wrong current password must be refused.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('current_password', $e->errors());
            $this->assertTrue(Hash::check(self::PASSWORD, $user->fresh()->password));
            $this->assertSame(1, $user->tokens()->count());
        }
    }

    public function test_rules_refuse_a_weak_password_and_an_unchanged_one(): void
    {
        $this->assertTrue(Validator::make(['current_password' => self::PASSWORD, 'password' => self::NEW_PASSWORD], ChangePassword::rules())->passes());
        $this->assertTrue(Validator::make(['current_password' => self::PASSWORD, 'password' => 'short'], ChangePassword::rules())->fails());
        $this->assertTrue(Validator::make(['current_password' => self::PASSWORD, 'password' => self::PASSWORD], ChangePassword::rules())->fails());
    }
}
