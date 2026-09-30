<?php

namespace App\Enums;

/**
 * Events a participant is told about (PRD FR 013), each about one match. The in-app notification
 * is always recorded; an email copy is sent only for the types that call for action, and never
 * for messages (one per conversation would flood the inbox).
 */
enum NotificationType: string
{
    case MatchSuggested = 'match_suggested';
    case InvitationReceived = 'invitation_received';
    case InvitationDeclined = 'invitation_declined';
    case MatchAccepted = 'match_accepted';
    case MatchWithdrawn = 'match_withdrawn';
    case ContactConsentGranted = 'contact_consent_granted';
    case MessageReceived = 'message_received';

    public function sendsEmail(): bool
    {
        return in_array($this, [self::MatchSuggested, self::InvitationReceived, self::MatchAccepted, self::MatchWithdrawn, self::ContactConsentGranted], true);
    }
}
