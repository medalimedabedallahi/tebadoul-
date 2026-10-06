<?php

namespace Tests\Feature\Actions\Auth;

use App\Actions\Auth\AuthenticateGoogleUser;
use App\Actions\Auth\RegisterGoogleUser;
use App\Enums\UserStatus;
use App\Models\User;
use App\Support\Auth\GoogleIdentity;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class GoogleAccountActionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_creates_an_active_verified_account_and_records_terms(): void
    {
        $this->freezeSecond();
        $identity = $this->identity();

        $user = app(RegisterGoogleUser::class)->handle($identity, 'Aminetou', true, 'ar');

        $this->assertSame(UserStatus::Active, $user->status);
        $this->assertSame('ar', $user->locale);
        $this->assertSame($identity->subject, $user->google_id);
        $this->assertSame($identity->email, $user->email);
        $this->assertTrue($user->email_verified_at->equalTo(now()));
        $this->assertTrue($user->terms_accepted_at->equalTo(now()));
        $this->assertSame(config('legal.version'), $user->terms_version);
        $this->assertTrue(Hash::isHashed($user->password));
        $this->assertSame(0, $user->contactVerifications()->count());
    }

    public function test_registration_requires_explicit_terms_acceptance(): void
    {
        try {
            app(RegisterGoogleUser::class)->handle($this->identity(), 'Aminetou', false);
            $this->fail('Terms must be accepted.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('accept_terms', $exception->errors());
        }

        $this->assertDatabaseCount('users', 0);
    }

    #[TestWith([true])]
    #[TestWith([false])]
    public function test_existing_email_is_not_automatically_linked_or_activated(bool $verified): void
    {
        $holder = User::factory()->create([
            'email' => 'aminetou@gmail.com',
            'email_verified_at' => $verified ? now() : null,
            'status' => $verified ? UserStatus::Active : UserStatus::PendingVerification,
        ]);
        $password = $holder->password;

        foreach ([fn () => app(AuthenticateGoogleUser::class)->handle($this->identity()),
            fn () => app(RegisterGoogleUser::class)->handle($this->identity(), 'Another name', true)] as $operation) {
            try {
                $operation();
                $this->fail('Email matches must not link accounts.');
            } catch (ValidationException $exception) {
                $this->assertSame([__('auth.google.existing_account')], $exception->errors()['google']);
            }
        }

        $this->assertNull($holder->fresh()->google_id);
        $this->assertSame($password, $holder->fresh()->password);
        $this->assertSame($verified ? UserStatus::Active : UserStatus::PendingVerification, $holder->fresh()->status);
        $this->assertDatabaseCount('users', 1);
    }

    public function test_an_active_account_can_link_the_same_verified_email_and_sign_in_by_subject(): void
    {
        $user = User::factory()->create(['email' => 'aminetou@gmail.com']);

        app(AuthenticateGoogleUser::class)->handle($this->identity(), $user);
        $signedIn = app(AuthenticateGoogleUser::class)->handle($this->identity());

        $this->assertTrue($signedIn->is($user));
        $this->assertSame('google-subject-123', $user->fresh()->google_id);
    }

    #[TestWith(['suspended'])]
    #[TestWith(['pending_verification'])]
    #[TestWith(['deleted'])]
    public function test_inactive_linked_accounts_cannot_sign_in(string $status): void
    {
        User::factory()->create(['email' => 'aminetou@gmail.com', 'google_id' => 'google-subject-123', 'status' => $status]);

        $this->expectException(ValidationException::class);
        app(AuthenticateGoogleUser::class)->handle($this->identity());
    }

    public function test_a_suspended_account_cannot_link_google(): void
    {
        $user = User::factory()->suspended()->create(['email' => 'aminetou@gmail.com']);

        $this->expectException(AuthorizationException::class);
        app(AuthenticateGoogleUser::class)->handle($this->identity(), $user);
    }

    public function test_linking_a_different_email_is_refused_without_mutation(): void
    {
        $user = User::factory()->create(['email' => 'other@gmail.com']);

        try {
            app(AuthenticateGoogleUser::class)->handle($this->identity(), $user);
            $this->fail('An unrelated address cannot be linked.');
        } catch (ValidationException $exception) {
            $this->assertSame([__('auth.google.email_mismatch')], $exception->errors()['google']);
        }

        $this->assertNull($user->fresh()->google_id);
    }

    public function test_linking_cannot_replace_a_different_google_identity(): void
    {
        $user = User::factory()->create(['email' => 'aminetou@gmail.com', 'google_id' => 'original-subject']);

        try {
            app(AuthenticateGoogleUser::class)->handle($this->identity(), $user);
            $this->fail('An existing identity must not be replaced.');
        } catch (ValidationException $exception) {
            $this->assertSame([__('auth.google.already_linked')], $exception->errors()['google']);
        }

        $this->assertSame('original-subject', $user->fresh()->google_id);
    }

    public function test_changed_google_email_does_not_silently_change_the_local_account(): void
    {
        $user = User::factory()->create(['email' => 'previous@gmail.com', 'google_id' => 'google-subject-123']);

        try {
            app(AuthenticateGoogleUser::class)->handle($this->identity());
            $this->fail('An email change must not be applied silently.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('google', $exception->errors());
        }

        $this->assertSame('previous@gmail.com', $user->fresh()->email);
    }

    private function identity(): GoogleIdentity
    {
        return new GoogleIdentity('google-subject-123', 'aminetou@gmail.com', 'Aminetou');
    }

    public function test_google_migration_can_rollback_and_reapply_without_changing_accounts(): void
    {
        $user = User::factory()->create();
        $migration = require database_path('migrations/2026_10_06_123708_add_google_id_to_users_table.php');

        $migration->down();
        $this->assertFalse(Schema::hasColumn('users', 'google_id'));
        $migration->up();

        $this->assertTrue(Schema::hasColumn('users', 'google_id'));
        $this->assertNull($user->fresh()->google_id);
        $this->assertSame($user->email, $user->fresh()->email);
    }
}
