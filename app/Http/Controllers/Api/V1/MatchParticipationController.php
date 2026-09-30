<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Matching\AcceptMatch;
use App\Actions\Matching\DeclineMatch;
use App\Actions\Matching\InviteToMatch;
use App\Actions\Matching\ListMatches;
use App\Actions\Matching\ShareContact;
use App\Actions\Matching\UpdateMatchProgress;
use App\Actions\Matching\WithdrawFromMatch;
use App\Enums\MatchDeclineReason;
use App\Enums\MatchStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Matches\DeclineMatchRequest;
use App\Http\Requests\Api\V1\Matches\UpdateMatchProgressRequest;
use App\Http\Resources\MobilityMatchResource;
use App\Models\MobilityMatch;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Participant operations on a direct match. Every operation delegates to the same transactional
 * business action used by Livewire; contact details are returned only by the dedicated reveal
 * endpoint after both consents have been checked and the access has been audited.
 */
class MatchParticipationController extends Controller
{
    public function invite(Request $request, string $match, InviteToMatch $invite): MobilityMatchResource
    {
        return $this->present($invite->handle($this->user($request), $match));
    }

    public function accept(Request $request, string $match, AcceptMatch $accept): MobilityMatchResource
    {
        return $this->present($accept->handle($this->user($request), $match));
    }

    public function decline(DeclineMatchRequest $request, string $match, DeclineMatch $decline): MobilityMatchResource
    {
        return $this->present($decline->handle(
            $this->user($request),
            $match,
            $request->enum('reason', MatchDeclineReason::class),
        ));
    }

    public function withdraw(Request $request, string $match, WithdrawFromMatch $withdraw): MobilityMatchResource
    {
        return $this->present($withdraw->handle($this->user($request), $match));
    }

    public function grantContactConsent(Request $request, string $match, ShareContact $shareContact): MobilityMatchResource
    {
        return $this->present($shareContact->grant($this->user($request), $match, $request->ip()));
    }

    public function revokeContactConsent(Request $request, string $match, ShareContact $shareContact): MobilityMatchResource
    {
        return $this->present($shareContact->revoke($this->user($request), $match, $request->ip()));
    }

    public function contact(Request $request, string $match, ShareContact $shareContact): JsonResponse
    {
        return response()->json([
            'data' => $shareContact->reveal($this->user($request), $match, $request->ip()),
        ]);
    }

    public function progress(UpdateMatchProgressRequest $request, string $match, UpdateMatchProgress $progress): MobilityMatchResource
    {
        /** @var MatchStatus $status */
        $status = $request->enum('status', MatchStatus::class);

        return $this->present($progress->handle($this->user($request), $match, $status));
    }

    private function present(MobilityMatch $match): MobilityMatchResource
    {
        return new MobilityMatchResource($match->load(ListMatches::PRESENTATION));
    }

    private function user(Request $request): User
    {
        /** @var User $user */
        $user = $request->user();

        return $user;
    }
}
