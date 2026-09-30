<div class="mx-auto flex w-full max-w-2xl flex-col gap-8">
    <div class="flex flex-col gap-2">
        <h1 class="text-3xl font-bold text-ink">
            {{ $publicId === null ? __('requests.edit.create_title') : __('requests.edit.edit_title') }}
        </h1>
        <p class="text-base text-ink-muted">{{ __('requests.edit.description') }}</p>
    </div>

    <form wire:submit="save" class="flex flex-col gap-8" novalidate>
        <p class="text-sm text-ink-muted">{{ __('common.required_legend') }}</p>

        <x-card :heading="__('requests.edit.dates_heading')" level="2">
            <div class="grid gap-5 sm:grid-cols-2">
                <x-input
                    name="available_from"
                    type="date"
                    label="{{ Str::ucfirst(__('requests.fields.available_from')) }}"
                    wire:model="available_from"
                    required
                />
                <x-input
                    name="expires_at"
                    type="date"
                    label="{{ Str::ucfirst(__('requests.fields.expires_at')) }}"
                    wire:model="expires_at"
                    required
                />
            </div>
        </x-card>

        <x-card :heading="__('requests.edit.destinations_heading')" level="2">
            <p class="mb-5 text-sm text-ink-muted">{{ __('requests.edit.destinations_help', ['max' => $maxDestinations]) }}</p>

            @error('destinations')
                <x-alert type="danger" class="mb-5">{{ $message }}</x-alert>
            @enderror

            <ol class="flex flex-col gap-6">
                @foreach ($destinations as $index => $destination)
                    <li wire:key="destination-{{ $index }}" class="flex flex-col gap-4 rounded-control border border-line p-4">
                        <div class="flex items-center justify-between gap-3">
                            <h3 class="text-base font-semibold text-ink">{{ __('requests.edit.destination_label', ['number' => $index + 1]) }}</h3>
                            @if (count($destinations) > 1)
                                <x-button
                                    variant="ghost"
                                    wire:click="removeDestination({{ $index }})"
                                    aria-label="{{ __('requests.edit.remove_destination', ['number' => $index + 1]) }}"
                                >
                                    <x-icon name="x" />
                                </x-button>
                            @endif
                        </div>

                        <x-select
                            name="destinations.{{ $index }}.wilaya"
                            label="{{ Str::ucfirst(__('requests.fields.wilaya')) }}"
                            wire:model.live="destinations.{{ $index }}.wilaya"
                            :options="$wilayas"
                            :placeholder="true"
                            required
                        />

                        <x-select
                            name="destinations.{{ $index }}.moughataa"
                            label="{{ Str::ucfirst(__('requests.fields.moughataa')) }}"
                            wire:model="destinations.{{ $index }}.moughataa"
                            wire:key="destination-{{ $index }}-moughataa-{{ $destination['wilaya'] }}"
                            :options="$moughataas[$index] ?? []"
                            :placeholder="__('requests.edit.any_moughataa')"
                            :disabled="($moughataas[$index] ?? []) === []"
                        />
                    </li>
                @endforeach
            </ol>

            @if (count($destinations) < $maxDestinations)
                <x-button variant="secondary" class="mt-5" wire:click="addDestination">
                    {{ __('requests.edit.add_destination') }}
                </x-button>
            @endif
        </x-card>

        <div class="flex flex-wrap gap-3">
            <x-button type="submit" wire:loading.attr="disabled" wire:target="save">
                <span wire:loading.remove wire:target="save">{{ __('requests.edit.save') }}</span>
                <span wire:loading wire:target="save" class="inline-flex items-center gap-2">
                    <x-icon name="spinner" class="motion-safe:animate-spin" />
                    {{ __('common.loading') }}
                </span>
            </x-button>
            <x-button
                variant="secondary"
                :href="$publicId === null ? route('requests.index') : route('requests.show', $publicId)"
            >
                {{ __('requests.edit.cancel') }}
            </x-button>
        </div>
    </form>
</div>
