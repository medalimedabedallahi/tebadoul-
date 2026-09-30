<div class="flex flex-col gap-8">
    <div class="flex max-w-prose flex-col gap-2">
        <h1 class="text-3xl font-bold text-ink">{{ __('admin.audit.heading') }}</h1>
        <p class="text-base text-ink-muted">{{ __('admin.audit.intro') }}</p>
    </div>

    <div class="flex flex-wrap items-end gap-4">
        <div class="w-full max-w-xs">
            <x-select name="audit_action" :label="__('admin.audit.filter_action')" wire:model.live="action" :options="$actions" />
        </div>

        <form wire:submit="applyTarget" role="search" class="flex min-w-64 flex-1 flex-wrap items-end gap-3" novalidate>
            <div class="min-w-64 flex-1">
                <x-input
                    name="audit_target"
                    label="{{ __('admin.audit.target_label') }}"
                    help="{{ __('admin.users.search_help') }}"
                    wire:model="targetInput"
                    autocomplete="off"
                    spellcheck="false"
                    dir="ltr"
                />
            </div>
            <x-button type="submit">{{ __('admin.audit.filter_submit') }}</x-button>
            @if ($action !== '' || $target !== '')
                <x-button variant="ghost" wire:click="clearFilters">{{ __('admin.audit.filter_clear') }}</x-button>
            @endif
        </form>
    </div>

    @if ($invalidTarget)
        <x-empty-state
            icon="alert-circle"
            :title="__('admin.users.invalid_search_title')"
            :description="__('admin.users.invalid_search_description')"
        />
    @elseif ($entries->isEmpty())
        <x-empty-state :title="__('admin.audit.empty_title')" :description="__('admin.audit.empty_description')" />
    @else
        <div class="overflow-x-auto rounded-card border border-line bg-surface shadow-card">
            <table class="w-full text-start text-base">
                <caption class="sr-only">{{ __('admin.audit.table_caption') }}</caption>
                <thead class="border-b border-line bg-canvas text-sm text-ink-muted">
                    <tr>
                        <th scope="col" class="px-4 py-3 text-start font-semibold">{{ __('admin.audit.column_date') }}</th>
                        <th scope="col" class="px-4 py-3 text-start font-semibold">{{ __('admin.audit.column_action') }}</th>
                        <th scope="col" class="px-4 py-3 text-start font-semibold">{{ __('admin.audit.column_actor') }}</th>
                        <th scope="col" class="px-4 py-3 text-start font-semibold">{{ __('admin.audit.column_target') }}</th>
                        <th scope="col" class="px-4 py-3 text-start font-semibold">{{ __('admin.audit.column_reason') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-line align-top">
                    @foreach ($entries as $entry)
                        <tr wire:key="audit-{{ $entries->firstItem() + $loop->index }}">
                            <td class="whitespace-nowrap px-4 py-3 text-sm text-ink">
                                <time datetime="{{ $entry->created_at->toIso8601String() }}">{{ $entry->created_at->translatedFormat('j F Y, H:i') }}</time>
                            </td>
                            <td class="px-4 py-3 text-ink">{{ __('admin.audit.actions.'.$entry->action->value) }}</td>
                            <td class="px-4 py-3 font-mono text-sm text-ink" dir="ltr">{{ $entry->actor->public_id }}</td>
                            <td class="px-4 py-3 font-mono text-sm text-ink" dir="ltr">{{ $entry->targetUser->public_id }}</td>
                            <td class="max-w-md px-4 py-3 text-ink"><p class="whitespace-pre-wrap break-words">{{ $entry->reason }}</p></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <x-pagination :paginator="$entries" />
    @endif
</div>
