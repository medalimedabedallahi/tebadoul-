{{--
    Message contextuel. Erreur et avertissement : role="alert" (annonce immédiate) ;
    information et succès : role="status" (annonce polie). L'état n'est jamais porté par la couleur seule.
    @props type: info|success|warning|danger (défaut info)
    @props title: titre court facultatif
--}}
@props(['type' => 'info', 'title' => null])

@php
    $tones = [
        'info' => ['icon' => 'info', 'role' => 'status', 'box' => 'border-info bg-info-soft text-info-ink'],
        'success' => ['icon' => 'check-circle', 'role' => 'status', 'box' => 'border-success bg-success-soft text-success-ink'],
        'warning' => ['icon' => 'alert-triangle', 'role' => 'alert', 'box' => 'border-warning bg-warning-soft text-warning-ink'],
        'danger' => ['icon' => 'x-circle', 'role' => 'alert', 'box' => 'border-danger bg-danger-soft text-danger-ink'],
    ];
    $type = array_key_exists($type, $tones) ? $type : 'info';
    $tone = $tones[$type];
@endphp

<div role="{{ $tone['role'] }}" {{ $attributes->merge(['class' => 'flex items-start gap-3 rounded-control border border-s-4 p-4 '.$tone['box']]) }}>
    <x-icon :name="$tone['icon']" class="mt-0.5" />
    <div class="flex-1 text-base">
        <span class="sr-only">{{ __('common.status_prefix', ['status' => __('common.status.'.$type)]) }}</span>
        @if ($title)
            <p class="font-semibold">{{ $title }}</p>
        @endif
        <div>{{ $slot }}</div>
    </div>
</div>
