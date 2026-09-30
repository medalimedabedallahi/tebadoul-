<?php

namespace App\Livewire\MobilityRequests;

use App\Actions\MobilityRequests\SaveMobilityRequest;
use App\Actions\References\ListReferences;
use App\Contracts\ReferenceEntry;
use App\Enums\ReferenceType;
use App\Exceptions\MobilityRequests\InvalidRequestTransitionException;
use App\Models\MobilityRequest;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Livewire\Component;

/**
 * Creates a mobility request (draft) or edits one, through {@see SaveMobilityRequest} (same
 * action as the API, ADR 0002). Destinations are rows of wilaya + optional moughataa, in
 * priority order; the API additionally accepts an establishment.
 */
class Edit extends Component
{
    /**
     * Public ULID of the edited request; null when creating.
     */
    public ?string $publicId = null;

    public string $available_from = '';

    public string $expires_at = '';

    /**
     * @var list<array{wilaya: string, moughataa: string}>
     */
    public array $destinations = [];

    public function mount(?string $mobilityRequest = null): void
    {
        if ($mobilityRequest === null) {
            $this->available_from = today()->toDateString();
            $this->expires_at = today()->addMonths(6)->toDateString();
            $this->destinations = [['wilaya' => '', 'moughataa' => '']];

            return;
        }

        $request = $this->currentUser()->mobilityRequests()
            ->where('public_id', Str::lower($mobilityRequest))
            ->with(['destinations.wilaya', 'destinations.moughataa'])
            ->firstOrFail();

        abort_unless($request->status->isEditable(), 404);

        $this->publicId = $request->public_id;
        $this->available_from = $request->available_from->toDateString();
        $this->expires_at = $request->expires_at->toDateString();
        $this->destinations = $request->destinations->map(fn ($destination): array => [
            'wilaya' => $destination->wilaya->code,
            'moughataa' => $destination->moughataa->code ?? '',
        ])->values()->all();
    }

    /**
     * A new wilaya invalidates the moughataa chosen on the same row.
     */
    public function updatedDestinations(mixed $value, string $key): void
    {
        [$index, $field] = array_pad(explode('.', $key), 2, null);

        if ($field === 'wilaya' && isset($this->destinations[(int) $index])) {
            $this->destinations[(int) $index]['moughataa'] = '';
        }
    }

    public function addDestination(): void
    {
        if (count($this->destinations) < MobilityRequest::MAX_DESTINATIONS) {
            $this->destinations[] = ['wilaya' => '', 'moughataa' => ''];
        }
    }

    public function removeDestination(int $index): void
    {
        if (count($this->destinations) > 1) {
            unset($this->destinations[$index]);
            $this->destinations = array_values($this->destinations);
            $this->resetErrorBag();
        }
    }

    public function save(SaveMobilityRequest $saveRequest): void
    {
        $this->resetErrorBag();

        $input = [
            'available_from' => $this->available_from,
            'expires_at' => $this->expires_at,
            'destinations' => array_map(fn (array $destination): array => [
                'wilaya' => $destination['wilaya'],
                'moughataa' => $destination['moughataa'] === '' ? null : $destination['moughataa'],
            ], $this->destinations),
        ];

        try {
            $request = $saveRequest->handle($this->currentUser(), $input, $this->publicId);
        } catch (InvalidRequestTransitionException) {
            throw ValidationException::withMessages(['destinations' => __('requests.errors.not_allowed')]);
        }

        session()->flash('status', __('requests.show.saved'));
        session()->flash('status_type', 'success');

        $this->redirectRoute('requests.show', $request->public_id);
    }

    public function render(ListReferences $references): View
    {
        $moughataas = [];

        foreach ($this->destinations as $index => $destination) {
            $moughataas[$index] = $destination['wilaya'] === '' ? [] : $this->options(
                $references->handle(ReferenceType::Moughataas, ['wilaya_code' => $destination['wilaya']]),
            );
        }

        return view('livewire.mobility-requests.edit', [
            'wilayas' => $this->options($references->handle(ReferenceType::Wilayas)),
            'moughataas' => $moughataas,
            'maxDestinations' => MobilityRequest::MAX_DESTINATIONS,
        ])->layout('components.layouts.app', [
            'title' => $this->publicId === null ? __('requests.edit.create_title') : __('requests.edit.edit_title'),
            'description' => __('requests.edit.description'),
        ]);
    }

    /**
     * @param  iterable<ReferenceEntry>  $entries
     * @return array<string, string>
     */
    private function options(iterable $entries): array
    {
        $options = [];

        foreach ($entries as $entry) {
            $options[$entry->referenceCode()] = $entry->localizedName();
        }

        return $options;
    }

    private function currentUser(): User
    {
        /** @var User $user */
        $user = Auth::user();

        return $user;
    }
}
