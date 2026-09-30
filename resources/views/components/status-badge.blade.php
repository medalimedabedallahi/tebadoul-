{{--
    Pastille de statut : texte + icône (jamais la couleur seule).
    @props tone: neutral|brand|success|warning|danger|info (défaut neutral)
    Le libellé est fourni dans le slot, déjà traduit.
--}}
@props(['tone' => 'neutral'])

@php
    $tones = [
        'neutral' => ['icon' => 'dot', 'box' => 'border-line-strong bg-canvas text-ink-soft'],
        'brand' => ['icon' => 'dot', 'box' => 'border-brand-line bg-brand-soft text-brand-ink'],
        'success' => ['icon' => 'check-circle', 'box' => 'border-success bg-success-soft text-success-ink'],
        'warning' => ['icon' => 'alert-triangle', 'box' => 'border-warning bg-warning-soft text-warning-ink'],
        'danger' => ['icon' => 'x-circle', 'box' => 'border-danger bg-danger-soft text-danger-ink'],
        'info' => ['icon' => 'info', 'box' => 'border-info bg-info-soft text-info-ink'],
    ];
    $config = $tones[$tone] ?? $tones['neutral'];
@endphp

<span {{ $attributes->merge(['class' => 'inline-flex items-center gap-1.5 rounded-full border px-2.5 py-0.5 text-xs font-semibold '.$config['box']]) }}>
    <x-icon :name="$config['icon']" size="size-3.5" />
    {{ $slot }}
</span>
