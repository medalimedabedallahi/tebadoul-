@php
    $statusTones = [
        'draft' => 'neutral',
        'published' => 'success',
        'paused' => 'warning',
        'matched' => 'brand',
        'expired' => 'warning',
        'closed' => 'neutral',
    ];
    $dateFormat = 'j F Y';
@endphp

<div class="mx-auto flex w-full max-w-2xl flex-col gap-8">
    <div class="flex flex-col gap-3">
        <a href="{{ route('requests.index') }}" class="inline-flex min-h-target items-center gap-2 self-start text-base font-medium text-brand-strong underline-offset-4 hover:underline">
            <x-icon name="chevron-start" mirror />
            {{ __('requests.show.back') }}
        </a>
        <h1 class="text-3xl font-bold text-ink">{{ __('requests.show.heading') }}</h1>
    </div>

    @if ($actionMessage)
        <x-alert type="success">{{ $actionMessage }}</x-alert>
    @endif

    @if ($actionErrors !== [])
        <x-alert type="danger">
            <ul class="list-inside list-disc">
                @foreach ($actionErrors as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </x-alert>
    @endif

    <x-card>
        <dl class="grid gap-4 sm:grid-cols-2">
            <div>
                <dt class="text-sm font-semibold text-ink-muted">{{ __('requests.show.status_label') }}</dt>
                <dd>
                    <x-status-badge :tone="$statusTones[$request->status->value] ?? 'neutral'">
                        {{ __('requests.status.'.$request->status->value) }}
                    </x-status-badge>
                </dd>
            </div>
            <div>
                <dt class="text-sm font-semibold text-ink-muted">{{ __('requests.show.available_from_label') }}</dt>
                <dd class="text-base text-ink">{{ $request->available_from->translatedFormat($dateFormat) }}</dd>
            </div>
            <div>
                <dt class="text-sm font-semibold text-ink-muted">{{ __('requests.show.expires_at_label') }}</dt>
                <dd class="text-base text-ink">{{ $request->expires_at->translatedFormat($dateFormat) }}</dd>
            </div>
            @if ($request->published_at)
                <div>
                    <dt class="text-sm font-semibold text-ink-muted">{{ __('requests.show.published_at_label') }}</dt>
                    <dd class="text-base text-ink">{{ $request->published_at->translatedFormat($dateFormat) }}</dd>
                </div>
            @endif
        </dl>

        @if ($request->closed_at)
            <p class="mt-4 text-sm text-ink-muted">
                {{ __('requests.show.closed_label', [
                    'date' => $request->closed_at->translatedFormat($dateFormat),
                    'reason' => __('requests.close_reasons.'.$request->close_reason?->value),
                ]) }}
            </p>
        @endif
    </x-card>

    @php
        $first = $request->destinations->first();
        $fromName = $profile?->moughataa->localizedName() ?? __('requests.index.origin_unknown');
        $toName = $first ? ($first->moughataa ?? $first->wilaya)->localizedName() : '';
    @endphp
    <x-exchange
        :from="$fromName"
        :from-detail="$profile ? $profile->profession->localizedName() : null"
        :to="$toName"
        :to-detail="$first?->moughataa ? $first->wilaya->localizedName() : null"
        :label="__('requests.index.exchange_label', ['from' => $fromName, 'to' => $toName])"
        :constrained="false"
        class="border-y border-line py-6"
    />

    <section class="flex flex-col gap-2" aria-labelledby="origin-heading">
        <h2 id="origin-heading" class="text-lg font-semibold text-ink">{{ __('requests.show.origin_heading') }}</h2>
        @if ($profile)
            <p class="text-base text-ink">{{ $profile->profession->localizedName() }}</p>
            <p class="text-base text-ink-soft">
                {{ __('requests.show.origin_place', ['moughataa' => $profile->moughataa->localizedName(), 'wilaya' => $profile->moughataa->wilaya->localizedName()]) }}
            </p>
            @if ($profile->establishment)
                <p class="text-base text-ink-soft">{{ $profile->establishment->localizedName() }}</p>
            @endif
        @else
            <p class="text-base text-ink-muted">
                {{ __('requests.show.no_profile') }}
                <a href="{{ route('profile.edit') }}" class="font-semibold text-ink underline underline-offset-4">{{ __('requests.index.complete_profile') }}</a>
            </p>
        @endif
    </section>

    @if ($matchCount > 0)
        <x-alert type="success">
            {{ trans_choice('requests.show.matches_found', $matchCount, ['count' => $matchCount]) }}
            <a href="{{ route('matches.index') }}" class="font-semibold underline underline-offset-4">{{ __('requests.show.see_matches') }}</a>
        </x-alert>
    @endif

    <section class="flex flex-col gap-3" aria-labelledby="destinations-heading">
        <h2 id="destinations-heading" class="text-lg font-semibold text-ink">{{ __('requests.show.destinations_heading') }}</h2>
        <ol class="divide-y divide-line border-y border-line">
            @foreach ($request->destinations as $destination)
                <li wire:key="destination-{{ $destination->id }}" class="flex items-baseline gap-4 py-3">
                    <span class="w-6 shrink-0 font-display text-lg font-bold text-ink-muted" aria-hidden="true">{{ $destination->priority }}</span>
                    <div class="flex flex-col">
                        <span class="text-base font-semibold text-ink">{{ ($destination->moughataa ?? $destination->wilaya)->localizedName() }}</span>
                        <span class="text-sm text-ink-muted">
                            @if ($destination->moughataa)
                                {{ $destination->wilaya->localizedName() }}
                            @else
                                {{ __('requests.edit.any_moughataa') }}
                            @endif
                        </span>
                        @if ($destination->establishment)
                            <span class="text-sm text-ink-muted">{{ $destination->establishment->localizedName() }}</span>
                        @endif
                    </div>
                </li>
            @endforeach
        </ol>
    </section>

    @if ($actions !== [])
        <x-card :heading="__('requests.show.actions_heading')" level="2">
            <div class="flex flex-col gap-6">
                <div class="flex flex-wrap gap-3">
                    @if (in_array('publish', $actions, true))
                        <x-button wire:click="publish" wire:loading.attr="disabled" wire:target="publish">
                            {{ $request->status->value === 'paused' ? __('requests.show.resume') : __('requests.show.publish') }}
                        </x-button>
                    @endif
                    @if (in_array('pause', $actions, true))
                        <x-button variant="secondary" wire:click="pause" wire:loading.attr="disabled" wire:target="pause">
                            {{ __('requests.show.pause') }}
                        </x-button>
                    @endif
                    @if (in_array('update', $actions, true))
                        <x-button variant="secondary" :href="route('requests.edit', $request->public_id)">
                            {{ __('requests.show.update') }}
                        </x-button>
                    @endif
                </div>

                @if (in_array('renew', $actions, true))
                    <form method="post" wire:submit="renew" class="flex flex-col gap-3 border-t border-line pt-5" novalidate>
                        <p class="text-sm text-ink-muted">{{ __('requests.show.renew_help') }}</p>
                        <div class="flex flex-wrap items-end gap-3">
                            <div class="min-w-56 flex-1">
                                <x-input name="expires_at" type="date" label="{{ Str::ucfirst(__('requests.fields.expires_at')) }}" wire:model="renewExpiresAt" required />
                            </div>
                            <x-button type="submit" wire:loading.attr="disabled" wire:target="renew">{{ __('requests.show.renew') }}</x-button>
                        </div>
                    </form>
                @endif

                @if (in_array('close', $actions, true))
                    <form method="post" wire:submit="close" class="flex flex-col gap-3 border-t border-line pt-5" novalidate>
                        <p class="text-sm text-ink-muted">{{ __('requests.show.close_help') }}</p>
                        <div class="flex flex-wrap items-end gap-3">
                            <div class="min-w-56 flex-1">
                                <x-select
                                    name="reason"
                                    label="{{ Str::ucfirst(__('requests.fields.reason')) }}"
                                    wire:model="closeReason"
                                    :options="$closeReasons"
                                    :placeholder="true"
                                    required
                                />
                            </div>
                            <x-button type="submit" variant="danger" wire:loading.attr="disabled" wire:target="close">{{ __('requests.show.close') }}</x-button>
                        </div>
                    </form>
                @endif

                @if (in_array('delete', $actions, true))
                    <div class="flex flex-col gap-3 border-t border-line pt-5">
                        <p class="text-sm text-ink-muted">{{ __('requests.show.delete_help') }}</p>
                        <div class="flex flex-wrap gap-3">
                            @if ($confirmingDelete)
                                <x-button variant="danger" wire:click="delete" wire:loading.attr="disabled" wire:target="delete">
                                    {{ __('requests.show.delete_confirm') }}
                                </x-button>
                                <x-button variant="secondary" wire:click="$set('confirmingDelete', false)">{{ __('requests.edit.cancel') }}</x-button>
                            @else
                                <x-button variant="secondary" wire:click="$set('confirmingDelete', true)">{{ __('requests.show.delete') }}</x-button>
                            @endif
                        </div>
                    </div>
                @endif
            </div>
        </x-card>
    @endif
</div>
