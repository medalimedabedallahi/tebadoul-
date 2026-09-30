<?php

namespace App\Enums;

/**
 * Audited events of contact sharing on a match.
 */
enum ContactEventAction: string
{
    case Granted = 'granted';
    case Revoked = 'revoked';
    case Viewed = 'viewed';
}
