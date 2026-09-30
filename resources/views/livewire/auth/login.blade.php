<div class="mx-auto flex w-full max-w-md flex-col gap-8">
    <div class="flex flex-col gap-2">
        <h1 class="text-3xl font-bold text-ink">{{ __('auth.login.heading') }}</h1>
    </div>

    <x-card>
        <form wire:submit="login" class="flex flex-col gap-5" novalidate>
            <x-input
                name="identifier"
                label="{{ __('auth.login.identifier_label') }}"
                wire:model="identifier"
                autocomplete="username"
                required
            />

            <div class="flex flex-col gap-1.5">
                <x-input
                    name="password"
                    type="password"
                    label="{{ __('auth.login.password_label') }}"
                    wire:model="password"
                    autocomplete="current-password"
                    required
                />
                <a href="{{ route('password.request') }}" class="self-start text-sm font-semibold text-brand-strong hover:underline">
                    {{ __('auth.login.forgot_password') }}
                </a>
            </div>

            <x-button type="submit" class="w-full" wire:loading.attr="disabled" wire:target="login">
                <span wire:loading.remove wire:target="login">{{ __('auth.login.submit') }}</span>
                <span wire:loading wire:target="login" class="inline-flex items-center gap-2">
                    <x-icon name="spinner" class="motion-safe:animate-spin" />
                    {{ __('common.loading') }}
                </span>
            </x-button>
        </form>

        <x-slot:footer>
            <p class="text-sm text-ink-soft">
                {{ __('auth.login.no_account') }}
                <a href="{{ route('register') }}" class="font-semibold text-brand-strong hover:underline">{{ __('auth.login.create_account') }}</a>
            </p>
        </x-slot:footer>
    </x-card>
</div>
