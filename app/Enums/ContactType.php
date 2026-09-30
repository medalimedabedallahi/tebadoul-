<?php

namespace App\Enums;

/**
 * Kind of contact detail an account can be identified and verified with.
 */
enum ContactType: string
{
    case Email = 'email';
    case Phone = 'phone';

    /**
     * Guess the kind of a raw contact value: anything containing "@" is an email, the rest a phone.
     */
    public static function detect(string $contact): self
    {
        return str_contains($contact, '@') ? self::Email : self::Phone;
    }
}
