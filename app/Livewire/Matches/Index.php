<?php

namespace App\Livewire\Matches;

use App\Actions\Matching\ListMatches;
use App\Models\MobilityMatch;
use App\Models\User;
use App\Support\Matching\MatchPresenter;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * The signed-in account's matches, best score first, through {@see ListMatches} (same action as
 * `GET /api/v1/matches`). Each card is built by {@see MatchPresenter}, so the web shows exactly
 * what the API exposes about the other participant.
 */
class Index extends Component
{
    use WithPagination;

    public function render(ListMatches $listMatches): View
    {
        $user = $this->currentUser();
        $matches = $listMatches->handle($user, page: $this->getPage(), perPage: 10);

        return view('livewire.matches.index', [
            'matches' => $matches,
            'cards' => array_map(fn (MobilityMatch $match): array => MatchPresenter::forViewer($match, $user), $matches->items()),
            'matchingEnabled' => (bool) config('matching.enabled'),
            'origin' => $user->professionalProfile()->with('moughataa.wilaya')->first()?->moughataa,
        ])->layout('components.layouts.app', [
            'title' => __('matches.index.title'),
            'description' => __('matches.index.description'),
        ]);
    }

    private function currentUser(): User
    {
        /** @var User $user */
        $user = Auth::user();

        return $user;
    }
}
