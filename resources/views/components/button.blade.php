{{--
    Bouton ou lien d'action (cible minimale 44px).
    @props variant: primary|secondary|danger|ghost (défaut primary)
    @props href: si fourni, rend un lien <a> au lieu d'un <button>
    @props type: button|submit|reset (défaut button, ignoré pour un lien)
    @props loading: désactive le bouton, affiche un indicateur et pose aria-busy
--}}
@props(['variant' => 'primary', 'href' => null, 'type' => 'button', 'loading' => false])

@php
    $variants = [
        'primary' => 'border-transparent bg-brand text-white hover:bg-brand-strong',
        'secondary' => 'border-line-strong bg-surface text-ink hover:bg-brand-soft',
        'danger' => 'border-transparent bg-danger-strong text-white hover:bg-danger-ink',
        'ghost' => 'border-transparent bg-transparent text-brand-strong hover:bg-brand-soft',
    ];

    $classes = 'inline-flex min-h-target min-w-target items-center justify-center gap-2 rounded-control border px-4 py-2 text-base font-semibold transition-colors disabled:cursor-not-allowed disabled:opacity-60 '
        .($variants[$variant] ?? $variants['primary']);
@endphp

@if ($href)
    <a href="{{ $href }}" {{ $attributes->merge(['class' => $classes]) }}>{{ $slot }}</a>
@else
    <button
        type="{{ $type }}"
        {{ $attributes->merge(['class' => $classes]) }}
        @if ($loading) disabled aria-busy="true" @endif
    >
        @if ($loading)
            <x-icon name="spinner" class="motion-safe:animate-spin" />
            <span class="sr-only">{{ __('common.loading') }}</span>
        @endif
        {{ $slot }}
    </button>
@endif
