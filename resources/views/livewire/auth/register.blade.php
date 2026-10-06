<x-auth-flow-shell
    :heading="__('auth.register.heading')"
    :intro="__('auth.register.intro')"
    :current-step="1"
>
        <div class="mb-5"><x-google-sign-in /></div>
        <form method="post" wire:submit="register" wire:loading.attr="aria-busy" wire:target="register" class="flex flex-col gap-5" novalidate>
            <p class="text-sm text-ink-muted">{{ __('common.required_legend') }}</p>

            <x-input
                name="name"
                label="{{ __('auth.register.name_label') }}"
                wire:model="name"
                autocomplete="name"
                required
            />

            <x-input
                name="email"
                type="email"
                label="{{ __('auth.register.email_label') }}"
                help="{{ __('auth.register.contact_help') }}"
                wire:model="email"
                autocomplete="email"
                inputmode="email"
                dir="ltr"
                required
            />

            <x-input
                name="phone"
                type="tel"
                label="{{ __('auth.register.phone_label') }}"
                wire:model="phone"
                autocomplete="tel"
                inputmode="tel"
                dir="ltr"
            />

            <x-input
                name="password"
                type="password"
                label="{{ __('auth.register.password_label') }}"
                help="{{ __('auth.register.password_help') }}"
                wire:model="password"
                autocomplete="new-password"
                required
            />

            <x-input
                name="password_confirmation"
                type="password"
                label="{{ __('auth.register.password_confirmation_label') }}"
                wire:model="password_confirmation"
                autocomplete="new-password"
                required
            />

            <div class="flex flex-col gap-2">
                <p class="text-sm text-ink-soft">
                    {{ __('auth.register.terms_intro') }}
                    <a href="{{ route('legal.terms') }}" target="_blank" rel="noopener" class="font-semibold text-brand-strong underline">{{ __('legal.terms.title') }}</a>
                    {{ __('auth.register.terms_and') }}
                    <a href="{{ route('legal.privacy') }}" target="_blank" rel="noopener" class="font-semibold text-brand-strong underline">{{ __('legal.privacy.title') }}</a>
                    <span class="sr-only">{{ __('auth.register.terms_new_tab') }}</span>
                </p>
                <x-checkbox
                    name="accept_terms"
                    :label="__('auth.register.accept_terms_label')"
                    wire:model="accept_terms"
                    required
                />
            </div>

            <x-button type="submit" class="w-full" wire:loading.attr="disabled" wire:target="register">
                <span wire:loading.remove wire:target="register">{{ __('auth.register.submit') }}</span>
                <span wire:loading wire:target="register" role="status" class="inline-flex items-center gap-2">
                    <x-icon name="spinner" class="motion-safe:animate-spin" />
                    {{ __('common.loading') }}
                </span>
            </x-button>
        </form>

        <x-slot:footer>
            <p class="text-sm text-ink-soft">
                {{ __('auth.register.already_have_account') }}
                <a href="{{ route('login') }}" class="font-semibold text-brand-strong hover:underline">{{ __('auth.register.sign_in') }}</a>
            </p>
        </x-slot:footer>
</x-auth-flow-shell>
