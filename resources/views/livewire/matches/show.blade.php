@php
    $name = fn (?array $reference): ?string => $reference === null ? null : (app()->getLocale() === 'ar' ? $reference['name_ar'] : $reference['name_fr']);
    $date = fn (string $value): string => \Illuminate\Support\Carbon::parse($value)->translatedFormat('j F Y');
    $counterpart = $match['counterpart'];
    $myPlace = $origin?->localizedName() ?? __('requests.index.origin_unknown');
    $theirPlace = $counterpart ? $name($counterpart['moughataa']) : '';
@endphp

<div class="mx-auto flex w-full max-w-2xl flex-col gap-10">
    <div class="flex flex-col gap-3">
        <a href="{{ route('matches.index') }}" class="inline-flex min-h-target items-center gap-2 self-start text-base font-medium text-ink-soft underline-offset-4 hover:text-ink hover:underline">
            <x-icon name="chevron-start" mirror />
            {{ __('matches.show.back') }}
        </a>
        <div class="flex flex-wrap items-center gap-3">
            <h1 class="text-3xl font-bold text-ink">{{ __('matches.show.heading') }}</h1>
            <x-status-badge :tone="$match['status'] === 'invalidated' ? 'warning' : 'brand'">{{ __('matches.status.'.$match['status']) }}</x-status-badge>
        </div>
    </div>

    @if ($match['status'] === 'invalidated' && $match['invalidation_reason'])
        <x-alert type="warning">
            {{ __('matches.show.invalidated', ['reason' => __('matches.invalidation_reasons.'.$match['invalidation_reason'])]) }}
        </x-alert>
    @endif

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

    @if ($match['status'] === 'invited' && $match['expires_at'])
        <x-alert type="info">
            {{ $match['invited_by_me']
                ? __('matches.show.invitation_sent', ['date' => $date($match['expires_at'])])
                : __('matches.show.invitation_received', ['date' => $date($match['expires_at'])]) }}
        </x-alert>
    @endif

    <x-exchange
        :from="$myPlace"
        :from-detail="$origin?->wilaya->localizedName()"
        :to="$theirPlace"
        :to-detail="$counterpart ? $name($counterpart['wilaya']) : null"
        :label="__('matches.index.exchange_label', ['from' => $myPlace, 'to' => $theirPlace])"
        :constrained="false"
        class="border-y border-line py-6"
    />

    @if ($counterpart)
        <section class="flex flex-col gap-3" aria-labelledby="counterpart-heading">
            <h2 id="counterpart-heading" class="text-lg font-semibold text-ink">{{ __('matches.show.counterpart_heading') }}</h2>
            <dl class="grid gap-x-6 gap-y-4 sm:grid-cols-2">
                @foreach (['profession', 'specialty', 'grade', 'sector'] as $field)
                    @if ($counterpart[$field])
                        <div>
                            <dt class="text-sm text-ink-muted">{{ Str::ucfirst(__('profile.fields.'.$field)) }}</dt>
                            <dd class="text-base font-medium text-ink">{{ $name($counterpart[$field]) }}</dd>
                        </div>
                    @endif
                @endforeach
            </dl>
            <p class="text-sm text-ink-muted">
                {{ __('matches.show.available', ['from' => $date($counterpart['available_from']), 'until' => $date($counterpart['expires_at'])]) }}
            </p>
        </section>
    @endif

    <section class="flex flex-col gap-4" aria-labelledby="score-heading">
        <div class="flex flex-wrap items-baseline justify-between gap-3">
            <h2 id="score-heading" class="text-lg font-semibold text-ink">{{ __('matches.show.score_heading') }}</h2>
            <p class="font-display text-2xl font-bold text-ink">{{ __('matches.show.score_total', ['score' => $match['score']]) }}</p>
        </div>
        <ul class="divide-y divide-line border-y border-line">
            @foreach ($match['reasons'] as $reason)
                <li wire:key="reason-{{ $reason['criterion'] }}" class="grid gap-1 py-4 sm:grid-cols-[minmax(0,1fr)_auto] sm:gap-x-6">
                    <span class="text-base font-medium text-ink">{{ __('matches.criteria.'.$reason['criterion']) }}</span>
                    <span class="inline-flex items-center gap-3 sm:row-span-2 sm:self-center">
                        <meter class="score-meter w-24" min="0" max="{{ $reason['max_points'] }}" value="{{ $reason['points'] }}" aria-hidden="true"></meter>
                        <span class="w-14 text-end text-sm font-semibold text-ink-soft" dir="ltr">{{ $reason['points'] }} / {{ $reason['max_points'] }}</span>
                    </span>
                    <p class="text-sm text-ink-muted">{{ __('matches.details.'.$reason['criterion'].'.'.$reason['detail']) }}</p>
                </li>
            @endforeach
        </ul>
        <p class="text-xs text-ink-muted">{{ __('matches.show.rules_version', ['version' => $match['rules_version']]) }}</p>
    </section>

    @if ($match['status'] === 'suggested')
        <x-alert type="info">{{ __('matches.show.invitation_help') }}</x-alert>
    @endif

    @if ($match['interaction_blocked'])
        <x-alert type="warning">{{ __('matches.show.interaction_blocked') }}</x-alert>
    @endif

    @if ($conversation->isNotEmpty() || in_array('send_message', $match['allowed_actions'], true))
        <section class="flex flex-col gap-4" aria-labelledby="conversation-heading">
            <div>
                <h2 id="conversation-heading" class="text-lg font-semibold text-ink">{{ __('matches.show.conversation_heading') }}</h2>
                <p class="text-sm text-ink-muted">{{ __('matches.show.conversation_help') }}</p>
            </div>

            @if ($conversation->isEmpty())
                <p class="rounded-control border border-dashed border-line-strong p-4 text-sm text-ink-muted">{{ __('matches.show.no_messages') }}</p>
            @else
                <ol class="flex max-h-96 flex-col gap-3 overflow-y-auto rounded-card border border-line bg-canvas p-4" aria-live="polite">
                    @foreach ($conversation as $message)
                        @php($fromMe = $message->sender_id === auth()->id())
                        <li wire:key="message-{{ $message->public_id }}" class="flex max-w-[85%] flex-col gap-1 rounded-card px-4 py-3 {{ $fromMe ? 'self-end bg-brand text-white' : 'self-start bg-surface text-ink shadow-card' }}">
                            <p class="whitespace-pre-wrap break-words text-sm">{{ $message->body }}</p>
                            <div class="flex flex-wrap items-center gap-2 text-xs {{ $fromMe ? 'text-white/80' : 'text-ink-muted' }}">
                                <time datetime="{{ $message->created_at?->toIso8601String() }}">{{ $message->created_at?->translatedFormat('j F, H:i') }}</time>
                                @if ($fromMe && $message->read_at)
                                    <span>{{ __('matches.show.message_read') }}</span>
                                @elseif (! $fromMe && ! $message->read_at)
                                    <button type="button" class="font-semibold underline underline-offset-2" wire:click="markMessageRead('{{ $message->public_id }}')">
                                        {{ __('matches.actions.mark_read') }}
                                    </button>
                                @endif
                            </div>
                        </li>
                    @endforeach
                </ol>
            @endif

            @if (in_array('send_message', $match['allowed_actions'], true))
                <form wire:submit="sendMessage" class="flex flex-col gap-3" novalidate>
                    <x-textarea name="body" :label="__('matches.fields.message')" wire:model="messageBody" rows="3" required />
                    <x-button type="submit" class="self-start" wire:loading.attr="disabled" wire:target="sendMessage">
                        {{ __('matches.actions.send_message') }}
                    </x-button>
                </form>
            @endif
        </section>
    @endif

    @if ($match['allowed_actions'] !== [])
        <x-card :heading="__('matches.show.actions_heading')" level="2">
            <div class="flex flex-col gap-6">
                <div class="flex flex-wrap gap-3">
                    @if (in_array('invite', $match['allowed_actions'], true))
                        <x-button wire:click="invite" wire:loading.attr="disabled" wire:target="invite">
                            {{ __('matches.actions.invite') }}
                        </x-button>
                    @endif
                    @if (in_array('accept', $match['allowed_actions'], true))
                        <x-button wire:click="accept" wire:loading.attr="disabled" wire:target="accept">
                            {{ __('matches.actions.accept') }}
                        </x-button>
                    @endif
                    @if (in_array('withdraw', $match['allowed_actions'], true))
                        <x-button
                            variant="danger"
                            wire:click="withdraw"
                            wire:confirm="{{ __('matches.actions.withdraw_confirm') }}"
                            wire:loading.attr="disabled"
                            wire:target="withdraw"
                        >
                            {{ __('matches.actions.withdraw') }}
                        </x-button>
                    @endif
                    @if (in_array('block', $match['allowed_actions'], true))
                        <x-button variant="danger" wire:click="block" wire:confirm="{{ __('matches.actions.block_confirm') }}" wire:loading.attr="disabled" wire:target="block">
                            {{ __('matches.actions.block') }}
                        </x-button>
                    @endif
                    @if (in_array('unblock', $match['allowed_actions'], true))
                        <x-button variant="secondary" wire:click="unblock" wire:loading.attr="disabled" wire:target="unblock">
                            {{ __('matches.actions.unblock') }}
                        </x-button>
                    @endif
                </div>

                @if (in_array('decline', $match['allowed_actions'], true))
                    <form wire:submit="decline" class="flex flex-col gap-3 border-t border-line pt-5" novalidate>
                        <p class="text-sm text-ink-muted">{{ __('matches.show.decline_help') }}</p>
                        <div class="flex flex-wrap items-end gap-3">
                            <div class="min-w-56 flex-1">
                                <x-select
                                    name="reason"
                                    :label="__('matches.fields.reason')"
                                    wire:model="reason"
                                    :options="$declineReasons"
                                    :placeholder="__('matches.show.reason_optional')"
                                />
                            </div>
                            <x-button type="submit" variant="secondary" wire:loading.attr="disabled" wire:target="decline">
                                {{ __('matches.actions.decline') }}
                            </x-button>
                        </div>
                    </form>
                @endif

                @if (in_array('grant_contact_consent', $match['allowed_actions'], true) || in_array('revoke_contact_consent', $match['allowed_actions'], true) || in_array('view_contact', $match['allowed_actions'], true))
                    <section class="flex flex-col gap-3 border-t border-line pt-5" aria-labelledby="contact-sharing-heading">
                        <h3 id="contact-sharing-heading" class="text-base font-semibold text-ink">{{ __('matches.show.contact_heading') }}</h3>
                        <p class="text-sm text-ink-muted">{{ __('matches.show.contact_help') }}</p>
                        <dl class="grid gap-2 text-sm sm:grid-cols-2">
                            <div class="flex items-center gap-2">
                                <dt class="text-ink-muted">{{ __('matches.show.my_consent') }}</dt>
                                <dd class="font-semibold text-ink">{{ $match['contact_sharing']['my_consent'] ? __('common.yes') : __('common.no') }}</dd>
                            </div>
                            <div class="flex items-center gap-2">
                                <dt class="text-ink-muted">{{ __('matches.show.counterpart_consent') }}</dt>
                                <dd class="font-semibold text-ink">{{ $match['contact_sharing']['counterpart_consent'] ? __('common.yes') : __('common.no') }}</dd>
                            </div>
                        </dl>
                        <div class="flex flex-wrap gap-3">
                            @if (in_array('grant_contact_consent', $match['allowed_actions'], true))
                                <x-button wire:click="grantContactConsent" wire:loading.attr="disabled" wire:target="grantContactConsent">
                                    {{ __('matches.actions.grant_contact') }}
                                </x-button>
                            @endif
                            @if (in_array('revoke_contact_consent', $match['allowed_actions'], true))
                                <x-button variant="secondary" wire:click="revokeContactConsent" wire:loading.attr="disabled" wire:target="revokeContactConsent">
                                    {{ __('matches.actions.revoke_contact') }}
                                </x-button>
                            @endif
                            @if (in_array('view_contact', $match['allowed_actions'], true))
                                <x-button variant="secondary" wire:click="revealContact" wire:loading.attr="disabled" wire:target="revealContact">
                                    {{ __('matches.actions.view_contact') }}
                                </x-button>
                            @endif
                        </div>

                        @if ($contact)
                            <x-alert type="success" :title="__('matches.show.contact_available')">
                                <dl class="flex flex-col gap-1">
                                    @if ($contact['email'])
                                        <div><dt class="inline font-semibold">{{ __('matches.show.email') }} :</dt> <dd class="inline" dir="ltr">{{ $contact['email'] }}</dd></div>
                                    @endif
                                    @if ($contact['phone'])
                                        <div><dt class="inline font-semibold">{{ __('matches.show.phone') }} :</dt> <dd class="inline" dir="ltr">{{ $contact['phone'] }}</dd></div>
                                    @endif
                                </dl>
                            </x-alert>
                        @endif
                    </section>
                @endif

                @if (in_array('update_progress', $match['allowed_actions'], true))
                    <form wire:submit="updateProgress" class="flex flex-col gap-3 border-t border-line pt-5" novalidate>
                        <p class="text-sm text-ink-muted">{{ __('matches.show.progress_help') }}</p>
                        <div class="flex flex-wrap items-end gap-3">
                            <div class="min-w-56 flex-1">
                                <x-select
                                    name="status"
                                    :label="__('matches.fields.status')"
                                    wire:model="status"
                                    :options="collect($match['allowed_progress_statuses'])->mapWithKeys(fn ($status) => [$status => __('matches.status.'.$status)])->all()"
                                    :placeholder="true"
                                    required
                                />
                            </div>
                            <x-button type="submit" wire:loading.attr="disabled" wire:target="updateProgress">
                                {{ __('matches.actions.update_progress') }}
                            </x-button>
                        </div>
                    </form>
                @endif

                @if (in_array('report', $match['allowed_actions'], true))
                    <form wire:submit="report" class="flex flex-col gap-3 border-t border-line pt-5" novalidate>
                        <h3 class="text-base font-semibold text-ink">{{ __('matches.show.report_heading') }}</h3>
                        <p class="text-sm text-ink-muted">{{ __('matches.show.report_help') }}</p>
                        <x-select name="report_reason" :label="__('matches.fields.report_reason')" :error="$errors->first('reason')" wire:model="reportReason" :options="$reportReasons" :placeholder="true" required />
                        <x-textarea name="report_details" :label="__('matches.fields.report_details')" :error="$errors->first('details')" wire:model="reportDetails" rows="3" />
                        <x-button type="submit" variant="secondary" class="self-start" wire:loading.attr="disabled" wire:target="report">
                            {{ __('matches.actions.report') }}
                        </x-button>
                    </form>
                @endif
            </div>
        </x-card>
    @endif

    @if ($match['my_request'])
        <x-button variant="secondary" class="self-start" :href="route('requests.show', $match['my_request'])">
            {{ __('matches.show.my_request') }}
        </x-button>
    @endif
</div>
