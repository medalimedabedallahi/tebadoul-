<?php

namespace App\Actions\Advertising;

use App\Enums\AdPlacement;
use App\Models\Advertisement;

/**
 * Picks the advertisement to show in a placement: one displayable advertisement of that placement,
 * for the visitor's language or for every language, drawn at random so several rotate.
 *
 * Needs no actor: it is shown to guests too. Nothing about the visitor is recorded.
 */
final class PickAdvertisement
{
    public function handle(AdPlacement $placement, string $locale): ?Advertisement
    {
        return Advertisement::query()
            ->select(Advertisement::SUMMARY_COLUMNS)
            ->displayable()
            ->where('placement', $placement)
            ->where(fn ($query) => $query->whereNull('locale')->orWhere('locale', $locale))
            ->inRandomOrder()
            ->first();
    }
}
