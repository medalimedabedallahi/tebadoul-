<?php

namespace App\Livewire\Admin\Statistics;

use App\Actions\Admin\ComputePlatformStatistics;
use App\Models\Sector;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Livewire\Component;

/**
 * Administration dashboard (PRD 15.1): the aggregated figures of {@see ComputePlatformStatistics},
 * the same as `GET /api/v1/admin/statistics`.
 */
class Show extends Component
{
    public function render(ComputePlatformStatistics $statistics): View
    {
        /** @var User $user */
        $user = Auth::user();
        $figures = $statistics->handle($user);

        $sectorNames = Sector::query()
            ->whereIn('code', array_column($figures['requests']['by_sector'], 'code'))
            ->get()
            ->mapWithKeys(fn (Sector $sector): array => [$sector->code => $sector->localizedName()]);

        return view('livewire.admin.statistics.show', [
            'statistics' => $figures,
            'sectorNames' => $sectorNames,
            'matchRate' => $figures['requests']['published'] > 0
                ? (int) round(100 * $figures['requests']['with_match'] / $figures['requests']['published'])
                : null,
        ])->layout('components.layouts.app', [
            'title' => __('admin.statistics.title'),
            'description' => __('admin.statistics.description'),
        ]);
    }
}
