{{--
    Case à cocher avec libellé cliquable (cible 44px), aide et erreur liés par aria-describedby.
    @props name, label, help, error, required: comme pour <x-input>
    @props value: valeur envoyée si cochée (défaut 1)
    @props checked: état initial ; old() est appliqué hors wire:model
--}}
@props(['name' => null, 'id' => null, 'label', 'help' => null, 'error' => null, 'required' => false, 'value' => '1', 'checked' => false])

@php
    $id ??= $name ? str_replace(['.', '[', ']'], ['-', '-', ''], $name) : 'checkbox-'.\Illuminate\Support\Str::random(6);
    $error ??= ($name && isset($errors)) ? ($errors->first($name) ?: null) : null;
    $describedBy = trim(($help ? $id.'-help ' : '').($error ? $id.'-error' : '')) ?: null;
    $isLivewire = $attributes->whereStartsWith('wire:model')->isNotEmpty();
    $isChecked = ($name && ! $isLivewire) ? (bool) old($name, $checked) : (bool) $checked;
@endphp

<div class="flex flex-col gap-1">
    <div class="flex items-start gap-3">
        <input
            id="{{ $id }}"
            type="checkbox"
            value="{{ $value }}"
            @if ($name) name="{{ $name }}" @endif
            @checked($isChecked && ! $isLivewire)
            @required($required)
            {{ $attributes->merge(['class' => 'mt-2.5 size-6 shrink-0 accent-brand', 'aria-describedby' => $describedBy, 'aria-invalid' => $error ? 'true' : null]) }}
        >
        <label for="{{ $id }}" class="min-h-target flex-1 py-2 text-base text-ink">
            {{ $label }}
            @if ($required)
                <span aria-hidden="true" class="text-danger-ink">*</span>
            @endif
        </label>
    </div>

    @if ($help)
        <p id="{{ $id }}-help" class="ps-9 text-sm text-ink-muted">{{ $help }}</p>
    @endif

    @if ($error)
        <p id="{{ $id }}-error" role="alert" class="flex items-start gap-1.5 ps-9 text-sm font-medium text-danger-ink">
            <x-icon name="alert-circle" size="size-4" class="mt-1" />
            <span>{{ $error }}</span>
        </p>
    @endif
</div>
