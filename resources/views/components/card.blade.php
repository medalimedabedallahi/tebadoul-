{{--
    Carte de contenu.
    @props heading: titre facultatif
    @props level: niveau du titre, 1 à 6 (défaut 2)
    Slot nommé `footer` : pied de carte facultatif.
--}}
@props(['heading' => null, 'level' => 2])

@php
    $tag = 'h'.max(1, min(6, (int) $level));
@endphp

<div {{ $attributes->merge(['class' => 'rounded-card border border-line bg-surface p-6 shadow-card']) }}>
    @if ($heading)
        <{{ $tag }} class="mb-2 text-lg font-semibold text-ink">{{ $heading }}</{{ $tag }}>
    @endif

    <div class="text-base text-ink-soft">{{ $slot }}</div>

    @isset($footer)
        <div class="mt-4 border-t border-line pt-4">{{ $footer }}</div>
    @endisset
</div>
