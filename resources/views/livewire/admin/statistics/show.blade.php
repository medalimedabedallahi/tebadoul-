@php
    $figure = fn (string $label, int $value): array => ['label' => $label, 'value' => $value];
    $groups = [
        'accounts' => [
            $figure(__('admin.statistics.accounts.total'), $statistics['accounts']['total']),
            $figure(__('auth.account.status.active'), $statistics['accounts']['active']),
            $figure(__('auth.account.status.pending_verification'), $statistics['accounts']['pending_verification']),
            $figure(__('auth.account.status.suspended'), $statistics['accounts']['suspended']),
            $figure(__('admin.statistics.accounts.deleted'), $statistics['accounts']['deleted']),
        ],
        'requests' => [
            $figure(__('admin.statistics.requests.published'), $statistics['requests']['published']),
            $figure(__('admin.statistics.requests.with_match'), $statistics['requests']['with_match']),
            $figure(__('admin.statistics.requests.paused'), $statistics['requests']['paused']),
            $figure(__('admin.statistics.requests.matched'), $statistics['requests']['matched']),
        ],
        'agreements' => [
            $figure(__('admin.statistics.mutual_agreements'), $statistics['mutual_agreements']),
            $figure(__('matches.status.invited'), $statistics['matches']['invited']),
            $figure(__('matches.status.approved'), $statistics['matches']['approved']),
        ],
        'reports' => [
            $figure(__('moderation.status.pending'), $statistics['reports']['pending']),
            $figure(__('moderation.status.resolved'), $statistics['reports']['resolved']),
            $figure(__('moderation.status.dismissed'), $statistics['reports']['dismissed']),
        ],
        'coverage' => [
            $figure(__('admin.statistics.coverage.origin_wilayas'), $statistics['coverage']['origin_wilayas']),
            $figure(__('admin.statistics.coverage.destination_wilayas'), $statistics['coverage']['destination_wilayas']),
        ],
    ];
@endphp

<div class="flex flex-col gap-8">
    <div class="flex max-w-prose flex-col gap-2">
        <h1 class="text-3xl font-bold text-ink">{{ __('admin.statistics.heading') }}</h1>
        <p class="text-base text-ink-muted">{{ __('admin.statistics.intro') }}</p>
        <p class="text-sm text-ink-muted">
            {{ __('admin.statistics.generated_at') }}
            <time datetime="{{ $statistics['generated_at'] }}">{{ \Illuminate\Support\Carbon::parse($statistics['generated_at'])->translatedFormat('j F Y, H:i') }}</time>
        </p>
    </div>

    @foreach ($groups as $group => $figures)
        <section aria-labelledby="statistics-{{ $group }}" class="flex flex-col gap-3">
            <h2 id="statistics-{{ $group }}" class="text-xl font-semibold text-ink">{{ __('admin.statistics.groups.'.$group) }}</h2>
            <dl class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                @foreach ($figures as $item)
                    <div class="flex flex-col-reverse gap-1 rounded-card border border-line bg-surface p-5 shadow-card">
                        <dt class="text-sm font-medium text-ink-muted">{{ $item['label'] }}</dt>
                        <dd class="font-display text-3xl font-bold text-ink tabular-nums">{{ number_format($item['value'], 0, ',', ' ') }}</dd>
                    </div>
                @endforeach
            </dl>
            @if ($group === 'requests' && $matchRate !== null)
                <p class="text-sm text-ink-muted">{{ __('admin.statistics.match_rate', ['rate' => $matchRate]) }}</p>
            @endif
        </section>
    @endforeach

    <section aria-labelledby="statistics-sectors" class="flex flex-col gap-3">
        <h2 id="statistics-sectors" class="text-xl font-semibold text-ink">{{ __('admin.statistics.by_sector_heading') }}</h2>
        @if ($statistics['requests']['by_sector'] === [])
            <p class="text-base text-ink-muted">{{ __('admin.statistics.by_sector_empty') }}</p>
        @else
            <div class="overflow-x-auto rounded-card border border-line bg-surface shadow-card">
                <table class="w-full text-start text-base">
                    <caption class="sr-only">{{ __('admin.statistics.by_sector_heading') }}</caption>
                    <thead class="border-b border-line bg-canvas text-sm text-ink-muted">
                        <tr>
                            <th scope="col" class="px-4 py-3 text-start font-semibold">{{ __('admin.statistics.column_sector') }}</th>
                            <th scope="col" class="px-4 py-3 text-end font-semibold">{{ __('admin.statistics.column_requests') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-line">
                        @foreach ($statistics['requests']['by_sector'] as $row)
                            <tr wire:key="sector-{{ $row['code'] }}">
                                <th scope="row" class="px-4 py-3 text-start font-medium text-ink">{{ $sectorNames[$row['code']] ?? $row['code'] }}</th>
                                <td class="px-4 py-3 text-end text-ink tabular-nums">{{ $row['count'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>

    <section aria-labelledby="statistics-matches" class="flex flex-col gap-3">
        <h2 id="statistics-matches" class="text-xl font-semibold text-ink">{{ __('admin.statistics.matches_heading') }}</h2>
        <div class="overflow-x-auto rounded-card border border-line bg-surface shadow-card">
            <table class="w-full text-start text-base">
                <caption class="sr-only">{{ __('admin.statistics.matches_heading') }}</caption>
                <thead class="border-b border-line bg-canvas text-sm text-ink-muted">
                    <tr>
                        <th scope="col" class="px-4 py-3 text-start font-semibold">{{ __('admin.statistics.column_status') }}</th>
                        <th scope="col" class="px-4 py-3 text-end font-semibold">{{ __('admin.statistics.column_matches') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-line">
                    @foreach ($statistics['matches'] as $status => $count)
                        <tr wire:key="match-status-{{ $status }}">
                            <th scope="row" class="px-4 py-3 text-start font-medium text-ink">{{ __('matches.status.'.$status) }}</th>
                            <td class="px-4 py-3 text-end text-ink tabular-nums">{{ $count }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>
</div>
