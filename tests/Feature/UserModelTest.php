<?php

namespace Tests\Feature;

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
        $this->assertSame('active', $user->status);
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
        ]);

        $serialized = $user->toArray();

        $this->assertArrayNotHasKey('email', $serialized);
        $this->assertArrayNotHasKey('phone', $serialized);
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
}
