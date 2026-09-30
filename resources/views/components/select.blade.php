{{--
    Liste déroulante native avec libellé, aide et erreur liés par aria-describedby / aria-invalid.
    @props name, label, help, error, required: comme pour <x-input>
    @props options: tableau valeur => libellé (facultatif ; sinon fournir des <option> dans le slot)
    @props value: valeur sélectionnée ; old() est appliqué hors wire:model
    @props placeholder: libellé d'une première option vide (true = texte par défaut traduit)
--}}
@props(['name' => null, 'id' => null, 'label' => null, 'help' => null, 'error' => null, 'required' => false, 'options' => [], 'value' => null, 'placeholder' => null])

@php
    $id ??= $name ? str_replace(['.', '[', ']'], ['-', '-', ''], $name) : 'select-'.\Illuminate\Support\Str::random(6);
    $error ??= ($name && isset($errors)) ? ($errors->first($name) ?: null) : null;
    $describedBy = trim(($help ? $id.'-help ' : '').($error ? $id.'-error' : '')) ?: null;
    $isLivewire = $attributes->whereStartsWith('wire:model')->isNotEmpty();
    $selected = ($name && ! $isLivewire) ? (string) old($name, $value) : (string) $value;
    $placeholderLabel = $placeholder === true ? __('common.select_placeholder') : $placeholder;
@endphp

<x-field :id="$id" :label="$label" :help="$help" :error="$error" :required="$required">
    <select
        id="{{ $id }}"
        @if ($name) name="{{ $name }}" @endif
        @required($required)
        {{ $attributes->merge(['class' => 'form-control', 'aria-describedby' => $describedBy, 'aria-invalid' => $error ? 'true' : null]) }}
    >
        @if ($placeholderLabel)
            <option value="">{{ $placeholderLabel }}</option>
        @endif
        @foreach ($options as $optionValue => $optionLabel)
            <option value="{{ $optionValue }}" @selected($selected !== '' && (string) $optionValue === $selected)>{{ $optionLabel }}</option>
        @endforeach
        {{ $slot }}
    </select>
</x-field>
