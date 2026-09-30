@php
    $statusTones = [
        \App\Enums\UserStatus::Active->value => 'success',
        \App\Enums\UserStatus::PendingVerification->value => 'warning',
        \App\Enums\UserStatus::Suspended->value => 'danger',
        \App\Enums\UserStatus::Deleted->value => 'neutral',
    ];
@endphp

<div class="flex flex-col gap-8">
    <div class="flex flex-col gap-2">
        <h1 class="text-3xl font-bold text-ink">{{ __('admin.users.heading') }}</h1>
        <p class="max-w-prose text-base text-ink-muted">{{ __('admin.users.intro') }}</p>
    </div>

    @if ($decisionRecorded)
        <x-alert type="success">{{ $decisionRecorded }}</x-alert>
    @endif

    <form wire:submit="applySearch" role="search" class="flex flex-wrap items-end gap-3" novalidate>
        <div class="min-w-64 flex-1">
            <x-input
                name="search"
                label="{{ __('admin.users.search_label') }}"
                help="{{ __('admin.users.search_help') }}"
                wire:model="search"
                autocomplete="off"
                spellcheck="false"
                dir="ltr"
            />
        </div>
        <x-button type="submit">{{ __('admin.users.search_submit') }}</x-button>
        @if ($search !== '')
            <x-button variant="ghost" wire:click="clearSearch">{{ __('admin.users.search_clear') }}</x-button>
        @endif
    </form>

    @if ($invalidSearch)
        <x-empty-state
            icon="alert-circle"
            :title="__('admin.users.invalid_search_title')"
            :description="__('admin.users.invalid_search_description')"
        />
    @elseif ($users->isEmpty())
        <x-empty-state
            :title="__('admin.users.empty_title')"
            :description="$search !== '' ? __('admin.users.empty_search_description') : __('admin.users.empty_description')"
        />
    @else
        <div class="overflow-x-auto rounded-card border border-line bg-surface shadow-card">
            <table class="w-full text-start text-base">
                <caption class="sr-only">{{ __('admin.users.table_caption') }}</caption>
                <thead class="border-b border-line bg-canvas text-sm text-ink-muted">
                    <tr>
                        <th scope="col" class="px-4 py-3 text-start font-semibold">{{ __('admin.users.column_public_id') }}</th>
                        <th scope="col" class="px-4 py-3 text-start font-semibold">{{ __('admin.users.column_status') }}</th>
                        <th scope="col" class="px-4 py-3 text-end font-semibold">{{ __('admin.users.column_actions') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-line">
                    @foreach ($users as $user)
                        @php($isSuspended = $user->status === \App\Enums\UserStatus::Suspended)
                        <tr wire:key="user-{{ $user->public_id }}">
                            <td class="px-4 py-3 font-mono text-sm text-ink" dir="ltr">{{ $user->public_id }}</td>
                            <td class="px-4 py-3">
                                <x-status-badge :tone="$statusTones[$user->status->value] ?? 'neutral'">
                                    {{ __('auth.account.status.'.$user->status->value) }}
                                </x-status-badge>
                            </td>
                            <td class="px-4 py-3 text-end">
                                @if ($user->status === \App\Enums\UserStatus::Deleted)
                                    {{-- Anonymized and final: nothing to suspend or reinstate. --}}
                                @elseif ($isSuspended)
                                    <x-button
                                        variant="secondary"
                                        wire:click="startDecision('{{ $user->public_id }}', 'reinstate')"
                                        aria-label="{{ __('admin.users.reinstate_for', ['id' => $user->public_id]) }}"
                                    >
                                        {{ __('admin.users.reinstate') }}
                                    </x-button>
                                @else
                                    <x-button
                                        variant="danger"
                                        wire:click="startDecision('{{ $user->public_id }}', 'suspend')"
                                        aria-label="{{ __('admin.users.suspend_for', ['id' => $user->public_id]) }}"
                                    >
                                        {{ __('admin.users.suspend') }}
                                    </x-button>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <x-pagination :paginator="$users" />
    @endif

    @if ($targetPublicId && $decision)
        <x-card
            :heading="__('admin.users.'.$decision.'_heading')"
            level="2"
            wire:key="decision-{{ $targetPublicId }}-{{ $decision }}"
        >
            <p class="mb-5">
                {{ __('admin.users.'.$decision.'_explanation') }}
                <span class="font-mono text-sm text-ink" dir="ltr">{{ $targetPublicId }}</span>
            </p>

            <form wire:submit="confirmDecision" class="flex flex-col gap-5" novalidate>
                <x-textarea
                    name="reason"
                    label="{{ __('admin.users.reason_label') }}"
                    help="{{ __('admin.users.reason_help') }}"
                    wire:model="reason"
                    maxlength="1000"
                    required
                />

                <div class="flex flex-wrap gap-3">
                    <x-button
                        type="submit"
                        :variant="$decision === 'suspend' ? 'danger' : 'primary'"
                        wire:loading.attr="disabled"
                        wire:target="confirmDecision"
                    >
                        <span wire:loading.remove wire:target="confirmDecision">{{ __('admin.users.'.$decision.'_confirm') }}</span>
                        <span wire:loading wire:target="confirmDecision" class="inline-flex items-center gap-2">
                            <x-icon name="spinner" class="motion-safe:animate-spin" />
                            {{ __('common.loading') }}
                        </span>
                    </x-button>
                    <x-button variant="secondary" wire:click="cancelDecision">{{ __('admin.users.cancel') }}</x-button>
                </div>
            </form>
        </x-card>
    @endif
</div>
