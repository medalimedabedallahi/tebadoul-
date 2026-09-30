<?php

namespace App\Http\Resources;

use App\Models\MobilityMatch;
use App\Models\User;
use App\Support\Matching\MatchPresenter;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A match as the authenticated participant sees it; see {@see MatchPresenter}.
 *
 * @mixin MobilityMatch
 */
class MobilityMatchResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var MobilityMatch $match */
        $match = $this->resource;
        /** @var User $viewer */
        $viewer = $request->user();

        return MatchPresenter::forViewer($match, $viewer);
    }
}
