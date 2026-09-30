{{--
    État vide : icône, titre, description et action facultative (slot).
    @props title: titre déjà traduit (obligatoire)
    @props description: texte d'explication facultatif
    @props icon: nom d'icône de <x-icon> (défaut inbox)
    @props level: niveau du titre, 1 à 6 (défaut 2)
--}}
@props(['title', 'description' => null, 'icon' => 'inbox', 'level' => 2])

@php
    $tag = 'h'.max(1, min(6, (int) $level));
@endphp

<div {{ $attributes->merge(['class' => 'flex flex-col items-start gap-3 border-y border-line py-10']) }}>
    <x-icon :name="$icon" size="size-7" class="text-ink-muted" />
    <{{ $tag }} class="text-xl font-semibold text-ink">{{ $title }}</{{ $tag }}>
    @if ($description)
        <p class="max-w-prose text-base text-ink-muted">{{ $description }}</p>
    @endif
    @if (! $slot->isEmpty())
        <div class="mt-2">{{ $slot }}</div>
    @endif
</div>
