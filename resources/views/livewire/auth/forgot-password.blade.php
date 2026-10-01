<div class="mx-auto flex w-full max-w-md flex-col gap-8">
    <div class="flex flex-col gap-2">
        <h1 class="text-3xl font-bold text-ink">{{ __('auth.password_reset.forgot_heading') }}</h1>
        <p class="text-base text-ink-soft">{{ __('auth.password_reset.forgot_intro') }}</p>
    </div>

    <x-card>
        <form wire:submit="request" class="flex flex-col gap-5" novalidate>
            <x-input
                name="contact"
                type="email"
                label="{{ __('auth.password_reset.contact_label') }}"
                wire:model="contact"
                autocomplete="email"
                inputmode="email"
                dir="ltr"
                required
            />

            <x-button type="submit" class="w-full" wire:loading.attr="disabled" wire:target="request">
                <span wire:loading.remove wire:target="request">{{ __('auth.password_reset.submit') }}</span>
                <span wire:loading wire:target="request" class="inline-flex items-center gap-2">
                    <x-icon name="spinner" class="motion-safe:animate-spin" />
                    {{ __('common.loading') }}
                </span>
            </x-button>
        </form>

        <x-slot:footer>
            <p class="text-sm text-ink-soft">
                {{ __('auth.password_reset.reset_link') }}
                <a href="{{ route('password.reset') }}" class="font-semibold text-brand-strong hover:underline">{{ __('auth.password_reset.reset_title') }}</a>
            </p>
        </x-slot:footer>
    </x-card>
</div>
