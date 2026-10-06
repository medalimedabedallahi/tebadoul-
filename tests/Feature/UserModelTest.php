<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class UserModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_user_has_a_non_predictable_public_identifier(): void
    {
        $user = User::factory()->create();

        $this->assertTrue(Str::isUlid($user->public_id));
        $this->assertSame(UserStatus::Active, $user->status);
        $this->assertSame(UserRole::User, $user->role);
        $this->assertSame('fr', $user->locale);
    }

    public function test_the_public_identifier_is_generated_without_the_factory(): void
    {
        $user = User::create([
            'name' => 'Aminetou',
            'phone' => '+22241111111',
            'password' => 'secret-password',
        ]);

        $this->assertTrue(Str::isUlid($user->public_id));
        $this->assertNotSame($user->getKey(), $user->public_id);
        $this->assertSame('public_id', $user->getRouteKeyName());
    }

    public function test_contact_details_are_not_serialized(): void
    {
        $user = User::factory()->create([
            'phone' => '+22242222222',
            'phone_verified_at' => now(),
            'google_id' => 'private-google-subject',
        ]);

        $serialized = $user->toArray();

        $this->assertArrayNotHasKey('email', $serialized);
        $this->assertArrayNotHasKey('phone', $serialized);
        $this->assertArrayNotHasKey('google_id', $serialized);
        $this->assertArrayNotHasKey('password', $serialized);
        $this->assertArrayHasKey('public_id', $serialized);
    }

    public function test_a_user_can_use_a_phone_without_an_email(): void
    {
        $user = User::factory()->create([
            'email' => null,
            'email_verified_at' => null,
            'phone' => '+22240000000',
            'phone_verified_at' => now(),
        ]);

        $this->assertNull($user->email);
        $this->assertNotNull($user->phone_verified_at);
    }

    public function test_the_status_is_cast_to_an_enum(): void
    {
        $user = User::factory()->create(['status' => 'pending_verification']);

        $this->assertSame(UserStatus::PendingVerification, $user->fresh()->status);
    }

    public function test_the_factory_can_build_a_suspended_user(): void
    {
        $user = User::factory()->suspended()->create();

        $this->assertSame(UserStatus::Suspended, $user->fresh()->status);
        $this->assertFalse($user->status->canSignIn());
    }

    public function test_the_role_is_cast_and_privileged_factory_states_are_explicit(): void
    {
        $moderator = User::factory()->moderator()->create();
        $administrator = User::factory()->administrator()->create();

        $this->assertSame(UserRole::Moderator, $moderator->fresh()->role);
        $this->assertSame(UserRole::Administrator, $administrator->fresh()->role);
    }

    public function test_the_factory_can_build_a_user_with_a_verified_phone(): void
    {
        $user = User::factory()->phoneVerified()->create();

        $this->assertMatchesRegularExpression('/^\+222\d{8}$/', $user->phone);
        $this->assertNotNull($user->phone_verified_at);
    }

    public function test_the_factory_can_build_a_user_with_a_verified_email(): void
    {
        $user = User::factory()->unverified()->emailVerified()->create();

        $this->assertNotNull($user->email);
        $this->assertNotNull($user->email_verified_at);
    }

    public function test_the_email_is_trimmed_and_lower_cased_when_stored(): void
    {
        $user = User::factory()->create(['email' => '  Aminetou.Mint@Example.COM ']);

        $this->assertSame('aminetou.mint@example.com', $user->fresh()->email);

        $user->update(['email' => 'NEW.Address@Example.com']);

        $this->assertSame('new.address@example.com', $user->fresh()->email);
    }

    public function test_the_phone_is_stored_in_e164_format(): void
    {
        $this->assertSame('+22241111111', User::factory()->create(['phone' => '41 11 11 11'])->fresh()->phone);
        $this->assertSame('+22242222222', User::factory()->create(['phone' => '00222-42-22-22-22'])->fresh()->phone);
        $this->assertSame('+22243333333', User::factory()->create(['phone' => '+222 (43) 33.33.33'])->fresh()->phone);
    }

    public function test_a_blank_contact_is_stored_as_null_so_that_unique_indexes_ignore_it(): void
    {
        User::factory()->create(['email' => '', 'phone' => '+22241111111']);
        $second = User::factory()->create(['email' => '   ', 'phone' => '+22242222222']);

        $this->assertNull($second->fresh()->email);
        $this->assertDatabaseCount('users', 2);
    }

    public function test_role_status_public_id_and_verification_dates_cannot_be_mass_assigned(): void
    {
        $user = User::create([
            'name' => 'Aminetou',
            'email' => 'aminetou@example.com',
            'password' => 'secret-password',
            'status' => 'suspended',
            'role' => 'administrator',
            'public_id' => 'chosen-by-the-client',
            'email_verified_at' => now(),
            'phone_verified_at' => now(),
        ])->fresh();

        $this->assertSame(UserStatus::Active, $user->status);
        $this->assertSame(UserRole::User, $user->role);
        $this->assertTrue(Str::isUlid($user->public_id));
        $this->assertNull($user->email_verified_at);
        $this->assertNull($user->phone_verified_at);
    }

    public function test_role_status_and_verification_dates_cannot_be_changed_by_a_mass_update(): void
    {
        $user = User::factory()->unverified()->create();

        $user->update([
            'status' => 'suspended',
            'role' => 'administrator',
            'email_verified_at' => now(),
            'phone_verified_at' => now(),
        ]);

        $user = $user->fresh();
        $this->assertSame(UserStatus::Active, $user->status);
        $this->assertSame(UserRole::User, $user->role);
        $this->assertNull($user->email_verified_at);
        $this->assertNull($user->phone_verified_at);
    }
}
