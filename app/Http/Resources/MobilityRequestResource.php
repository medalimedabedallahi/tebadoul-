<?php

namespace App\Http\Resources;

use App\Models\MobilityRequest;
use App\Models\RequestDestination;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The owner's view of a mobility request, identified by its public ULID. `allowed_actions`
 * lists what the owner may do now, so clients never rebuild the lifecycle rules (ADR 0001).
 * Expects `destinations.wilaya`, `destinations.moughataa` and `destinations.establishment`
 * to be loaded.
 *
 * @mixin MobilityRequest
 */
class MobilityRequestResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var MobilityRequest $mobilityRequest */
        $mobilityRequest = $this->resource;

        return [
            'public_id' => $mobilityRequest->public_id,
            'status' => $mobilityRequest->status->value,
            'available_from' => $mobilityRequest->available_from->toDateString(),
            'expires_at' => $mobilityRequest->expires_at->toDateString(),
            'destinations' => $mobilityRequest->destinations->map(fn (RequestDestination $destination): array => [
                'priority' => $destination->priority,
                'wilaya' => $destination->wilaya->toReferenceArray(),
                'moughataa' => $destination->moughataa?->toReferenceArray(),
                'establishment' => $destination->establishment?->toReferenceArray(),
            ])->values()->all(),
            'published_at' => $mobilityRequest->published_at?->toIso8601String(),
            'closed_at' => $mobilityRequest->closed_at?->toIso8601String(),
            'close_reason' => $mobilityRequest->close_reason?->value,
            'allowed_actions' => $mobilityRequest->allowedActions(),
            'created_at' => $mobilityRequest->created_at?->toIso8601String(),
            'updated_at' => $mobilityRequest->updated_at?->toIso8601String(),
        ];
    }
}
