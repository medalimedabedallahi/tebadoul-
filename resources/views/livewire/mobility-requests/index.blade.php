@php
    $statusTones = [
        'draft' => 'neutral',
        'published' => 'success',
        'paused' => 'warning',
        'matched' => 'brand',
        'expired' => 'warning',
        'closed' => 'neutral',
    ];
    $origin = $profile?->moughataa;
@endphp

<div class="flex flex-col gap-10">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div class="flex max-w-prose flex-col gap-2">
            <h1 class="text-3xl font-bold text-ink">{{ __('requests.index.heading') }}</h1>
            <p class="text-base text-ink-muted">{{ __('requests.index.intro') }}</p>
        </div>
        <x-button :href="route('requests.create')">{{ __('requests.index.create') }}</x-button>
    </div>

    @if ($profile === null)
        <x-alert type="warning">
            {{ __('requests.index.profile_missing') }}
            <a href="{{ route('profile.edit') }}" class="font-semibold underline underline-offset-4">{{ __('requests.index.complete_profile') }}</a>
        </x-alert>
    @endif

    @if ($requests->isEmpty())
        <x-empty-state :title="__('requests.index.empty_title')" :description="__('requests.index.empty_description')">
            <x-button :href="route('requests.create')">{{ __('requests.index.create') }}</x-button>
        </x-empty-state>
    @else
        <ul class="divide-y divide-line border-y border-line">
            @foreach ($requests as $request)
                @php
                    $first = $request->destinations->first();
                    $others = $request->destinations->count() - 1;
                    $fromName = $origin?->localizedName() ?? __('requests.index.origin_unknown');
                    $toName = $first ? ($first->moughataa ?? $first->wilaya)->localizedName() : '';
                    $toDetail = $first?->moughataa ? $first->wilaya->localizedName() : null;
                @endphp
                <li wire:key="request-{{ $request->public_id }}" class="flex flex-col gap-4 py-6">
                    <x-exchange
                        :from="$fromName"
                        :from-detail="$origin?->wilaya->localizedName()"
                        :to="$toName"
                        :to-detail="$others > 0 ? trans_choice('requests.index.more_destinations', $others, ['count' => $others]) : $toDetail"
                        :label="__('requests.index.exchange_label', ['from' => $fromName, 'to' => $toName])"
                    />
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <div class="flex flex-wrap items-center gap-3">
                            <x-status-badge :tone="$statusTones[$request->status->value] ?? 'neutral'">
                                {{ __('requests.status.'.$request->status->value) }}
                            </x-status-badge>
                            <span class="text-sm text-ink-muted">
                                {{ __('requests.index.expires_on', ['date' => $request->expires_at->translatedFormat('j F Y')]) }}
                            </span>
                        </div>
                        <x-button variant="secondary" :href="route('requests.show', $request->public_id)">
                            {{ __('requests.index.open') }}
                        </x-button>
                    </div>
                </li>
            @endforeach
        </ul>

        <x-pagination :paginator="$requests" />
    @endif
</div>
