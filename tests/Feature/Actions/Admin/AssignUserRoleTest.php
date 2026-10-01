<?php

namespace Tests\Feature\Actions\Admin;

use App\Actions\Admin\AssignUserRole;
use App\Enums\UserRole;
use App\Exceptions\Admin\RoleNotAssignableException;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AssignUserRoleTest extends TestCase
{
    use RefreshDatabase;

    public function test_names_an_administrator_from_the_email_of_an_active_verified_account(): void
    {
        $user = User::factory()->create(['email' => 'aminetou@example.com']);

        app(AssignUserRole::class)->handle(' Aminetou@Example.com ', UserRole::Administrator);
        app(AssignUserRole::class)->handle('aminetou@example.com', UserRole::Administrator);

        $this->assertSame(UserRole::Administrator, $user->fresh()->role);
    }

    public function test_never_gives_a_role_to_an_unusable_account(): void
    {
        $accounts = [
            User::factory()->suspended()->create(),
            User::factory()->unverified()->create(),
        ];

        foreach ($accounts as $account) {
            try {
                app(AssignUserRole::class)->handle((string) $account->email, UserRole::Administrator);
                $this->fail('A role was given to an unusable account.');
            } catch (RoleNotAssignableException) {
            }

            $this->assertSame(UserRole::User, $account->fresh()->role);
        }

        $this->expectException(ModelNotFoundException::class);
        app(AssignUserRole::class)->handle('nobody@example.com', UserRole::Administrator);
    }

    public function test_the_command_reports_its_outcome(): void
    {
        $user = User::factory()->create(['email' => 'aminetou@example.com']);

        $this->artisan('auth:assign-role', ['email' => 'aminetou@example.com'])->assertSuccessful();
        $this->assertSame(UserRole::Administrator, $user->fresh()->role);

        $this->artisan('auth:assign-role', ['email' => 'aminetou@example.com', 'role' => 'moderator'])->assertSuccessful();
        $this->assertSame(UserRole::Moderator, $user->fresh()->role);

        $this->artisan('auth:assign-role', ['email' => 'aminetou@example.com', 'role' => 'owner'])->assertFailed();
        $this->artisan('auth:assign-role', ['email' => 'nobody@example.com'])->assertFailed();
    }
}
