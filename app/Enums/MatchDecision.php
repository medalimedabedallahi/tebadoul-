<?php

namespace App\Enums;

/**
 * A participant's decision on a match. No decision (null) means the participant has not
 * answered yet. Inviting counts as accepting.
 */
enum MatchDecision: string
{
    case Accepted = 'accepted';
    case Declined = 'declined';
    case Withdrawn = 'withdrawn';
}
