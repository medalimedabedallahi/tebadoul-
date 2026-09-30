<div class="flex flex-col gap-8">
    <div class="flex max-w-prose flex-col gap-2">
        <h1 class="text-3xl font-bold text-ink">{{ __('notifications.heading') }}</h1>
        <p class="text-base text-ink-muted">{{ __('notifications.intro') }}</p>
    </div>

    @if ($allMarked)
        <x-alert type="success">{{ $allMarked }}</x-alert>
    @endif

    <div class="flex flex-wrap items-end justify-between gap-4">
        <div class="w-full max-w-xs">
            <x-select name="notifications_filter" :label="__('notifications.filter')" wire:model.live="filter" :options="$filters" />
        </div>

        <div class="flex flex-wrap items-center gap-3">
            <p class="text-sm text-ink-muted" role="status">{{ trans_choice('notifications.unread_count', $unreadCount, ['count' => $unreadCount]) }}</p>
            @if ($unreadCount > 0)
                <x-button variant="secondary" wire:click="markAllRead" wire:loading.attr="disabled" wire:target="markAllRead">
                    {{ __('notifications.mark_all_read') }}
                </x-button>
            @endif
        </div>
    </div>

    @if ($notifications->isEmpty())
        <x-empty-state :title="__('notifications.empty_title')" :description="__('notifications.empty_description')" icon="inbox" />
    @else
        <ol class="flex flex-col gap-3">
            @foreach ($notifications as $notification)
                <li wire:key="notification-{{ $notification->id }}">
                    <x-card :class="$notification->read_at === null ? 'border-s-4 border-s-accent' : ''">
                        <div class="flex flex-wrap items-start justify-between gap-4">
                            <div class="flex min-w-0 flex-col gap-1">
                                <p @class(['text-base text-ink', 'font-semibold' => $notification->read_at === null])>
                                    {{ __('notifications.types.'.$notification->type) }}
                                </p>
                                <p class="text-sm text-ink-muted">
                                    <time datetime="{{ $notification->created_at?->toIso8601String() }}">{{ $notification->created_at?->translatedFormat('j F Y, H:i') }}</time>
                                </p>
                            </div>

                            <div class="flex flex-wrap items-center gap-2">
                                @if ($notification->read_at === null)
                                    <x-status-badge tone="brand">{{ __('notifications.unread') }}</x-status-badge>
                                @endif
                                <x-button wire:click="open('{{ $notification->id }}')">{{ __('notifications.open') }}</x-button>
                                @if ($notification->read_at === null)
                                    <x-button variant="ghost" wire:click="markRead('{{ $notification->id }}')">{{ __('notifications.mark_read') }}</x-button>
                                @endif
                            </div>
                        </div>
                    </x-card>
                </li>
            @endforeach
        </ol>

        <x-pagination :paginator="$notifications" />
    @endif
</div>
