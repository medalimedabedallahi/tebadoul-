<?php

namespace App\Enums;

/**
 * Optional reason given when declining a match. A fixed list rather than free text: nothing
 * personal is collected about the other participant.
 */
enum MatchDeclineReason: string
{
    case NotInterested = 'not_interested';
    case SituationChanged = 'situation_changed';
    case Other = 'other';
}
