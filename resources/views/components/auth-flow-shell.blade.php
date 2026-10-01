{{--
    Cadre des parcours d'authentification en plusieurs etapes.
    @props heading, intro: textes traduits de la page
    @props currentStep: etape active dans le parcours compte -> verification -> profil
    Slot nomme `footer`: action secondaire facultative.
--}}
@props(['heading', 'intro', 'currentStep' => 1])

@php
    $steps = [
        1 => __('auth.flow.steps.account'),
        2 => __('auth.flow.steps.verification'),
        3 => __('auth.flow.steps.profile'),
    ];
@endphp

<section class="mx-auto w-full max-w-4xl overflow-hidden rounded-[1.25rem] border border-line bg-surface shadow-card lg:grid lg:grid-cols-[minmax(0,0.82fr)_minmax(0,1.18fr)]" aria-labelledby="auth-flow-title">
    <aside class="relative isolate flex flex-col justify-between gap-10 overflow-hidden bg-brand-strong p-6 text-white sm:p-8" aria-label="{{ __('auth.flow.progress_label') }}">
        <div class="flex flex-col gap-4">
            <div class="flex items-center gap-3">
                <span class="inline-flex size-11 items-center justify-center rounded-full border border-white/30 bg-white/10">
                    <x-icon name="exchange" size="size-6" />
                </span>
                <span class="text-xl font-bold">{{ __('common.app_name') }}</span>
            </div>

            <div class="flex max-w-sm flex-col gap-3">
                <h2 class="text-2xl font-bold leading-snug">{{ __('auth.flow.promise') }}</h2>
                <p class="text-sm leading-7 text-white/80">{{ __('auth.flow.description') }}</p>
            </div>
        </div>

        <div class="relative h-24" aria-hidden="true">
            <div class="absolute start-2 top-3 h-14 w-3/4 rounded-e-full border-e-2 border-t-2 border-white/35"></div>
            <div class="absolute end-2 top-8 h-12 w-3/4 rounded-s-full border-b-2 border-s-2 border-warning-soft/70"></div>
            <span class="absolute start-0 top-1 size-4 rounded-full border-4 border-brand-strong bg-white"></span>
            <span class="absolute end-0 top-[4.55rem] size-4 rounded-full border-4 border-brand-strong bg-warning-soft"></span>
            <x-icon name="exchange" size="size-8" class="absolute start-1/2 top-8 -translate-x-1/2 text-white" />
        </div>

        <ol class="grid grid-cols-3 gap-2 text-xs" aria-label="{{ __('auth.flow.progress_label') }}">
            @foreach ($steps as $number => $label)
                @php $isReached = $number <= $currentStep; @endphp
                <li
                    class="flex min-w-0 flex-col gap-2 border-t pt-3 {{ $isReached ? 'border-white text-white' : 'border-white/25 text-white/60' }}"
                    @if ($number === $currentStep) aria-current="step" @endif
                >
                    <span class="font-bold">{{ $number }}</span>
                    <span>{{ $label }}</span>
                </li>
            @endforeach
        </ol>
    </aside>

    <div class="flex flex-col p-6 sm:p-8 lg:p-10">
        <div class="flex max-w-xl flex-col gap-2">
            <p class="text-sm font-semibold text-brand-strong">
                {{ __('auth.flow.step_status', ['current' => $currentStep]) }}
            </p>
            <h1 id="auth-flow-title" class="text-3xl font-bold text-ink">{{ $heading }}</h1>
            <p class="max-w-prose text-base text-ink-soft">{{ $intro }}</p>
        </div>

        <div class="mt-8 text-base text-ink-soft">{{ $slot }}</div>

        @isset($footer)
            <div class="mt-6 border-t border-line pt-5">{{ $footer }}</div>
        @endisset
    </div>
</section>
