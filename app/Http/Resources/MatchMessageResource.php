<?php

namespace App\Http\Resources;

use App\Models\MatchMessage;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin MatchMessage */
class MatchMessageResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var MatchMessage $message */
        $message = $this->resource;

        return [
            'public_id' => $message->public_id,
            'from_me' => $message->sender_id === $request->user()?->getAuthIdentifier(),
            'body' => $message->body,
            'read_at' => $message->read_at?->toIso8601String(),
            'created_at' => $message->created_at?->toIso8601String(),
        ];
    }
}
