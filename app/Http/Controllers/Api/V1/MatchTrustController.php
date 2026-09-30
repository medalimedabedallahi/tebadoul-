<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Matching\ListMatches;
use App\Actions\Trust\BlockUser;
use App\Actions\Trust\CreateUserReport;
use App\Actions\Trust\UnblockUser;
use App\Enums\ReportReason;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Trust\StoreUserReportRequest;
use App\Http\Resources\MobilityMatchResource;
use App\Http\Resources\UserReportResource;
use App\Models\User;
use Illuminate\Http\Request;

class MatchTrustController extends Controller
{
    public function block(Request $request, string $match, BlockUser $block, ListMatches $matches): MobilityMatchResource
    {
        $user = $this->user($request);
        $block->handle($user, $match);

        return new MobilityMatchResource($matches->find($user, $match));
    }

    public function unblock(Request $request, string $match, UnblockUser $unblock, ListMatches $matches): MobilityMatchResource
    {
        $user = $this->user($request);
        $unblock->handle($user, $match);

        return new MobilityMatchResource($matches->find($user, $match));
    }

    public function report(StoreUserReportRequest $request, string $match, CreateUserReport $create): UserReportResource
    {
        /** @var ReportReason $reason */
        $reason = $request->enum('reason', ReportReason::class);
        $report = $create->handle($this->user($request), $match, $reason, $request->string('details')->toString());

        return new UserReportResource($report->load(['mobilityMatch:id,public_id', 'reporter:id,public_id', 'reportedUser:id,public_id', 'moderator:id,public_id']));
    }

    private function user(Request $request): User
    {
        /** @var User $user */
        $user = $request->user();

        return $user;
    }
}
