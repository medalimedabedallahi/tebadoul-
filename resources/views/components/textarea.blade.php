{{--
    Zone de texte avec libellé, aide et erreur liés par aria-describedby / aria-invalid.
    @props name, label, help, error, required, value: comme pour <x-input>
    @props rows: nombre de lignes visibles (défaut 4)
--}}
@props(['name' => null, 'id' => null, 'label' => null, 'help' => null, 'error' => null, 'required' => false, 'value' => null, 'rows' => 4])

@php
    $id ??= $name ? str_replace(['.', '[', ']'], ['-', '-', ''], $name) : 'textarea-'.\Illuminate\Support\Str::random(6);
    $error ??= ($name && isset($errors)) ? ($errors->first($name) ?: null) : null;
    $describedBy = trim(($help ? $id.'-help ' : '').($error ? $id.'-error' : '')) ?: null;
    $isLivewire = $attributes->whereStartsWith('wire:model')->isNotEmpty();
    $value = ($name && ! $isLivewire) ? old($name, $value) : $value;
@endphp

<x-field :id="$id" :label="$label" :help="$help" :error="$error" :required="$required">
    <textarea
        id="{{ $id }}"
        rows="{{ $rows }}"
        @if ($name) name="{{ $name }}" @endif
        @required($required)
        {{ $attributes->merge(['class' => 'form-control', 'aria-describedby' => $describedBy, 'aria-invalid' => $error ? 'true' : null]) }}
    >{{ $isLivewire ? '' : $value }}</textarea>
</x-field>
