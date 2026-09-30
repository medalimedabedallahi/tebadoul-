<?php

namespace App\Livewire\MobilityRequests;

use App\Actions\MobilityRequests\CloseMobilityRequest;
use App\Actions\MobilityRequests\DeleteMobilityRequest;
use App\Actions\MobilityRequests\PauseMobilityRequest;
use App\Actions\MobilityRequests\PublishMobilityRequest;
use App\Actions\MobilityRequests\RenewMobilityRequest;
use App\Enums\MatchStatus;
use App\Enums\RequestCloseReason;
use App\Exceptions\MobilityRequests\InvalidRequestTransitionException;
use App\Exceptions\MobilityRequests\RequestNotPublishableException;
use App\Models\MobilityMatch;
use App\Models\MobilityRequest;
use App\Models\User;
use Closure;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Livewire\Component;

/**
 * One mobility request of the signed-in account, with the lifecycle operations its status
 * allows ({@see MobilityRequest::allowedActions()}), each through the same action as the API.
 *
 * A refused operation (not publishable, or not allowed in the current status) is shown as a
 * list of reasons rather than thrown: it is an expected outcome, not a bug.
 */
class Show extends Component
{
    public string $publicId;

    public string $renewExpiresAt = '';

    public string $closeReason = '';

    public bool $confirmingDelete = false;

    public ?string $actionMessage = null;

    /**
     * @var list<string>
     */
    public array $actionErrors = [];

    public function mount(string $mobilityRequest): void
    {
        $this->publicId = $this->findRequest(Str::lower($mobilityRequest))->public_id;
        $this->renewExpiresAt = today()->addMonths(6)->toDateString();
    }

    public function publish(PublishMobilityRequest $publish): void
    {
        $this->perform(fn (User $user) => $publish->handle($user, $this->publicId), 'requests.show.published');
    }

    public function pause(PauseMobilityRequest $pause): void
    {
        $this->perform(fn (User $user) => $pause->handle($user, $this->publicId), 'requests.show.paused');
    }

    public function renew(RenewMobilityRequest $renew): void
    {
        $this->perform(fn (User $user) => $renew->handle($user, $this->publicId, $this->renewExpiresAt), 'requests.show.renewed');
    }

    public function close(CloseMobilityRequest $close): void
    {
        $this->resetErrorBag();
        $validated = Validator::make(['reason' => $this->closeReason], CloseMobilityRequest::rules(), attributes: [
            'reason' => __('requests.fields.reason'),
        ])->validate();

        $this->perform(
            fn (User $user) => $close->handle($user, $this->publicId, RequestCloseReason::from($validated['reason'])),
            'requests.show.closed',
        );
    }

    public function delete(DeleteMobilityRequest $delete): void
    {
        $this->actionErrors = [];

        try {
            $delete->handle($this->currentUser(), $this->publicId);
        } catch (InvalidRequestTransitionException) {
            $this->actionErrors = [__('requests.errors.not_allowed')];

            return;
        }

        session()->flash('status', __('requests.show.deleted'));
        session()->flash('status_type', 'success');

        $this->redirectRoute('requests.index');
    }

    public function render(): View
    {
        $request = $this->findRequest($this->publicId);
        $profile = $this->currentUser()->professionalProfile()->with(['moughataa.wilaya', 'establishment', 'profession'])->first();

        return view('livewire.mobility-requests.show', [
            'request' => $request,
            'actions' => $request->allowedActions(),
            'profile' => $profile,
            'matchCount' => MobilityMatch::query()
                ->where('status', '!=', MatchStatus::Invalidated)
                ->whereHas('participants', fn ($participants) => $participants->where('mobility_request_id', $request->id))
                ->count(),
            'closeReasons' => collect(RequestCloseReason::cases())
                ->mapWithKeys(fn (RequestCloseReason $reason): array => [$reason->value => __('requests.close_reasons.'.$reason->value)])
                ->all(),
        ])->layout('components.layouts.app', [
            'title' => __('requests.show.title'),
        ]);
    }

    /**
     * @param  Closure(User): MobilityRequest  $operation
     */
    private function perform(Closure $operation, string $successKey): void
    {
        $this->resetErrorBag();
        $this->actionMessage = null;
        $this->actionErrors = [];

        try {
            $operation($this->currentUser());
            $this->actionMessage = __($successKey);
        } catch (RequestNotPublishableException $exception) {
            $this->actionErrors = array_merge(...array_values($exception->errors));
        } catch (InvalidRequestTransitionException) {
            $this->actionErrors = [__('requests.errors.not_allowed')];
        }
    }

    private function findRequest(string $publicId): MobilityRequest
    {
        return $this->currentUser()->mobilityRequests()
            ->where('public_id', $publicId)
            ->with(['destinations.wilaya', 'destinations.moughataa', 'destinations.establishment'])
            ->firstOrFail();
    }

    private function currentUser(): User
    {
        /** @var User $user */
        $user = Auth::user();

        return $user;
    }
}
