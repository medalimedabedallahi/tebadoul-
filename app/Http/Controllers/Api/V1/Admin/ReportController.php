<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Actions\Trust\ListUserReports;
use App\Actions\Trust\ResolveUserReport;
use App\Enums\ReportStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\ListUserReportsRequest;
use App\Http\Requests\Api\V1\Admin\ResolveUserReportRequest;
use App\Http\Resources\UserReportResource;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ReportController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(ListUserReportsRequest $request, ListUserReports $reports): AnonymousResourceCollection
    {
        return UserReportResource::collection($reports->handle(
            $this->user($request),
            $request->enum('status', ReportStatus::class),
            $request->integer('page', 1),
            $request->integer('per_page', 20),
        ));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(ResolveUserReportRequest $request, string $report, ResolveUserReport $resolve): UserReportResource
    {
        /** @var ReportStatus $status */
        $status = $request->enum('status', ReportStatus::class);

        return new UserReportResource($resolve->handle(
            $this->user($request),
            $report,
            $status,
            $request->string('resolution_note')->toString(),
            $request->ip(),
        ));
    }

    private function user(Request $request): User
    {
        /** @var User $user */
        $user = $request->user();

        return $user;
    }
}
