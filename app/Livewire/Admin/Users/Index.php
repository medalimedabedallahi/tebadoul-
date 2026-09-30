<?php

namespace App\Livewire\Admin\Users;

use App\Actions\Admin\ListUsers;
use App\Actions\Auth\ReinstateUser;
use App\Actions\Auth\SuspendUser;
use App\Exceptions\Admin\AccountNotSuspendedException;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Administration of ordinary accounts: exact search by public ULID, paginated list, and the
 * audited suspension / reinstatement of an account with a mandatory reason.
 *
 * Guarded by `auth`, `account.active` and `can:viewAny` (see routes/web.php). Calls the same
 * actions as the `/api/v1/admin` endpoints ({@see ListUsers}, {@see SuspendUser},
 * {@see ReinstateUser}), per ADR 0002; each action authorizes the actor again. Like the API, only
 * the public ULID and the status are shown: no name, contact or internal identifier.
 */
class Index extends Component
{
    use WithPagination;

    private const PER_PAGE = 20;

    #[Url(as: 'compte', except: '')]
    public string $search = '';

    /**
     * Public ULID of the account the pending decision targets, if a decision form is open.
     */
    public ?string $targetPublicId = null;

    /**
     * `suspend` or `reinstate`, for the open decision form.
     */
    public ?string $decision = null;

    public string $reason = '';

    /**
     * Shown after a decision; a session flash would not do (Livewire re-renders only this
     * component's own view on that request, never the surrounding layout).
     */
    public ?string $decisionRecorded = null;

    public function applySearch(): void
    {
        $this->search = trim($this->search);
        $this->resetPage();
    }

    public function clearSearch(): void
    {
        $this->search = '';
        $this->resetPage();
    }

    public function startDecision(string $publicId, string $decision): void
    {
        if (! in_array($decision, ['suspend', 'reinstate'], true)) {
            return;
        }

        $this->targetPublicId = $publicId;
        $this->decision = $decision;
        $this->reason = '';
        $this->decisionRecorded = null;
        $this->resetErrorBag();
    }

    public function cancelDecision(): void
    {
        $this->targetPublicId = null;
        $this->decision = null;
        $this->reason = '';
        $this->resetErrorBag();
    }

    public function confirmDecision(SuspendUser $suspendUser, ReinstateUser $reinstateUser): void
    {
        // Both properties are client-editable: re-check them rather than trusting startDecision().
        if ($this->targetPublicId === null || ! in_array($this->decision, ['suspend', 'reinstate'], true)) {
            return;
        }

        $this->resetErrorBag();
        $validated = Validator::make(['reason' => $this->reason], SuspendUser::rules())->validate();
        $reason = trim($validated['reason']);

        try {
            if ($this->decision === 'suspend') {
                $suspendUser->handle($this->currentUser(), $this->targetPublicId, $reason, request()->ip());
            } else {
                $reinstateUser->handle($this->currentUser(), $this->targetPublicId, $reason, request()->ip());
            }
        } catch (ModelNotFoundException) {
            throw ValidationException::withMessages(['reason' => __('admin.users.not_found')]);
        } catch (AccountNotSuspendedException) {
            throw ValidationException::withMessages(['reason' => __('admin.users.not_suspended')]);
        }

        $this->decisionRecorded = __('admin.users.'.$this->decision.'_done');
        $this->targetPublicId = null;
        $this->decision = null;
        $this->reason = '';
    }

    public function render(ListUsers $listUsers): View
    {
        $invalidSearch = $this->search !== '' && Validator::make(
            ['public_id' => $this->search],
            ['public_id' => ListUsers::rules()['public_id']],
        )->fails();

        return view('livewire.admin.users.index', [
            'invalidSearch' => $invalidSearch,
            'users' => $invalidSearch ? null : $listUsers->handle(
                actor: $this->currentUser(),
                publicId: $this->search !== '' ? $this->search : null,
                page: $this->getPage(),
                perPage: self::PER_PAGE,
            ),
        ])->layout('components.layouts.app', [
            'title' => __('admin.users.title'),
            'description' => __('admin.users.description'),
        ]);
    }

    private function currentUser(): User
    {
        /** @var User $user */
        $user = Auth::user();

        return $user;
    }
}
