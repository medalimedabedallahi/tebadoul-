{{--
    Diptyque d'échange : deux postes face à face, reliés par le signe d'échange (safran).
    C'est l'élément signature de Tebadoul : accueil, demandes et correspondances.
    La grille suit la direction de lecture : en arabe, le premier poste passe à droite.

    @props from: libellé principal du premier poste (ex. « Rosso »)
    @props fromDetail: précision facultative sous le premier poste (ex. « Trarza »)
    @props to: libellé principal du second poste
    @props toDetail: précision facultative sous le second poste
    @props size: lg (accueil, en-tête de page) | md (liste) — défaut md
    @props animate: une seule rotation du signe au chargement (respecte prefers-reduced-motion)
    @props label: phrase lue par les lecteurs d'écran à la place du diptyque visuel
    @props constrained: limite la largeur (listes) pour garder les deux postes proches ; false sur les pages de détail
--}}
@props(['from', 'fromDetail' => null, 'to', 'toDetail' => null, 'size' => 'md', 'animate' => false, 'label' => null, 'constrained' => true])

@php
    $name = $size === 'lg'
        ? 'text-3xl font-bold sm:text-4xl'
        : 'text-xl font-semibold';
    $sign = $size === 'lg' ? 'size-10 sm:size-12' : 'size-7';
@endphp

<div {{ $attributes->merge(['class' => 'grid grid-cols-[minmax(0,1fr)_auto_minmax(0,1fr)] items-center gap-x-3 sm:gap-x-6 '.(! $constrained ? '' : ($size === 'lg' ? 'max-w-3xl' : 'max-w-xl'))]) }}>
    @if ($label)
        <p class="sr-only">{{ $label }}</p>
    @endif

    <div class="min-w-0" @if ($label) aria-hidden="true" @endif>
        <p class="font-display leading-tight text-ink {{ $name }} break-words">{{ $from }}</p>
        @if ($fromDetail)
            <p class="mt-1 text-sm text-ink-muted">{{ $fromDetail }}</p>
        @endif
    </div>

    <svg
        viewBox="0 0 48 48"
        fill="none"
        stroke="currentColor"
        stroke-width="3"
        stroke-linecap="round"
        stroke-linejoin="round"
        aria-hidden="true"
        focusable="false"
        class="{{ $sign }} shrink-0 text-accent {{ $animate ? 'exchange-sign-intro' : '' }}"
    >
        <path d="M8 18h30m-7-7 7 7-7 7" />
        <path d="M40 30H10m7 7-7-7 7-7" />
    </svg>

    <div class="min-w-0 text-end" @if ($label) aria-hidden="true" @endif>
        <p class="font-display leading-tight text-ink {{ $name }} break-words">{{ $to }}</p>
        @if ($toDetail)
            <p class="mt-1 text-sm text-ink-muted">{{ $toDetail }}</p>
        @endif
    </div>
</div>
