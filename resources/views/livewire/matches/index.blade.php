@php
    $name = fn (?array $reference): ?string => $reference === null ? null : (app()->getLocale() === 'ar' ? $reference['name_ar'] : $reference['name_fr']);
    $myPlace = $origin?->localizedName() ?? __('requests.index.origin_unknown');
@endphp

<div class="flex flex-col gap-10">
    <div class="flex max-w-prose flex-col gap-2">
        <h1 class="text-3xl font-bold text-ink">{{ __('matches.index.heading') }}</h1>
        <p class="text-base text-ink-muted">{{ __('matches.index.intro') }}</p>
    </div>

    @unless ($matchingEnabled)
        <x-alert type="info">{{ __('matches.index.disabled') }}</x-alert>
    @endunless

    @if ($cards === [])
        <x-empty-state :title="__('matches.index.empty_title')" :description="__('matches.index.empty_description')">
            <x-button variant="secondary" :href="route('requests.index')">{{ __('nav.requests') }}</x-button>
        </x-empty-state>
    @else
        <ul class="divide-y divide-line border-y border-line">
            @foreach ($cards as $card)
                @php
                    $other = $card['counterpart'];
                    $theirPlace = $other ? $name($other['moughataa']) : '';
                @endphp
                <li wire:key="match-{{ $card['public_id'] }}" class="flex flex-col gap-4 py-6">
                    <x-exchange
                        :from="$myPlace"
                        :from-detail="$origin?->wilaya->localizedName()"
                        :to="$theirPlace"
                        :to-detail="$other ? $name($other['wilaya']) : null"
                        :label="__('matches.index.exchange_label', ['from' => $myPlace, 'to' => $theirPlace])"
                    />
                    <div class="flex flex-wrap items-center justify-between gap-4">
                        <div class="flex flex-wrap items-center gap-x-4 gap-y-2">
                            <x-status-badge tone="brand">{{ __('matches.status.'.$card['status']) }}</x-status-badge>
                            <span class="inline-flex items-center gap-2">
                                <meter class="score-meter" min="0" max="100" value="{{ $card['score'] }}" aria-hidden="true">{{ $card['score'] }}/100</meter>
                                <span class="text-sm font-semibold text-ink">{{ __('matches.index.score', ['score' => $card['score']]) }}</span>
                            </span>
                            @if ($other)
                                <span class="text-sm text-ink-muted">{{ $name($other['profession']) }}</span>
                            @endif
                        </div>
                        <x-button variant="secondary" :href="route('matches.show', $card['public_id'])">
                            {{ __('matches.index.open') }}
                        </x-button>
                    </div>
                </li>
            @endforeach
        </ul>

        <x-pagination :paginator="$matches" />
    @endif
</div>
