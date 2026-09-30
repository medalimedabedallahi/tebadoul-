<?php

namespace App\Enums;

/**
 * What a one-time code sent to a contact is for.
 */
enum ContactPurpose: string
{
    case ContactVerification = 'contact_verification';
    case PasswordReset = 'password_reset';
}
