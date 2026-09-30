<?php

namespace Tests\Feature\Actions\Auth;

use App\Actions\Auth\LogoutUser;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\TestCase;

class LogoutUserTest extends TestCase
{
    use RefreshDatabase;

    public function test_revokes_only_the_token_of_the_current_request(): void
    {
        $user = User::factory()->create();
        $current = $user->createToken('phone');
        $user->createToken('laptop');
        $user->withAccessToken($current->accessToken);

        app(LogoutUser::class)->handle($user);

        $this->assertSame(['laptop'], PersonalAccessToken::query()->pluck('name')->all());
    }

    public function test_does_nothing_when_the_request_has_no_personal_access_token(): void
    {
        $user = User::factory()->create();
        $user->createToken('phone');

        app(LogoutUser::class)->handle($user);

        $this->assertSame(1, PersonalAccessToken::query()->count());
    }
}
