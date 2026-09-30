<?php

namespace App\Enums;

/**
 * Authorization role of an authenticated account.
 */
enum UserRole: string
{
    case User = 'user';
    case Moderator = 'moderator';
    case Administrator = 'administrator';
}
