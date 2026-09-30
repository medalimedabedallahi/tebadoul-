<?php

namespace App\Livewire\Admin\AuditLogs;

use App\Actions\Admin\ListAuditLogs;
use App\Enums\AuditAction;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Read-only view of the audit trail (PRD FR 019), the same as `GET /api/v1/admin/audit-logs`.
 */
class Index extends Component
{
    use WithPagination;

    #[Url(except: '')]
    public string $action = '';

    #[Url(as: 'compte', except: '')]
    public string $target = '';

    public string $targetInput = '';

    public function mount(): void
    {
        $this->targetInput = $this->target;
    }

    public function updatedAction(): void
    {
        $this->resetPage();
    }

    public function applyTarget(): void
    {
        $this->target = trim($this->targetInput);
        $this->resetPage();
    }

    public function clearFilters(): void
    {
        $this->action = '';
        $this->target = '';
        $this->targetInput = '';
        $this->resetPage();
    }

    public function render(ListAuditLogs $auditLogs): View
    {
        $invalidTarget = $this->target !== ''
            && Validator::make(['target_public_id' => $this->target], ['target_public_id' => ListAuditLogs::rules()['target_public_id']])->fails();

        /** @var User $user */
        $user = Auth::user();
        $entries = $auditLogs->handle(
            $user,
            AuditAction::tryFrom($this->action),
            $this->target !== '' && ! $invalidTarget ? $this->target : null,
            $this->getPage(),
            20,
        );

        $actions = ['' => __('admin.audit.actions.all')];

        foreach (AuditAction::cases() as $case) {
            $actions[$case->value] = __('admin.audit.actions.'.$case->value);
        }

        return view('livewire.admin.audit-logs.index', [
            'entries' => $entries,
            'actions' => $actions,
            'invalidTarget' => $invalidTarget,
        ])->layout('components.layouts.app', [
            'title' => __('admin.audit.title'),
            'description' => __('admin.audit.description'),
        ]);
    }
}
