@php
    $statusTones = [
        \App\Enums\UserStatus::Active->value => 'success',
        \App\Enums\UserStatus::PendingVerification->value => 'warning',
        \App\Enums\UserStatus::Suspended->value => 'danger',
    ];
@endphp

<div class="mx-auto flex w-full max-w-2xl flex-col gap-8">
    <div class="flex flex-col gap-2">
        <h1 class="text-3xl font-bold text-ink">{{ __('auth.account.heading') }}</h1>
    </div>

    <x-card>
        <dl class="grid gap-4 sm:grid-cols-2">
            <div>
                <dt class="text-sm font-semibold text-ink-muted">{{ __('auth.account.name_label') }}</dt>
                <dd class="text-base text-ink">{{ $accountName }}</dd>
            </div>
            <div>
                <dt class="text-sm font-semibold text-ink-muted">{{ __('auth.account.status_label') }}</dt>
                <dd>
                    <x-status-badge :tone="$statusTones[$accountStatus->value] ?? 'neutral'">
                        {{ __('auth.account.status.'.$accountStatus->value) }}
                    </x-status-badge>
                </dd>
            </div>
        </dl>

        <x-slot:footer>
            <h2 class="mb-3 text-base font-semibold text-ink">{{ __('auth.account.contacts_heading') }}</h2>
            <dl class="grid gap-4 sm:grid-cols-2">
                <div>
                    <dt class="text-sm font-semibold text-ink-muted">{{ __('auth.account.email_label') }}</dt>
                    <dd class="text-base text-ink">{{ $maskedEmail ?? __('auth.account.not_provided') }}</dd>
                </div>
                <div>
                    <dt class="text-sm font-semibold text-ink-muted">{{ __('auth.account.phone_label') }}</dt>
                    <dd class="text-base text-ink">{{ $maskedPhone ?? __('auth.account.not_provided') }}</dd>
                </div>
            </dl>
        </x-slot:footer>
    </x-card>

    <x-card :heading="__('notifications.preferences.heading')" level="2">
        @if ($preferencesSaved)
            <x-alert type="success" class="mb-5">{{ $preferencesSaved }}</x-alert>
        @endif

        <form wire:submit="savePreferences" class="flex flex-col gap-5" novalidate>
            <x-checkbox
                name="email_notifications"
                :label="__('notifications.preferences.email_label')"
                :help="$hasVerifiedEmail ? __('notifications.preferences.email_help') : __('notifications.preferences.email_unavailable')"
                wire:model="email_notifications"
            />

            <div class="max-w-xs">
                <x-select name="locale" :label="__('notifications.preferences.locale_label')" wire:model="locale" :options="$locales" />
            </div>

            <x-button type="submit" wire:loading.attr="disabled" wire:target="savePreferences">
                {{ __('notifications.preferences.submit') }}
            </x-button>
        </form>
    </x-card>

    <x-card :heading="__('auth.account.change_password_heading')" level="2">
        @if ($passwordUpdated)
            <x-alert type="success" class="mb-5">{{ $passwordUpdated }}</x-alert>
        @endif

        <form wire:submit="updatePassword" class="flex flex-col gap-5" novalidate>
            <x-input
                name="current_password"
                type="password"
                label="{{ __('auth.account.current_password_label') }}"
                wire:model="current_password"
                autocomplete="current-password"
                required
            />

            <x-input
                name="password"
                type="password"
                label="{{ __('auth.account.new_password_label') }}"
                wire:model="password"
                autocomplete="new-password"
                required
            />

            <x-input
                name="password_confirmation"
                type="password"
                label="{{ __('auth.account.new_password_confirmation_label') }}"
                wire:model="password_confirmation"
                autocomplete="new-password"
                required
            />

            <x-button type="submit" wire:loading.attr="disabled" wire:target="updatePassword">
                <span wire:loading.remove wire:target="updatePassword">{{ __('auth.account.submit_password') }}</span>
                <span wire:loading wire:target="updatePassword" class="inline-flex items-center gap-2">
                    <x-icon name="spinner" class="motion-safe:animate-spin" />
                    {{ __('common.loading') }}
                </span>
            </x-button>
        </form>
    </x-card>

    <x-card :heading="__('auth.account.delete_heading')" level="2" class="border-danger">
        <div class="flex flex-col gap-3 text-base text-ink-soft">
            <p>{{ __('auth.account.delete_explanation') }}</p>
            <ul class="list-disc ps-5">
                <li>{{ __('auth.account.delete_effects.removed') }}</li>
                <li>{{ __('auth.account.delete_effects.kept') }}</li>
                <li>{{ __('auth.account.delete_effects.final') }}</li>
            </ul>
        </div>

        <form wire:submit="deleteAccount" class="mt-5 flex flex-col gap-5" novalidate>
            <x-input
                name="delete_password"
                type="password"
                label="{{ __('auth.account.delete_password_label') }}"
                wire:model="delete_password"
                autocomplete="current-password"
                required
            />

            <x-button type="submit" variant="danger" wire:loading.attr="disabled" wire:target="deleteAccount">
                {{ __('auth.account.delete_submit') }}
            </x-button>
        </form>
    </x-card>
</div>
