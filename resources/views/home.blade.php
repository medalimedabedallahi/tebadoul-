<x-layouts.app :title="__('home.title')" :description="__('home.description')">
    <div class="flex flex-col gap-16 sm:gap-20">
        <section class="flex max-w-3xl flex-col gap-8" aria-labelledby="home-heading">
            <x-exchange
                size="lg"
                animate
                :from="__('home.example_from')"
                :from-detail="__('home.example_from_detail')"
                :to="__('home.example_to')"
                :to-detail="__('home.example_to_detail')"
                :label="__('home.example_label')"
                class="border-y border-line py-8"
            />

            <div class="flex flex-col gap-4">
                <h1 id="home-heading" class="max-w-2xl text-2xl font-semibold text-ink sm:text-3xl">{{ __('home.heading') }}</h1>
                <p class="max-w-prose text-lg text-ink-soft">{{ __('home.intro') }}</p>
                <p class="text-base text-ink-muted">{{ __('home.audience') }}</p>
            </div>

            <div class="flex flex-wrap gap-3">
                @auth
                    <x-button :href="route('requests.index')">{{ __('home.cta_requests') }}</x-button>
                @else
                    <x-button :href="route('register')">{{ __('home.cta_register') }}</x-button>
                    <x-button :href="route('login')" variant="secondary">{{ __('home.cta_login') }}</x-button>
                @endauth
            </div>
        </section>

        <section class="flex flex-col gap-6" aria-labelledby="steps-heading">
            <h2 id="steps-heading" class="text-2xl font-bold text-ink">{{ __('home.steps_heading') }}</h2>
            <ol class="grid gap-8 sm:grid-cols-3 sm:gap-6">
                @foreach (['profile', 'request', 'matches'] as $step)
                    <li class="flex flex-col gap-2 border-t-2 border-ink pt-4">
                        <span class="font-display text-3xl font-bold text-accent" aria-hidden="true">{{ $loop->iteration }}</span>
                        <h3 class="text-lg font-semibold text-ink">{{ __('home.steps.'.$step.'.title') }}</h3>
                        <p class="text-base text-ink-muted">{{ __('home.steps.'.$step.'.body') }}</p>
                    </li>
                @endforeach
            </ol>
        </section>

        <section class="flex flex-col gap-6" aria-labelledby="principles-heading">
            <h2 id="principles-heading" class="text-2xl font-bold text-ink">{{ __('home.principles_heading') }}</h2>
            <dl class="grid max-w-3xl divide-y divide-line border-y border-line">
                @foreach (['consent', 'languages', 'accessible'] as $principle)
                    <div class="grid gap-1 py-5 sm:grid-cols-[14rem_minmax(0,1fr)] sm:gap-6">
                        <dt class="font-semibold text-ink">{{ __('home.principles.'.$principle.'.title') }}</dt>
                        <dd class="text-base text-ink-muted">{{ __('home.principles.'.$principle.'.body') }}</dd>
                    </div>
                @endforeach
            </dl>
        </section>

        <x-alert type="info" :title="__('home.status_title')" class="max-w-3xl">
            {{ __('home.status_body') }}
        </x-alert>
    </div>
</x-layouts.app>
