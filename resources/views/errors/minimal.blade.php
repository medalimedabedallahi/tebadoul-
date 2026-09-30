{{--
    Gabarit de toutes les pages d'erreur web (remplace `errors::minimal` du framework, que ses vues
    401, 403, 404, 419, 429, 500 et 503 étendent). Le code vient de la section `code` de ces vues ;
    le titre et l'explication, traduits, de lang/{fr,ar}/errors.php.

    Volontairement autonome : ni base de données, ni session, ni composant qui en dépend, pour
    s'afficher même quand l'erreur vient de ces services.
--}}
@php
    $code = trim($__env->yieldContent('code'));
    $key = \Illuminate\Support\Facades\Lang::has("errors.codes.{$code}") ? $code : 'default';
    $locale = app()->getLocale();
    $direction = in_array($locale, ['ar', 'fa', 'he', 'ur'], true) ? 'rtl' : 'ltr';
    $script = $direction === 'rtl' ? 'arabic' : 'latin';
    $appName = config('app.name');
    $title = __("errors.codes.{$key}.title");
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', $locale) }}" dir="{{ $direction }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="color-scheme" content="light">
        <meta name="robots" content="noindex">
        <title>{{ $title }} | {{ $appName }}</title>

        <link rel="preload" href="{{ asset('fonts/cairo-'.$script.'-wght-normal.woff2') }}" as="font" type="font/woff2" crossorigin>
        <link rel="preload" href="{{ asset('fonts/alexandria-'.$script.'-wght-normal.woff2') }}" as="font" type="font/woff2" crossorigin>

        @vite(['resources/css/app.css'])
    </head>
    <body class="flex min-h-dvh flex-col font-sans antialiased">
        <header class="border-b border-line bg-surface">
            <div class="mx-auto flex w-full max-w-5xl items-center px-4 py-3 sm:px-6">
                <a href="{{ url('/') }}" class="inline-flex min-h-target items-center gap-2 rounded-control font-display text-xl font-bold text-ink" aria-label="{{ __('nav.home_link') }}">
                    <svg viewBox="0 0 48 48" fill="none" stroke="currentColor" stroke-width="4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false" class="size-6 text-accent">
                        <path d="M8 18h30m-7-7 7 7-7 7" />
                        <path d="M40 30H10m7 7-7-7 7-7" />
                    </svg>
                    <span>{{ $appName }}</span>
                </a>
            </div>
        </header>

        <main id="main-content" class="flex-1">
            <div class="mx-auto flex w-full max-w-prose flex-col items-start gap-4 px-4 py-16 sm:px-6">
                <p class="font-display text-5xl font-bold text-accent-ink" dir="ltr">{{ $code }}</p>
                <h1 class="text-3xl font-bold text-ink">{{ $title }}</h1>
                <p class="text-base text-ink-muted">{{ __("errors.codes.{$key}.description") }}</p>
                <a href="{{ url('/') }}" class="inline-flex min-h-target items-center rounded-control bg-brand px-4 py-2 text-base font-semibold text-white hover:bg-brand-strong">
                    {{ __('errors.home') }}
                </a>
            </div>
        </main>
    </body>
</html>
