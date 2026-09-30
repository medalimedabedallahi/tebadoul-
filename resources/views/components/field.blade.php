{{--
    Enveloppe interne des champs de formulaire : libellé, aide et erreur.
    Les identifiants {id}-help et {id}-error sont ceux référencés par aria-describedby
    dans input, select et textarea.
    @props id: identifiant du contrôle (obligatoire)
    @props label, help, error: textes déjà traduits
    @props required: ajoute l'astérisque visuel (le contrôle porte l'attribut required)
--}}
@props(['id', 'label' => null, 'help' => null, 'error' => null, 'required' => false])

<div class="flex flex-col gap-1.5">
    @if ($label)
        <label for="{{ $id }}" class="text-sm font-semibold text-ink">
            {{ $label }}
            @if ($required)
                <span aria-hidden="true" class="text-danger-ink">*</span>
            @endif
        </label>
    @endif

    @if ($help)
        <p id="{{ $id }}-help" class="text-sm text-ink-muted">{{ $help }}</p>
    @endif

    {{ $slot }}

    @if ($error)
        <p id="{{ $id }}-error" role="alert" class="flex items-start gap-1.5 text-sm font-medium text-danger-ink">
            <x-icon name="alert-circle" size="size-4" class="mt-1" />
            <span>{{ $error }}</span>
        </p>
    @endif
</div>
