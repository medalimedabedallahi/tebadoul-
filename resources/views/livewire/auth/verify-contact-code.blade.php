<x-auth-flow-shell
    :heading="__('auth.verification.heading')"
    :intro="__('auth.verification.intro')"
    :current-step="2"
>
        @if ($resendStatus)
            <x-alert type="success" class="mb-5">{{ $resendStatus }}</x-alert>
        @endif

        @if (app()->isLocal())
            <div class="mb-6 flex gap-3 rounded-card border border-warning bg-warning-soft p-4 text-warning-ink" role="note">
                <x-icon name="inbox" class="mt-0.5" />
                <div class="flex min-w-0 flex-col gap-1">
                    <p class="font-semibold">{{ __('auth.verification.local_delivery_heading') }}</p>
                    <p class="text-sm">{{ __('auth.verification.local_delivery_body') }}</p>
                    <a
                        href="http://localhost:8026"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="mt-1 w-fit font-semibold text-brand-strong underline underline-offset-4"
                    >
                        {{ __('auth.verification.open_mailpit') }}
                    </a>
                </div>
            </div>
        @endif

        <form wire:submit="verify" wire:loading.attr="aria-busy" wire:target="verify" class="flex flex-col gap-5" novalidate>
            <x-input
                name="contact"
                label="{{ __('auth.verification.contact_label') }}"
                wire:model="contact"
                autocomplete="username"
                dir="ltr"
                required
            />

            <x-input
                name="code"
                label="{{ __('auth.verification.code_label') }}"
                help="{{ __('auth.verification.code_help') }}"
                wire:model="code"
                inputmode="numeric"
                autocomplete="one-time-code"
                pattern="[0-9]*"
                maxlength="9"
                dir="ltr"
                required
            />

            <x-button type="submit" class="w-full" wire:loading.attr="disabled" wire:target="verify">
                <span wire:loading.remove wire:target="verify">{{ __('auth.verification.submit') }}</span>
                <span wire:loading wire:target="verify" role="status" class="inline-flex items-center gap-2">
                    <x-icon name="spinner" class="motion-safe:animate-spin" />
                    {{ __('common.loading') }}
                </span>
            </x-button>
        </form>

        <x-slot:footer>
            <button
                type="button"
                wire:click="resend"
                wire:loading.attr="disabled"
                wire:target="resend"
                class="inline-flex min-h-target items-center rounded-control font-semibold text-brand-strong hover:underline disabled:cursor-not-allowed disabled:opacity-60"
            >
                <span wire:loading.remove wire:target="resend">{{ __('auth.verification.resend') }}</span>
                <span wire:loading wire:target="resend" role="status" class="inline-flex items-center gap-2">
                    <x-icon name="spinner" class="motion-safe:animate-spin" />
                    {{ __('common.loading') }}
                </span>
            </button>
        </x-slot:footer>
</x-auth-flow-shell>
