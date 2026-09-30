<?php

namespace App\Livewire\Admin\Reports;

use App\Actions\Trust\ListUserReports;
use App\Actions\Trust\ResolveUserReport;
use App\Enums\ReportStatus;
use App\Exceptions\Trust\InvalidReportTransitionException;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use WithPagination;

    #[Url(except: 'pending')]
    public string $status = 'pending';

    public ?string $targetReport = null;

    public string $decision = '';

    public string $resolutionNote = '';

    public ?string $decisionRecorded = null;

    public function updatedStatus(): void
    {
        $this->resetPage();
    }

    public function startDecision(string $report, string $decision): void
    {
        if (! in_array($decision, [ReportStatus::Resolved->value, ReportStatus::Dismissed->value], true)) {
            return;
        }

        $this->targetReport = $report;
        $this->decision = $decision;
        $this->resolutionNote = '';
        $this->decisionRecorded = null;
        $this->resetErrorBag();
    }

    public function cancelDecision(): void
    {
        $this->targetReport = null;
        $this->decision = '';
        $this->resolutionNote = '';
        $this->resetErrorBag();
    }

    public function confirmDecision(ResolveUserReport $resolve): void
    {
        if ($this->targetReport === null) {
            return;
        }

        $validated = Validator::make(['status' => $this->decision, 'resolution_note' => $this->resolutionNote], ResolveUserReport::rules())->validate();

        try {
            $resolve->handle($this->currentUser(), $this->targetReport, ReportStatus::from($validated['status']), $validated['resolution_note'], request()->ip());
        } catch (InvalidReportTransitionException) {
            $this->addError('resolution_note', __('moderation.errors.already_reviewed'));

            return;
        }

        $this->decisionRecorded = __('moderation.decision_recorded');
        $this->cancelDecision();
    }

    public function render(ListUserReports $reports): View
    {
        $status = $this->status === 'all'
            ? null
            : (ReportStatus::tryFrom($this->status) ?? ReportStatus::Pending);

        return view('livewire.admin.reports.index', [
            'reports' => $reports->handle($this->currentUser(), $status, $this->getPage(), 20),
            'statuses' => [
                'pending' => __('moderation.status.pending'),
                'resolved' => __('moderation.status.resolved'),
                'dismissed' => __('moderation.status.dismissed'),
                'all' => __('moderation.status.all'),
            ],
        ])->layout('components.layouts.app', ['title' => __('moderation.title')]);
    }

    private function currentUser(): User
    {
        /** @var User $user */
        $user = Auth::user();

        return $user;
    }
}
