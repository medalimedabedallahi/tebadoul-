@php
    $now = now();
    $stateOf = function (\App\Models\Advertisement $advertisement) use ($now): string {
        if (! $advertisement->is_active) {
            return 'inactive';
        }
        if ($advertisement->ends_at !== null && $advertisement->ends_at->lte($now)) {
            return 'ended';
        }
        if ($advertisement->starts_at !== null && $advertisement->starts_at->gt($now)) {
            return 'scheduled';
        }

        return 'live';
    };
    $stateTones = ['live' => 'success', 'scheduled' => 'info', 'ended' => 'neutral', 'inactive' => 'warning'];
@endphp

<div class="flex flex-col gap-8">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div class="flex max-w-prose flex-col gap-2">
            <h1 class="text-3xl font-bold text-ink">{{ __('admin.advertisements.heading') }}</h1>
            <p class="text-base text-ink-muted">{{ __('admin.advertisements.intro') }}</p>
        </div>
        @if ($editing === null)
            <x-button wire:click="create">{{ __('admin.advertisements.create') }}</x-button>
        @endif
    </div>

    @if ($notice)
        <x-alert type="success">{{ $notice }}</x-alert>
    @endif

    @if ($editing !== null)
        <x-card
            :heading="__($editing === 'new' ? 'admin.advertisements.create_heading' : 'admin.advertisements.edit_heading')"
            level="2"
            wire:key="advertisement-form-{{ $editing }}"
        >
            <form method="post" wire:submit="save" class="flex flex-col gap-5" novalidate>
                <p class="text-sm text-ink-muted">{{ __('common.required_legend') }}</p>

                <x-input
                    name="title"
                    label="{{ __('admin.advertisements.fields.title') }}"
                    help="{{ __('admin.advertisements.help.title') }}"
                    wire:model="title"
                    maxlength="120"
                    required
                />

                <x-input
                    name="link_url"
                    type="url"
                    label="{{ __('admin.advertisements.fields.link_url') }}"
                    help="{{ __('admin.advertisements.help.link_url') }}"
                    wire:model="link_url"
                    inputmode="url"
                    dir="ltr"
                />

                <div class="grid gap-5 sm:grid-cols-2">
                    <x-select
                        name="placement"
                        label="{{ __('admin.advertisements.fields.placement') }}"
                        :options="$placements"
                        wire:model="placement"
                        required
                    />
                    <x-select
                        name="locale"
                        label="{{ __('admin.advertisements.fields.locale') }}"
                        :options="$locales"
                        wire:model="locale"
                    />
                </div>

                <fieldset class="flex flex-col gap-3" aria-describedby="period-help">
                    <legend class="text-base font-semibold text-ink">{{ __('admin.advertisements.fields.period') }}</legend>
                    <p id="period-help" class="text-sm text-ink-muted">{{ __('admin.advertisements.help.period') }}</p>
                    <div class="grid gap-5 sm:grid-cols-2">
                        <x-input
                            name="starts_at"
                            type="datetime-local"
                            label="{{ __('admin.advertisements.fields.starts_at') }}"
                            wire:model="starts_at"
                        />
                        <x-input
                            name="ends_at"
                            type="datetime-local"
                            label="{{ __('admin.advertisements.fields.ends_at') }}"
                            wire:model="ends_at"
                        />
                    </div>
                </fieldset>

                <x-checkbox
                    name="is_active"
                    label="{{ __('admin.advertisements.fields.is_active') }}"
                    help="{{ __('admin.advertisements.help.is_active') }}"
                    wire:model="is_active"
                />

                <div class="flex flex-col gap-3">
                    <x-input
                        name="image"
                        type="file"
                        label="{{ __('admin.advertisements.fields.image') }}"
                        help="{{ __($editing === 'new' ? 'admin.advertisements.help.image' : 'admin.advertisements.help.image_replace', ['size' => \App\Actions\Advertising\SaveAdvertisement::MAX_IMAGE_KILOBYTES / 1024]) }}"
                        wire:model="image"
                        accept="image/png,image/jpeg,image/webp"
                        :required="$editing === 'new'"
                    />
                    <p wire:loading wire:target="image" class="inline-flex items-center gap-2 text-sm text-ink-muted">
                        <x-icon name="spinner" class="motion-safe:animate-spin" />
                        {{ __('common.loading') }}
                    </p>
                    @if ($imagePreviewUrl)
                        {{-- Same frame as <x-ad-slot>, so the preview shows what visitors will see. --}}
                        <figure class="flex w-full max-w-3xl flex-col gap-2">
                            <div class="w-full overflow-hidden rounded-control bg-surface ring-1 ring-line {{ $placement === 'footer' ? 'aspect-[8/1]' : 'aspect-[4/1]' }}">
                                <img src="{{ $imagePreviewUrl }}" alt="" class="size-full object-contain">
                            </div>
                            <figcaption class="text-sm text-ink-muted">{{ __('admin.advertisements.preview') }}</figcaption>
                        </figure>
                    @endif
                </div>

                <div class="flex flex-wrap gap-3">
                    <x-button type="submit" wire:loading.attr="disabled" wire:target="save,image">
                        <span wire:loading.remove wire:target="save">{{ __('admin.advertisements.save') }}</span>
                        <span wire:loading wire:target="save" class="inline-flex items-center gap-2">
                            <x-icon name="spinner" class="motion-safe:animate-spin" />
                            {{ __('common.loading') }}
                        </span>
                    </x-button>
                    <x-button variant="secondary" wire:click="cancel">{{ __('admin.advertisements.cancel') }}</x-button>
                </div>
            </form>
        </x-card>
    @endif

    @if ($deleting)
        <x-card :heading="__('admin.advertisements.delete_heading')" level="2" wire:key="delete-{{ $deleting }}">
            <p class="mb-5">{{ __('admin.advertisements.delete_explanation') }}</p>
            <div class="flex flex-wrap gap-3">
                <x-button variant="danger" wire:click="delete" wire:loading.attr="disabled" wire:target="delete">
                    {{ __('admin.advertisements.delete_confirm') }}
                </x-button>
                <x-button variant="secondary" wire:click="cancelDelete">{{ __('admin.advertisements.cancel') }}</x-button>
            </div>
        </x-card>
    @endif

    @if ($advertisements->isEmpty())
        <x-empty-state
            :title="__('admin.advertisements.empty_title')"
            :description="__('admin.advertisements.empty_description')"
        />
    @else
        <div class="overflow-x-auto rounded-card border border-line bg-surface shadow-card">
            <table class="w-full text-start text-base">
                <caption class="sr-only">{{ __('admin.advertisements.table_caption') }}</caption>
                <thead class="border-b border-line bg-canvas text-sm text-ink-muted">
                    <tr>
                        <th scope="col" class="px-4 py-3 text-start font-semibold">{{ __('admin.advertisements.column_advertisement') }}</th>
                        <th scope="col" class="px-4 py-3 text-start font-semibold">{{ __('admin.advertisements.fields.placement') }}</th>
                        <th scope="col" class="px-4 py-3 text-start font-semibold">{{ __('admin.advertisements.column_period') }}</th>
                        <th scope="col" class="px-4 py-3 text-start font-semibold">{{ __('admin.advertisements.column_status') }}</th>
                        <th scope="col" class="px-4 py-3 text-end font-semibold">{{ __('admin.advertisements.column_actions') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-line">
                    @foreach ($advertisements as $advertisement)
                        @php($state = $stateOf($advertisement))
                        <tr wire:key="advertisement-{{ $advertisement->public_id }}">
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-3">
                                    <img src="{{ $advertisement->imageUrl() }}" alt="" class="h-12 w-28 shrink-0 rounded-control border border-line object-cover">
                                    <div class="flex min-w-0 flex-col">
                                        <span class="font-semibold text-ink">{{ $advertisement->title }}</span>
                                        <span class="text-sm text-ink-muted">{{ $locales[$advertisement->locale ?? ''] ?? $advertisement->locale }}</span>
                                    </div>
                                </div>
                            </td>
                            <td class="px-4 py-3">{{ $placements[$advertisement->placement->value] }}</td>
                            <td class="px-4 py-3 text-sm text-ink-soft">
                                @if ($advertisement->starts_at || $advertisement->ends_at)
                                    <span class="block">{{ __('admin.advertisements.period_from', ['date' => $advertisement->starts_at?->translatedFormat('j M Y H:i') ?? __('admin.advertisements.period_open')]) }}</span>
                                    <span class="block">{{ __('admin.advertisements.period_until', ['date' => $advertisement->ends_at?->translatedFormat('j M Y H:i') ?? __('admin.advertisements.period_open')]) }}</span>
                                @else
                                    {{ __('admin.advertisements.period_always') }}
                                @endif
                            </td>
                            <td class="px-4 py-3">
                                <x-status-badge :tone="$stateTones[$state]">{{ __('admin.advertisements.states.'.$state) }}</x-status-badge>
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex flex-wrap justify-end gap-2">
                                    <x-button
                                        variant="secondary"
                                        wire:click="edit('{{ $advertisement->public_id }}')"
                                        aria-label="{{ __('admin.advertisements.edit_for', ['title' => $advertisement->title]) }}"
                                    >
                                        {{ __('admin.advertisements.edit') }}
                                    </x-button>
                                    <x-button
                                        variant="danger"
                                        wire:click="confirmDelete('{{ $advertisement->public_id }}')"
                                        aria-label="{{ __('admin.advertisements.delete_for', ['title' => $advertisement->title]) }}"
                                    >
                                        {{ __('admin.advertisements.delete') }}
                                    </x-button>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <x-pagination :paginator="$advertisements" />
    @endif
</div>
