<?php

namespace App\Livewire\Profile;

use App\Actions\Profiles\SaveProfessionalProfile;
use App\Actions\References\ListReferences;
use App\Contracts\ReferenceEntry;
use App\Enums\ReferenceType;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;
use Livewire\Component;

/**
 * Web form of the professional profile, with dependent lists: changing the sector resets the
 * profession, changing the profession resets the specialty and the grade, and so on.
 *
 * Guarded by `auth` and `account.active` (see routes/web.php). Lists come from
 * {@see ListReferences} and the profile is saved by {@see SaveProfessionalProfile}, the same
 * action as `PUT /api/v1/me/profile`, which re-checks every reference (ADR 0002).
 */
class Edit extends Component
{
    public string $sector = '';

    public string $profession = '';

    public string $specialty = '';

    public string $grade = '';

    public string $wilaya = '';

    public string $moughataa = '';

    public string $establishment = '';

    public string $professional_identifier = '';

    /**
     * Shown after a successful save; a session flash would not do (Livewire re-renders only this
     * component's own view on that request, never the surrounding layout).
     */
    public ?string $profileSaved = null;

    public function mount(): void
    {
        $profile = $this->currentUser()->professionalProfile()
            ->with(['sector', 'profession', 'specialty', 'grade', 'moughataa.wilaya', 'establishment'])
            ->first();

        if ($profile === null) {
            return;
        }

        $this->sector = $profile->sector->code;
        $this->profession = $profile->profession->code;
        $this->specialty = $profile->specialty->code ?? '';
        $this->grade = $profile->grade->code ?? '';
        $this->wilaya = $profile->moughataa->wilaya->code;
        $this->moughataa = $profile->moughataa->code;
        $this->establishment = $profile->establishment->code ?? '';
        $this->professional_identifier = (string) $profile->professional_identifier;
    }

    public function updatedSector(): void
    {
        $this->reset('profession', 'specialty', 'grade', 'establishment');
    }

    public function updatedProfession(): void
    {
        $this->reset('specialty', 'grade');
    }

    public function updatedWilaya(): void
    {
        $this->reset('moughataa', 'establishment');
    }

    public function updatedMoughataa(): void
    {
        $this->reset('establishment');
    }

    public function save(SaveProfessionalProfile $saveProfile): void
    {
        $this->profileSaved = null;
        // Validator::make() does not clear Livewire's error bag the way $this->validate() does.
        $this->resetErrorBag();

        $validated = Validator::make(
            $this->only(array_keys(SaveProfessionalProfile::rules())),
            SaveProfessionalProfile::rules(),
            attributes: __('profile.fields'),
        )->validate();

        $saveProfile->handle($this->currentUser(), $validated);

        $this->profileSaved = __('profile.saved');
    }

    public function render(ListReferences $references): View
    {
        return view('livewire.profile.edit', [
            'sectors' => $this->options($references->handle(ReferenceType::Sectors)),
            'professions' => $this->sector === '' ? [] : $this->options($references->handle(ReferenceType::Professions, ['sector_code' => $this->sector])),
            'specialties' => $this->profession === '' ? [] : $this->options($references->handle(ReferenceType::Specialties, ['profession_code' => $this->profession])),
            'grades' => $this->profession === '' ? [] : $this->options($references->handle(ReferenceType::Grades, ['profession_code' => $this->profession])),
            'wilayas' => $this->options($references->handle(ReferenceType::Wilayas)),
            'moughataas' => $this->wilaya === '' ? [] : $this->options($references->handle(ReferenceType::Moughataas, ['wilaya_code' => $this->wilaya])),
            'establishments' => $this->sector === '' || $this->moughataa === '' ? [] : $this->options(
                $references->establishments(sectorCode: $this->sector, moughataaCode: $this->moughataa, perPage: 500)->items(),
            ),
        ])->layout('components.layouts.app', [
            'title' => __('profile.title'),
            'description' => __('profile.description'),
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
