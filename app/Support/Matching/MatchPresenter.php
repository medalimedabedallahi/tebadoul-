<?php

namespace App\Support\Matching;

use App\Actions\Matching\ListMatches;
use App\Contracts\ReferenceEntry;
use App\Enums\MatchDecision;
use App\Enums\MatchStatus;
use App\Models\MatchParticipant;
use App\Models\MatchReason;
use App\Models\MobilityMatch;
use App\Models\User;

/**
 * A match as one participant sees it, shared by the API resource and the web pages.
 *
 * Only what is needed to judge the compatibility is shown about the other participant (PRD 8.3):
 * sector, profession, specialty, grade, current wilaya and moughataa (the viewer's destination)
 * and availability. Never a name, a contact, an establishment, an account or request identifier.
 * Expects the relations of {@see ListMatches::PRESENTATION} to be loaded.
 */
final class MatchPresenter
{
    /**
     * @return array{
     *     public_id: string,
     *     type: string,
     *     status: string,
     *     score: int,
     *     rules_version: string,
     *     invalidation_reason: string|null,
     *     my_request: string|null,
     *     counterpart: array<string, mixed>|null,
     *     reasons: list<array{criterion: string, points: int, max_points: int, detail: string}>,
     *     my_decision: string|null,
     *     invited_by_me: bool,
     *     expires_at: string|null,
     *     outcome_reason: string|null,
     *     contact_sharing: array{my_consent: bool, counterpart_consent: bool, available: bool},
     *     blocked_by_me: bool,
     *     interaction_blocked: bool,
     *     allowed_actions: list<string>,
     *     allowed_progress_statuses: list<string>,
     *     created_at: string|null,
     *     updated_at: string|null
     * }
     */
    public static function forViewer(MobilityMatch $match, User $viewer): array
    {
        $mine = $match->participantOf($viewer);
        $other = $match->counterpartOf($viewer);
        $otherRequest = $other?->mobilityRequest;
        $otherProfile = $otherRequest?->user->professionalProfile;
        $myUser = $mine?->mobilityRequest->user;
        $otherUser = $otherRequest?->user;
        $blockedByMe = $myUser?->blocksInitiated->contains('blocked_id', $other?->user_id) ?? false;
        $blockedMe = $otherUser?->blocksInitiated->contains('blocked_id', $mine?->user_id) ?? false;

        return [
            'public_id' => $match->public_id,
            'type' => $match->type,
            'status' => $match->status->value,
            'score' => $match->score,
            'rules_version' => $match->rules_version,
            'invalidation_reason' => $match->invalidation_reason,
            'my_request' => $mine?->mobilityRequest->public_id,
            'counterpart' => $otherRequest === null || $otherProfile === null ? null : [
                'sector' => self::reference($otherProfile->sector),
                'profession' => self::reference($otherProfile->profession),
                'specialty' => self::reference($otherProfile->specialty),
                'grade' => self::reference($otherProfile->grade),
                'wilaya' => self::reference($otherProfile->moughataa->wilaya),
                'moughataa' => self::reference($otherProfile->moughataa),
                'available_from' => $otherRequest->available_from->toDateString(),
                'expires_at' => $otherRequest->expires_at->toDateString(),
            ],
            'reasons' => $match->reasons->map(fn (MatchReason $reason): array => [
                'criterion' => $reason->criterion,
                'points' => $reason->points,
                'max_points' => $reason->max_points,
                'detail' => $reason->detail,
            ])->values()->all(),
            'my_decision' => $mine?->decision?->value,
            'invited_by_me' => $match->status === MatchStatus::Invited
                && $mine?->decision === MatchDecision::Accepted,
            'expires_at' => $match->expires_at?->toIso8601String(),
            'outcome_reason' => $match->outcome_reason,
            'contact_sharing' => [
                'my_consent' => $mine?->contact_consented_at !== null,
                'counterpart_consent' => $other?->contact_consented_at !== null,
                'available' => $match->status->isAccord()
                    && $mine?->contact_consented_at !== null
                    && $other?->contact_consented_at !== null,
            ],
            'blocked_by_me' => $blockedByMe,
            'interaction_blocked' => $blockedByMe || $blockedMe,
            'allowed_actions' => self::allowedActions($match, $mine, $other, $blockedByMe, $blockedMe),
            'allowed_progress_statuses' => array_map(
                static fn (MatchStatus $status): string => $status->value,
                $match->status->nextSteps(),
            ),
            'created_at' => $match->created_at?->toIso8601String(),
            'updated_at' => $match->updated_at?->toIso8601String(),
        ];
    }

    /**
     * Operations available to this participant now. Clients must use this list rather than
     * rebuilding the lifecycle and double-consent rules themselves.
     *
     * @return list<string>
     */
    private static function allowedActions(
        MobilityMatch $match,
        ?MatchParticipant $mine,
        ?MatchParticipant $other,
        bool $blockedByMe,
        bool $blockedMe,
    ): array {
        if ($mine === null || $other === null) {
            return [];
        }

        $status = $match->status;
        $blocked = $blockedByMe || $blockedMe;

        return array_values(array_filter([
            ! $blocked && $status === MatchStatus::Suggested ? 'invite' : null,
            $status === MatchStatus::Suggested || ($status === MatchStatus::Invited && $mine->decision === null) ? 'decline' : null,
            ! $blocked && $status === MatchStatus::Invited && $mine->decision === null ? 'accept' : null,
            ($status === MatchStatus::Invited && $mine->decision === MatchDecision::Accepted) || $status->isAccord() ? 'withdraw' : null,
            ! $blocked && $status->isAccord() && $mine->contact_consented_at === null ? 'grant_contact_consent' : null,
            $mine->contact_consented_at !== null ? 'revoke_contact_consent' : null,
            ! $blocked && $status->isAccord() && $mine->contact_consented_at !== null && $other->contact_consented_at !== null ? 'view_contact' : null,
            ! $blocked && $status->isAccord() ? 'send_message' : null,
            ! $blocked && $status->nextSteps() !== [] ? 'update_progress' : null,
            ! $blocked ? 'block' : null,
            $blockedByMe ? 'unblock' : null,
            'report',
        ]));
    }

    /**
     * @return array{code: string, name_fr: string, name_ar: string, active: bool}|null
     */
    private static function reference(?ReferenceEntry $entry): ?array
    {
        return $entry?->toReferenceArray();
    }
}
