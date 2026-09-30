<?php

namespace App\Enums;

/**
 * Why the owner closed a mobility request. A fixed list rather than free text: no personal data
 * is collected and the reasons can be counted in the statistics.
 */
enum RequestCloseReason: string
{
    case PermutationCompleted = 'permutation_completed';
    case NoLongerNeeded = 'no_longer_needed';
    case Other = 'other';
}
