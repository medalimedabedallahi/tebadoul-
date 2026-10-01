<?php

namespace App\Livewire\Admin\Advertisements;

use App\Actions\Advertising\DeleteAdvertisement;
use App\Actions\Advertising\ListAdvertisements;
use App\Actions\Advertising\SaveAdvertisement;
use App\Enums\AdPlacement;
use App\Models\Advertisement;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

/**
 * Administration of the advertising spaces: paginated list, create / edit form with the banner
 * upload, and deletion after an explicit confirmation.
 *
 * Guarded by `auth`, `account.active` and `can:viewAny` (see routes/web.php); the actions
 * ({@see SaveAdvertisement}, {@see DeleteAdvertisement}) authorize the actor again.
 */
class Index extends Component
{
    use WithFileUploads;
    use WithPagination;

    private const PER_PAGE = 20;

    /**
     * Public id of the advertisement being edited, `new` for a creation, null when the form is closed.
     */
    public ?string $editing = null;

    public string $title = '';

    public string $link_url = '';

    public string $placement = 'home';

    public string $locale = '';

    public bool $is_active = true;

    public string $starts_at = '';

    public string $ends_at = '';

    /**
     * @var TemporaryUploadedFile|null
     */
    public $image = null;

    /**
     * Public id of the advertisement whose deletion awaits confirmation.
     */
    public ?string $deleting = null;

    public ?string $notice = null;

    public function create(): void
    {
        $this->resetForm();
        $this->editing = 'new';
    }

    public function edit(string $publicId): void
    {
        $advertisement = Advertisement::query()
            ->select(Advertisement::SUMMARY_COLUMNS)
            ->where('public_id', Str::lower($publicId))
            ->first();

        if ($advertisement === null) {
            $this->notice = __('admin.advertisements.not_found');

            return;
        }

        Gate::forUser($this->currentUser())->authorize('update', $advertisement);

        $this->resetForm();
        $this->editing = $advertisement->public_id;
        $this->title = $advertisement->title;
        $this->link_url = (string) $advertisement->link_url;
        $this->placement = $advertisement->placement->value;
        $this->locale = (string) $advertisement->locale;
        $this->is_active = $advertisement->is_active;
        $this->starts_at = (string) $advertisement->starts_at?->format('Y-m-d\TH:i');
        $this->ends_at = (string) $advertisement->ends_at?->format('Y-m-d\TH:i');
    }

    public function cancel(): void
    {
        $this->resetForm();
    }

    public function save(SaveAdvertisement $saveAdvertisement): void
    {
        if ($this->editing === null) {
            return;
        }

        $creating = $this->editing === 'new';

        try {
            $saveAdvertisement->handle($this->currentUser(), [
                'title' => $this->title,
                'link_url' => $this->link_url,
                'placement' => $this->placement,
                'locale' => $this->locale,
                'is_active' => $this->is_active,
                'starts_at' => $this->starts_at,
                'ends_at' => $this->ends_at,
                'image' => $this->image,
            ], $creating ? null : $this->editing);
        } catch (ModelNotFoundException) {
            $this->resetForm();
            $this->notice = __('admin.advertisements.not_found');

            return;
        }

        $this->resetForm();
        $this->notice = __($creating ? 'admin.advertisements.created' : 'admin.advertisements.updated');
    }

    public function confirmDelete(string $publicId): void
    {
        $this->deleting = $publicId;
        $this->notice = null;
    }

    public function cancelDelete(): void
    {
        $this->deleting = null;
    }

    public function delete(DeleteAdvertisement $deleteAdvertisement): void
    {
        if ($this->deleting === null) {
            return;
        }

        try {
            $deleteAdvertisement->handle($this->currentUser(), $this->deleting);
            $this->notice = __('admin.advertisements.deleted');
        } catch (ModelNotFoundException) {
            $this->notice = __('admin.advertisements.not_found');
        }

        if ($this->editing === $this->deleting) {
            $this->resetForm();
        }

        $this->deleting = null;
    }

    public function render(ListAdvertisements $listAdvertisements): View
    {
        $editedAdvertisement = $this->editing !== null && $this->editing !== 'new'
            ? Advertisement::query()->select(Advertisement::SUMMARY_COLUMNS)->where('public_id', $this->editing)->first()
            : null;

        return view('livewire.admin.advertisements.index', [
            'advertisements' => $listAdvertisements->handle($this->currentUser(), $this->getPage(), self::PER_PAGE),
            'editedAdvertisement' => $editedAdvertisement,
            'placements' => collect(AdPlacement::cases())
                ->mapWithKeys(fn (AdPlacement $placement): array => [$placement->value => __('admin.advertisements.placements.'.$placement->value)])
                ->all(),
            'locales' => $this->localeOptions(),
            'imagePreviewUrl' => $this->image instanceof TemporaryUploadedFile && $this->image->isPreviewable()
                ? $this->image->temporaryUrl()
                : $editedAdvertisement?->imageUrl(),
        ])->layout('components.layouts.app', [
            'title' => __('admin.advertisements.title'),
            'description' => __('admin.advertisements.description'),
        ]);
    }

    /**
     * @return array<string, string>
     */
    private function localeOptions(): array
    {
        $options = ['' => __('admin.advertisements.all_locales')];

        foreach ((array) config('app.supported_locales', ['fr', 'ar']) as $code) {
            $options[(string) $code] = __('nav.languages.'.$code);
        }

        return $options;
    }

    private function resetForm(): void
    {
        $this->reset(['editing', 'title', 'link_url', 'placement', 'locale', 'is_active', 'starts_at', 'ends_at', 'image']);
        $this->resetErrorBag();
        $this->notice = null;
    }

    private function currentUser(): User
    {
        /** @var User $user */
        $user = Auth::user();

        return $user;
    }
}
