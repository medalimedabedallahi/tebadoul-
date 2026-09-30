<div class="mx-auto flex w-full max-w-md flex-col gap-8">
    <div class="flex flex-col gap-2">
        <h1 class="text-3xl font-bold text-ink">{{ __('auth.password_reset.reset_heading') }}</h1>
        <p class="text-base text-ink-soft">{{ __('auth.password_reset.reset_intro') }}</p>
    </div>

    <x-card>
        <form wire:submit="resetPassword" class="flex flex-col gap-5" novalidate>
            <x-input
                name="contact"
                label="{{ __('auth.password_reset.contact_label') }}"
                wire:model="contact"
                autocomplete="username"
                required
            />

            <x-input
                name="code"
                label="{{ __('auth.password_reset.code_label') }}"
                wire:model="code"
                inputmode="numeric"
                autocomplete="one-time-code"
                pattern="[0-9]*"
                maxlength="9"
                required
            />

            <x-input
                name="password"
                type="password"
                label="{{ __('auth.password_reset.new_password_label') }}"
                wire:model="password"
                autocomplete="new-password"
                required
            />

            <x-input
                name="password_confirmation"
                type="password"
                label="{{ __('auth.password_reset.new_password_confirmation_label') }}"
                wire:model="password_confirmation"
                autocomplete="new-password"
                required
            />

            <x-button type="submit" class="w-full" wire:loading.attr="disabled" wire:target="resetPassword">
                <span wire:loading.remove wire:target="resetPassword">{{ __('auth.password_reset.submit_reset') }}</span>
                <span wire:loading wire:target="resetPassword" class="inline-flex items-center gap-2">
                    <x-icon name="spinner" class="motion-safe:animate-spin" />
                    {{ __('common.loading') }}
                </span>
            </x-button>
        </form>
    </x-card>
</div>
