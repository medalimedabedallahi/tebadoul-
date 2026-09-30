<?php

namespace App\Livewire\MobilityRequests;

use App\Actions\MobilityRequests\ListMobilityRequests;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * The signed-in account's mobility requests, newest first, through {@see ListMobilityRequests}.
 * Guarded by `auth` and `account.active` (see routes/web.php).
 */
class Index extends Component
{
    use WithPagination;

    public function render(ListMobilityRequests $listRequests): View
    {
        $user = $this->currentUser();

        return view('livewire.mobility-requests.index', [
            'requests' => $listRequests->handle($user, $this->getPage(), 10),
            'profile' => $user->professionalProfile()->with('moughataa.wilaya')->first(),
        ])->layout('components.layouts.app', [
            'title' => __('requests.index.title'),
            'description' => __('requests.index.description'),
        ]);
    }

    private function currentUser(): User
    {
        /** @var User $user */
        $user = Auth::user();

        return $user;
    }
}
