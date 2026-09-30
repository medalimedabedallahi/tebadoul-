{{--
    Icône SVG en ligne (trait 2px, viewBox 24).
    @props name: alert-circle|alert-triangle|arrow-end|check|check-circle|chevron-end|chevron-start|dot|exchange|globe|inbox|info|spinner|x|x-circle
    @props title: libellé accessible ; sans titre, l'icône est décorative (aria-hidden)
    @props mirror: inverse l'icône en lecture de droite à gauche (flèches, chevrons)
    @props size: classes de taille Tailwind (défaut size-5)
--}}
@props(['name', 'title' => null, 'mirror' => false, 'size' => 'size-5'])

@php
    $shapes = [
        'alert-circle' => '<circle cx="12" cy="12" r="10"/><path d="M12 8v4M12 16h.01"/>',
        'alert-triangle' => '<path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><path d="M12 9v4M12 17h.01"/>',
        'arrow-end' => '<path d="M5 12h14M12 5l7 7-7 7"/>',
        'check' => '<path d="M20 6 9 17l-5-5"/>',
        'check-circle' => '<path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><path d="M22 4 12 14.01l-3-3"/>',
        'chevron-end' => '<path d="m9 18 6-6-6-6"/>',
        'chevron-start' => '<path d="m15 18-6-6 6-6"/>',
        'dot' => '<circle cx="12" cy="12" r="4" fill="currentColor"/>',
        'exchange' => '<path d="m17 1 4 4-4 4"/><path d="M3 11V9a4 4 0 0 1 4-4h14"/><path d="m7 23-4-4 4-4"/><path d="M21 13v2a4 4 0 0 1-4 4H3"/>',
        'globe' => '<circle cx="12" cy="12" r="10"/><path d="M2 12h20"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/>',
        'inbox' => '<path d="M22 12h-6l-2 3h-4l-2-3H2"/><path d="M5.45 5.11 2 12v6a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-6l-3.45-6.89A2 2 0 0 0 16.76 4H7.24a2 2 0 0 0-1.79 1.11z"/>',
        'info' => '<circle cx="12" cy="12" r="10"/><path d="M12 16v-4M12 8h.01"/>',
        'spinner' => '<path d="M21 12a9 9 0 1 1-6.219-8.56"/>',
        'x' => '<path d="M18 6 6 18M6 6l12 12"/>',
        'x-circle' => '<circle cx="12" cy="12" r="10"/><path d="m15 9-6 6m0-6 6 6"/>',
    ];
@endphp

<svg
    xmlns="http://www.w3.org/2000/svg"
    viewBox="0 0 24 24"
    fill="none"
    stroke="currentColor"
    stroke-width="2"
    stroke-linecap="round"
    stroke-linejoin="round"
    @if ($title)
        role="img" aria-label="{{ $title }}"
    @else
        aria-hidden="true" focusable="false"
    @endif
    {{ $attributes->class([$size, 'shrink-0', 'rtl:-scale-x-100' => $mirror]) }}
>{!! $shapes[$name] ?? '' !!}</svg>
