<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Actions\Admin\ListAuditLogs;
use App\Enums\AuditAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\ListAuditLogsRequest;
use App\Http\Resources\AuditLogResource;
use App\Models\User;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class AuditLogIndexController extends Controller
{
    public function __invoke(ListAuditLogsRequest $request, ListAuditLogs $auditLogs): AnonymousResourceCollection
    {
        /** @var User $user */
        $user = $request->user();

        return AuditLogResource::collection($auditLogs->handle(
            $user,
            $request->enum('action', AuditAction::class),
            $request->filled('target_public_id') ? $request->string('target_public_id')->toString() : null,
            $request->integer('page', 1),
            $request->integer('per_page', 20),
        ));
    }
}
