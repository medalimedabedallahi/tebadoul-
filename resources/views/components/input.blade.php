{{--
    Champ de saisie avec libellé, aide et erreur liés par aria-describedby / aria-invalid.
    @props name: nom du champ ; sert aussi d'identifiant et de clé dans le sac d'erreurs
    @props label, help: textes déjà traduits
    @props error: message ; par défaut, la première erreur de validation du champ `name`
    @props type: text|email|tel|password|... (défaut text)
    @props required: pose required et l'astérisque visuel
    @props value: valeur initiale ; old() est appliqué hors wire:model
    Les autres attributs (autocomplete, inputmode, wire:model...) sont transmis à <input>.
--}}
@props(['name' => null, 'id' => null, 'label' => null, 'help' => null, 'error' => null, 'required' => false, 'type' => 'text', 'value' => null])

@php
    $id ??= $name ? str_replace(['.', '[', ']'], ['-', '-', ''], $name) : 'input-'.\Illuminate\Support\Str::random(6);
    $error ??= ($name && isset($errors)) ? ($errors->first($name) ?: null) : null;
    $describedBy = trim(($help ? $id.'-help ' : '').($error ? $id.'-error' : '')) ?: null;
    $isLivewire = $attributes->whereStartsWith('wire:model')->isNotEmpty();
    $value = $type === 'password' ? null : (($name && ! $isLivewire) ? old($name, $value) : $value);
@endphp

<x-field :id="$id" :label="$label" :help="$help" :error="$error" :required="$required">
    <input
        id="{{ $id }}"
        type="{{ $type }}"
        @if ($name) name="{{ $name }}" @endif
        @if (! is_null($value) && ! $isLivewire) value="{{ $value }}" @endif
        @required($required)
        {{ $attributes->merge(['class' => 'form-control', 'aria-describedby' => $describedBy, 'aria-invalid' => $error ? 'true' : null]) }}
    >
</x-field>
