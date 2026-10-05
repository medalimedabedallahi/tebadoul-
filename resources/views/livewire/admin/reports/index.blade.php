<div class="flex flex-col gap-8">
    <div class="flex max-w-prose flex-col gap-2">
        <h1 class="text-3xl font-bold text-ink">{{ __('moderation.heading') }}</h1>
        <p class="text-base text-ink-muted">{{ __('moderation.intro') }}</p>
    </div>

    @if ($decisionRecorded)
        <x-alert type="success">{{ $decisionRecorded }}</x-alert>
    @endif

    <div class="max-w-sm">
        <x-select name="status_filter" :label="__('moderation.filter')" wire:model.live="status" :options="$statuses" />
    </div>

    @if ($reports->isEmpty())
        <x-empty-state :title="__('moderation.empty_title')" :description="__('moderation.empty_description')" />
    @else
        <ol class="flex flex-col gap-5">
            @foreach ($reports as $report)
                <li wire:key="report-{{ $report->public_id }}">
                    <x-card>
                        <div class="flex flex-col gap-4">
                            <div class="flex flex-wrap items-start justify-between gap-3">
                                <div>
                                    <p class="font-mono text-xs text-ink-muted" dir="ltr">{{ $report->public_id }}</p>
                                    <h2 class="text-lg font-semibold text-ink">{{ __('moderation.reasons.'.$report->reason->value) }}</h2>
                                </div>
                                <x-status-badge :tone="$report->status->value === 'pending' ? 'warning' : ($report->status->value === 'resolved' ? 'success' : 'neutral')">
                                    {{ __('moderation.status.'.$report->status->value) }}
                                </x-status-badge>
                            </div>

                            <dl class="grid gap-3 text-sm sm:grid-cols-2">
                                <div><dt class="font-semibold text-ink-muted">{{ __('moderation.reporter') }}</dt><dd class="font-mono text-ink" dir="ltr">{{ $report->reporter->public_id }}</dd></div>
                                <div><dt class="font-semibold text-ink-muted">{{ __('moderation.reported') }}</dt><dd class="font-mono text-ink" dir="ltr">{{ $report->reportedUser->public_id }}</dd></div>
                                <div><dt class="font-semibold text-ink-muted">{{ __('moderation.match') }}</dt><dd class="font-mono text-ink" dir="ltr">{{ $report->mobilityMatch->public_id }}</dd></div>
                                <div><dt class="font-semibold text-ink-muted">{{ __('moderation.created_at') }}</dt><dd class="text-ink">{{ $report->created_at?->translatedFormat('j F Y, H:i') }}</dd></div>
                            </dl>

                            @if ($report->details)
                                <div class="rounded-control border border-line bg-canvas p-4">
                                    <h3 class="text-sm font-semibold text-ink-muted">{{ __('moderation.details') }}</h3>
                                    <p class="whitespace-pre-wrap break-words text-base text-ink">{{ $report->details }}</p>
                                </div>
                            @endif

                            @if ($report->status->value === 'pending')
                                <div class="flex flex-wrap gap-3">
                                    <x-button wire:click="startDecision('{{ $report->public_id }}', 'resolved')">{{ __('moderation.resolve') }}</x-button>
                                    <x-button variant="secondary" wire:click="startDecision('{{ $report->public_id }}', 'dismissed')">{{ __('moderation.dismiss') }}</x-button>
                                </div>
                            @elseif ($report->resolution_note)
                                <x-alert type="info" :title="__('moderation.resolution')">{{ $report->resolution_note }}</x-alert>
                            @endif

                            @if ($targetReport === $report->public_id)
                                <form method="post" wire:submit="confirmDecision" class="flex flex-col gap-3 border-t border-line pt-4" novalidate>
                                    <x-textarea name="resolution_note" :label="__('moderation.resolution_note')" wire:model="resolutionNote" required />
                                    <div class="flex flex-wrap gap-3">
                                        <x-button type="submit" wire:loading.attr="disabled" wire:target="confirmDecision">{{ __('moderation.confirm') }}</x-button>
                                        <x-button variant="ghost" wire:click="cancelDecision">{{ __('moderation.cancel') }}</x-button>
                                    </div>
                                </form>
                            @endif
                        </div>
                    </x-card>
                </li>
            @endforeach
        </ol>

        <x-pagination :paginator="$reports" />
    @endif
</div>
