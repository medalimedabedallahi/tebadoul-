{{--
    Gabarit principal des pages web (composant anonyme <x-layouts.app>).
    @props title: titre de la page, déjà traduit (le nom de l'application est ajouté)
    @props description: meta description facultative, déjà traduite
    La langue et la direction proviennent de app()->getLocale() (résolue par le middleware SetLocale).
    Livewire injecte lui-même ses scripts et styles (livewire.inject_assets = true) sur les pages qui
    contiennent un composant ; aucune directive @livewireStyles / @livewireScripts n'est nécessaire.

    Le menu se replie sous md quand JavaScript est disponible (classe `js`, resources/js/app.js) ;
    sans JavaScript il reste entièrement visible.
--}}
@props(['title' => null, 'description' => null])

@php
    $locale = app()->getLocale();
    $direction = in_array($locale, ['ar', 'fa', 'he', 'ur'], true) ? 'rtl' : 'ltr';
    $supportedLocales = config('app.supported_locales', ['fr', 'ar']);
    $appName = __('common.app_name');
    $script = $direction === 'rtl' ? 'arabic' : 'latin';
    $navLink = 'inline-flex min-h-target items-center whitespace-nowrap rounded-control px-3 text-base font-medium text-ink-soft hover:bg-brand-soft hover:text-ink '
        .'aria-[current=page]:font-semibold aria-[current=page]:text-ink aria-[current=page]:underline aria-[current=page]:decoration-accent aria-[current=page]:decoration-2 aria-[current=page]:underline-offset-8';
    $links = auth()->check()
        ? array_filter([
            ['route' => 'requests.index', 'active' => 'requests.*', 'label' => __('nav.requests')],
            ['route' => 'matches.index', 'active' => 'matches.*', 'label' => __('nav.matches')],
            ['route' => 'notifications.index', 'active' => 'notifications.*', 'label' => __('nav.notifications'), 'badge' => auth()->user()->unreadNotifications()->count()],
            ['route' => 'profile.edit', 'active' => 'profile.*', 'label' => __('nav.profile')],
            ['route' => 'account.show', 'active' => 'account.*', 'label' => __('nav.account')],
            auth()->user()->can('viewAny', \App\Models\User::class)
                ? ['route' => 'admin.users.index', 'active' => 'admin.users.*', 'label' => __('nav.admin')]
                : null,
            auth()->user()->can('viewAny', \App\Models\UserReport::class)
                ? ['route' => 'admin.reports.index', 'active' => 'admin.reports.*', 'label' => __('nav.moderation')]
                : null,
            auth()->user()->can('viewStatistics', \App\Models\User::class)
                ? ['route' => 'admin.statistics.show', 'active' => 'admin.statistics.*', 'label' => __('nav.statistics')]
                : null,
            auth()->user()->can('viewAny', \App\Models\AuditLog::class)
                ? ['route' => 'admin.audit-logs.index', 'active' => 'admin.audit-logs.*', 'label' => __('nav.audit')]
                : null,
            auth()->user()->can('viewAny', \App\Models\Advertisement::class)
                ? ['route' => 'admin.advertisements.index', 'active' => 'admin.advertisements.*', 'label' => __('nav.advertisements')]
                : null,
        ])
        : [['route' => 'home', 'active' => 'home', 'label' => __('nav.home')]];
    // Page advertising space, by route, shown after the content. Livewire updates never re-render
    // the layout, so the advertisement drawn on page load stays in place.
    $adPlacement = match (true) {
        request()->routeIs('home') => \App\Enums\AdPlacement::Home,
        request()->routeIs('login', 'register', 'password.request', 'password.reset', 'verification.show') => \App\Enums\AdPlacement::Auth,
        auth()->check() && request()->routeIs('requests.*', 'matches.*', 'notifications.*') => \App\Enums\AdPlacement::Member,
        default => null,
    };
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', $locale) }}" dir="{{ $direction }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="color-scheme" content="light">
        <meta name="theme-color" content="#1d2757">
        <meta name="session-expired-message" content="{{ __('common.session_expired') }}">
        @if ($description)
            <meta name="description" content="{{ $description }}">
        @endif

        <title>{{ $title ? $title.' | '.$appName : $appName }}</title>

        <script>document.documentElement.classList.add('js');</script>

        <link rel="preload" href="{{ asset('fonts/cairo-'.$script.'-wght-normal.woff2') }}" as="font" type="font/woff2" crossorigin>
        <link rel="preload" href="{{ asset('fonts/alexandria-'.$script.'-wght-normal.woff2') }}" as="font" type="font/woff2" crossorigin>

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="flex min-h-dvh flex-col font-sans antialiased">
        <a
            href="#main-content"
            class="sr-only rounded-control bg-surface font-semibold text-ink shadow-raised focus:not-sr-only focus:fixed focus:start-4 focus:top-4 focus:z-50 focus:px-4 focus:py-3"
        >
            {{ __('common.skip_to_content') }}
        </a>

        <header class="border-b border-line bg-surface">
            <div class="mx-auto flex w-full max-w-5xl flex-wrap items-center gap-x-6 gap-y-3 px-4 py-3 sm:px-6">
                <a href="{{ route('home') }}" class="me-auto inline-flex min-h-target items-center gap-2 rounded-control font-display text-xl font-bold text-ink" aria-label="{{ __('nav.home_link') }}">
                    <svg viewBox="0 0 48 48" fill="none" stroke="currentColor" stroke-width="4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false" class="size-6 text-accent">
                        <path d="M8 18h30m-7-7 7 7-7 7" />
                        <path d="M40 30H10m7 7-7-7 7-7" />
                    </svg>
                    <span>{{ $appName }}</span>
                </a>

                <button
                    type="button"
                    class="nav-toggle min-h-target items-center gap-2 rounded-control border border-line-strong px-3 text-base font-medium text-ink"
                    data-nav-toggle
                    aria-controls="site-menu"
                    aria-expanded="false"
                >
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true" focusable="false" class="size-5">
                        <path d="M4 7h16M4 12h16M4 17h16" />
                    </svg>
                    {{ __('nav.menu') }}
                </button>

                <div id="site-menu" data-nav-panel class="flex w-full flex-col gap-3 border-t border-line pt-3 md:w-auto md:flex-row md:flex-wrap md:items-center md:gap-4 md:border-0 md:pt-0">
                    <nav aria-label="{{ __('nav.main') }}">
                        <ul class="flex flex-col gap-1 md:flex-row md:flex-wrap">
                            @foreach ($links as $link)
                                <li>
                                    <a
                                        href="{{ route($link['route']) }}"
                                        @if (request()->routeIs($link['active'])) aria-current="page" @endif
                                        class="{{ $navLink }}"
                                    >
                                        {{ $link['label'] }}
                                        @if (($link['badge'] ?? 0) > 0)
                                            <span class="ms-1.5 inline-flex min-w-6 items-center justify-center rounded-full bg-accent-ink px-1.5 text-xs font-semibold text-white">
                                                <span aria-hidden="true">{{ $link['badge'] }}</span>
                                                <span class="sr-only">{{ __('notifications.nav_unread', ['count' => $link['badge']]) }}</span>
                                            </span>
                                        @endif
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                    </nav>

                    <div role="group" aria-label="{{ __('nav.account_menu') }}" class="flex flex-wrap items-center gap-2">
                        @guest
                            <a href="{{ route('login') }}" class="{{ $navLink }}">{{ __('nav.login') }}</a>
                            <x-button href="{{ route('register') }}">{{ __('nav.register') }}</x-button>
                        @else
                            <livewire:auth.logout-button />
                        @endguest
                    </div>

                    <form method="POST" action="{{ route('locale.switch') }}">
                        @csrf
                        <div role="group" aria-label="{{ __('nav.language_switcher') }}" class="flex items-center gap-1">
                            @foreach ($supportedLocales as $code)
                                <button
                                    type="submit"
                                    name="locale"
                                    value="{{ $code }}"
                                    lang="{{ $code }}"
                                    @if ($code === $locale) aria-current="true" @endif
                                    class="inline-flex min-h-target min-w-target items-center justify-center rounded-control px-2 text-sm font-medium text-ink-muted hover:text-ink aria-[current=true]:font-semibold aria-[current=true]:text-ink aria-[current=true]:underline aria-[current=true]:decoration-accent aria-[current=true]:decoration-2 aria-[current=true]:underline-offset-8"
                                >
                                    {{ __('nav.languages.'.$code) }}
                                </button>
                            @endforeach
                        </div>
                    </form>
                </div>
            </div>
        </header>

        <main id="main-content" tabindex="-1" class="flex-1">
            <div class="mx-auto w-full max-w-5xl px-4 py-10 sm:px-6 sm:py-14">
                @if (session('status'))
                    <x-alert :type="session('status_type', 'info')" class="mb-8">
                        {{ session('status') }}
                    </x-alert>
                @endif

                {{ $slot }}

                {{-- After the content: the page's heading and task always come first. --}}
                @if ($adPlacement)
                    <x-ad-slot :placement="$adPlacement" :centered="$adPlacement === \App\Enums\AdPlacement::Auth" class="mt-14" />
                @endif
            </div>
        </main>

        <footer class="border-t border-line">
            <x-ad-slot :placement="\App\Enums\AdPlacement::Footer" compact class="mx-auto max-w-5xl px-4 pt-6 sm:px-6" />
            <div class="mx-auto flex w-full max-w-5xl flex-wrap items-center justify-between gap-2 px-4 py-6 text-sm text-ink-muted sm:px-6">
                <p>{{ __('common.footer.copyright', ['year' => now()->year, 'name' => $appName]) }}</p>
                <nav aria-label="{{ __('legal.footer_label') }}">
                    <ul class="flex flex-wrap gap-x-4">
                        <li><a href="{{ route('legal.terms') }}" class="inline-flex min-h-target items-center underline hover:text-ink">{{ __('legal.terms.title') }}</a></li>
                        <li><a href="{{ route('legal.privacy') }}" class="inline-flex min-h-target items-center underline hover:text-ink">{{ __('legal.privacy.title') }}</a></li>
                    </ul>
                </nav>
                <p>{{ __('common.footer.note') }}</p>
            </div>
        </footer>
    </body>
</html>
