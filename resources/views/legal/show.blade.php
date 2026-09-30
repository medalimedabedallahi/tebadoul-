{{--
    Page légale publique : conditions d'utilisation (`terms`) ou politique de confidentialité (`privacy`).
    Textes dans lang/{fr,ar}/legal.php ; version et mention de projet dans config/legal.php.
--}}
@php
    $other = $document === 'terms' ? 'privacy' : 'terms';
    $replacements = ['days' => (int) config('auth.pending_accounts.ttl_days')];
    $version = \Illuminate\Support\Carbon::parse((string) config('legal.version'))->translatedFormat('j F Y');
@endphp

<x-layouts.app :title="__('legal.'.$document.'.title')" :description="__('legal.'.$document.'.description')">
    <article class="mx-auto flex w-full max-w-prose flex-col gap-8">
        <header class="flex flex-col gap-2">
            <h1 class="text-3xl font-bold text-ink">{{ __('legal.'.$document.'.title') }}</h1>
            <p class="text-sm text-ink-muted">{{ __('legal.version', ['date' => $version]) }}</p>
        </header>

        @if (config('legal.draft'))
            <x-alert type="warning">{{ __('legal.draft_notice') }}</x-alert>
        @endif

        @foreach (trans('legal.'.$document.'.sections') as $index => $section)
            <section aria-labelledby="section-{{ $index }}" class="flex flex-col gap-3">
                <h2 id="section-{{ $index }}" class="text-xl font-semibold text-ink">{{ $index + 1 }}. {{ $section['heading'] }}</h2>
                @foreach ($section['paragraphs'] as $paragraph)
                    <p class="text-base text-ink-soft">{{ __('legal.'.$document.'.sections.'.$index.'.paragraphs.'.$loop->index, $replacements) }}</p>
                @endforeach
            </section>
        @endforeach

        <p class="border-t border-line pt-6 text-base text-ink-soft">
            {{ __('legal.other_document') }}
            <a href="{{ route('legal.'.$other) }}" class="font-semibold text-brand-strong underline">{{ __('legal.'.$other.'.title') }}</a>
        </p>
    </article>
</x-layouts.app>
