<?php

namespace App\Http\Resources;

use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * An audit entry, by public identifiers only. The internal id and the actor's IP address are not
 * exposed.
 *
 * @mixin AuditLog
 */
class AuditLogResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var AuditLog $entry */
        $entry = $this->resource;

        return [
            'action' => $entry->action->value,
            'actor_public_id' => $entry->actor->public_id,
            'target_user_public_id' => $entry->targetUser->public_id,
            'reason' => $entry->reason,
            'before' => $entry->before,
            'after' => $entry->after,
            'created_at' => $entry->created_at->toIso8601String(),
        ];
    }
}
