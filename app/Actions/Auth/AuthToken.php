<?php

namespace App\Actions\Auth;

use App\Enums\TokenAbility;
use App\Models\User;
use Carbon\CarbonInterface;

/**
 * Result of a successful sign-in: the account and the bearer token issued for it.
 *
 * `plainTextToken` is only known now (only its hash is stored): give it to the client and forget it.
 */
final readonly class AuthToken
{
    /**
     * @param  list<string>  $abilities  Abilities granted to the token (see {@see TokenAbility}).
     */
    public function __construct(
        public User $user,
        public string $plainTextToken,
        public array $abilities,
        public ?CarbonInterface $expiresAt,
    ) {}

    /**
     * Whether the token can only be used to verify a contact (account still pending verification).
     */
    public function isLimited(): bool
    {
        return ! in_array(TokenAbility::AccessApi->value, $this->abilities, true);
    }
}
