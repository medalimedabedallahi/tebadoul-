<?php

namespace Database\Factories;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'phone' => null,
            'phone_verified_at' => null,
            'status' => UserStatus::Active,
            'role' => UserRole::User,
            'locale' => 'fr',
            'email_notifications' => true,
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    /**
     * Indicate that the account has an email address and that it has been verified.
     */
    public function emailVerified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email' => $attributes['email'] ?? fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
        ]);
    }

    /**
     * Indicate that the account has a phone number and that it has been verified.
     */
    public function phoneVerified(): static
    {
        return $this->state(fn (array $attributes) => [
            'phone' => $attributes['phone'] ?? '+222'.fake()->unique()->numerify('4#######'),
            'phone_verified_at' => now(),
        ]);
    }

    /**
     * Indicate that the account has been suspended.
     */
    public function suspended(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => UserStatus::Suspended,
        ]);
    }

    /**
     * Indicate that the account moderates user content and reports.
     */
    public function moderator(): static
    {
        return $this->state(fn (): array => [
            'role' => UserRole::Moderator,
        ]);
    }

    /**
     * Indicate that the account administers the platform.
     */
    public function administrator(): static
    {
        return $this->state(fn (): array => [
            'role' => UserRole::Administrator,
        ]);
    }
}
