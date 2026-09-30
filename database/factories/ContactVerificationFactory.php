<?php

namespace Database\Factories;

use App\Enums\ContactPurpose;
use App\Enums\ContactType;
use App\Models\ContactVerification;
use App\Support\VerificationCode;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * The default code is "123456" ({@see self::KNOWN_CODE}); its hash follows the contact and the
 * purpose given to the factory. Use {@see self::withCode()} for another code.
 *
 * @extends Factory<ContactVerification>
 */
class ContactVerificationFactory extends Factory
{
    public const KNOWN_CODE = '123456';

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => null,
            'purpose' => ContactPurpose::ContactVerification,
            'channel' => ContactType::Email,
            'contact' => fake()->unique()->safeEmail(),
            'code_hash' => fn (array $attributes): string => self::hashFor($attributes, self::KNOWN_CODE),
            'attempts' => 0,
            'max_attempts' => 5,
            'expires_at' => now()->addMinutes(10),
            'consumed_at' => null,
            'last_sent_at' => null,
        ];
    }

    /**
     * Store the hash of the given code for the contact and purpose of the verification.
     */
    public function withCode(string $code): static
    {
        return $this->state(fn (): array => [
            'code_hash' => fn (array $attributes): string => self::hashFor($attributes, $code),
        ]);
    }

    /**
     * A code for a phone number.
     */
    public function forPhone(string $phone = '+22241111111'): static
    {
        return $this->state(fn (): array => [
            'channel' => ContactType::Phone,
            'contact' => $phone,
        ]);
    }

    /**
     * A code that resets a password rather than verifies a contact.
     */
    public function forPasswordReset(): static
    {
        return $this->state(fn (): array => ['purpose' => ContactPurpose::PasswordReset]);
    }

    public function expired(): static
    {
        return $this->state(fn (): array => ['expires_at' => now()->subMinute()]);
    }

    public function consumed(): static
    {
        return $this->state(fn (): array => ['consumed_at' => now()]);
    }

    /**
     * A code that already used up its attempts.
     */
    public function locked(): static
    {
        return $this->state(fn (): array => [
            'attempts' => fn (array $attributes): int => (int) $attributes['max_attempts'],
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private static function hashFor(array $attributes, string $code): string
    {
        $purpose = $attributes['purpose'];

        return VerificationCode::hash(
            (string) $attributes['contact'],
            $purpose instanceof ContactPurpose ? $purpose : ContactPurpose::from((string) $purpose),
            $code,
        );
    }
}
