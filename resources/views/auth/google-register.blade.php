<x-layouts.app :title="__('auth.google.complete_heading')">
    <div class="mx-auto flex w-full max-w-md flex-col gap-6">
        <h1 class="text-3xl font-bold text-ink">{{ __('auth.google.complete_heading') }}</h1>
        <x-card>
            <form method="post" action="{{ route('google.register.store') }}" class="flex flex-col gap-5">
                @csrf
                <input type="hidden" name="terms_version" value="{{ config('legal.version') }}">
                @error('google') <x-alert type="danger">{{ $message }}</x-alert> @enderror
                <p class="text-sm text-ink-soft">{{ __('auth.google.complete_help') }}</p>
                <p class="break-all text-ink" dir="ltr">{{ $identity->email }}</p>
                <x-input name="name" :label="__('auth.register.name_label')" :value="$identity->name" autocomplete="name" required />
                <p class="text-sm text-ink-soft">
                    {{ __('auth.register.terms_intro') }}
                    <a href="{{ route('legal.terms') }}" target="_blank" rel="noopener" class="font-semibold text-brand-strong underline">{{ __('legal.terms.title') }}</a>
                    {{ __('auth.register.terms_and') }}
                    <a href="{{ route('legal.privacy') }}" target="_blank" rel="noopener" class="font-semibold text-brand-strong underline">{{ __('legal.privacy.title') }}</a>
                    <span class="sr-only">{{ __('auth.register.terms_new_tab') }}</span>
                </p>
                <x-checkbox name="accept_terms" :label="__('auth.register.accept_terms_label')" required />
                @error('terms_version') <x-alert type="danger">{{ __('auth.google.terms_changed') }}</x-alert> @enderror
                <x-button type="submit" class="w-full">{{ __('auth.register.submit') }}</x-button>
                <x-button :href="route('login')" variant="ghost">{{ __('auth.google.cancel') }}</x-button>
            </form>
        </x-card>
    </div>
</x-layouts.app>
