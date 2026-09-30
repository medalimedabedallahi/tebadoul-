<div class="mx-auto flex w-full max-w-2xl flex-col gap-8">
    <div class="flex flex-col gap-2">
        <h1 class="text-3xl font-bold text-ink">{{ __('profile.heading') }}</h1>
        <p class="text-base text-ink-muted">{{ __('profile.intro') }}</p>
    </div>

    @if ($profileSaved)
        <x-alert type="success">{{ $profileSaved }}</x-alert>
    @endif

    <form wire:submit="save" class="flex flex-col gap-8" novalidate>
        <p class="text-sm text-ink-muted">{{ __('common.required_legend') }}</p>

        <x-card :heading="__('profile.professional_heading')" level="2">
            <div class="flex flex-col gap-5">
                <x-select
                    name="sector"
                    label="{{ Str::ucfirst(__('profile.fields.sector')) }}"
                    wire:model.live="sector"
                    :options="$sectors"
                    :placeholder="true"
                    required
                />

                <x-select
                    name="profession"
                    label="{{ Str::ucfirst(__('profile.fields.profession')) }}"
                    help="{{ $sector === '' ? __('profile.help.choose_sector_first') : null }}"
                    wire:model.live="profession"
                    wire:key="profession-{{ $sector }}"
                    :options="$professions"
                    :placeholder="true"
                    :disabled="$sector === ''"
                    required
                />

                @if ($specialties !== [])
                    <x-select
                        name="specialty"
                        label="{{ Str::ucfirst(__('profile.fields.specialty')) }}"
                        wire:model="specialty"
                        wire:key="specialty-{{ $profession }}"
                        :options="$specialties"
                        :placeholder="true"
                        required
                    />
                @endif

                @if ($grades !== [])
                    <x-select
                        name="grade"
                        label="{{ Str::ucfirst(__('profile.fields.grade')) }}"
                        wire:model="grade"
                        wire:key="grade-{{ $profession }}"
                        :options="$grades"
                        :placeholder="true"
                        required
                    />
                @endif

                <x-input
                    name="professional_identifier"
                    label="{{ Str::ucfirst(__('profile.fields.professional_identifier')) }}"
                    help="{{ __('profile.help.professional_identifier') }}"
                    wire:model="professional_identifier"
                    autocomplete="off"
                    maxlength="64"
                />
            </div>
        </x-card>

        <x-card :heading="__('profile.assignment_heading')" level="2">
            <div class="flex flex-col gap-5">
                <x-select
                    name="wilaya"
                    label="{{ Str::ucfirst(__('profile.fields.wilaya')) }}"
                    wire:model.live="wilaya"
                    :options="$wilayas"
                    :placeholder="true"
                    required
                />

                <x-select
                    name="moughataa"
                    label="{{ Str::ucfirst(__('profile.fields.moughataa')) }}"
                    help="{{ $wilaya === '' ? __('profile.help.choose_wilaya_first') : null }}"
                    wire:model.live="moughataa"
                    wire:key="moughataa-{{ $wilaya }}"
                    :options="$moughataas"
                    :placeholder="true"
                    :disabled="$wilaya === ''"
                    required
                />

                <x-select
                    name="establishment"
                    label="{{ Str::ucfirst(__('profile.fields.establishment')) }}"
                    help="{{ $establishments === [] ? __('profile.help.no_establishment') : __('profile.help.establishment') }}"
                    wire:model="establishment"
                    wire:key="establishment-{{ $sector }}-{{ $moughataa }}"
                    :options="$establishments"
                    :placeholder="__('profile.establishment_none')"
                    :disabled="$establishments === []"
                />
            </div>
        </x-card>

        <div>
            <x-button type="submit" wire:loading.attr="disabled" wire:target="save">
                <span wire:loading.remove wire:target="save">{{ __('profile.submit') }}</span>
                <span wire:loading wire:target="save" class="inline-flex items-center gap-2">
                    <x-icon name="spinner" class="motion-safe:animate-spin" />
                    {{ __('common.loading') }}
                </span>
            </x-button>
        </div>
    </form>
</div>
