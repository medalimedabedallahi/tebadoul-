<?php

use App\Actions\Auth\PruneExpiredPendingAccounts;
use App\Actions\Matching\ComputeDirectMatches;
use App\Actions\Matching\ExpireInvitations;
use App\Actions\MobilityRequests\ExpireMobilityRequests;
use App\Actions\References\ImportReferences;
use App\Enums\MatchStatus;
use App\Enums\MobilityRequestStatus;
use App\Enums\ReferenceType;
use App\Exceptions\References\InvalidReferenceImportException;
use App\Models\MatchParticipant;
use App\Models\MobilityRequest;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('auth:prune-pending-accounts', function (PruneExpiredPendingAccounts $prune): int {
    $this->info(sprintf('%d expired pending account(s) deleted.', $prune->handle()));

    return 0;
})->purpose('Delete the accounts pending verification, without any verified contact, older than auth.pending_accounts.ttl_days');

Artisan::command('references:import {type : sectors, wilayas, moughataas, professions, specialties, grades or establishments} {path : UTF-8 CSV file}', function (ImportReferences $import): int {
    $type = ReferenceType::tryFrom((string) $this->argument('type'));

    if ($type === null) {
        $this->error('Unknown reference type. Expected one of: '.implode(', ', array_column(ReferenceType::cases(), 'value')).'.');

        return 1;
    }

    try {
        $counts = $import->handle($type, (string) $this->argument('path'));
    } catch (InvalidReferenceImportException $exception) {
        $this->error('Nothing was imported:');

        foreach ($exception->errors as $error) {
            $this->line("  - {$error}");
        }

        return 1;
    }

    $this->info(sprintf('%s: %d created, %d updated, %d unchanged.', $type->value, $counts['created'], $counts['updated'], $counts['unchanged']));

    return 0;
})->purpose('Create or update reference entries from a CSV file, matched on their code (all or nothing)');

Artisan::command('requests:expire', function (ExpireMobilityRequests $expire): int {
    $this->info(sprintf('%d mobility request(s) expired.', $expire->handle()));

    return 0;
})->purpose('Mark published and paused mobility requests past their expiry date as expired');

Artisan::command('matching:recompute', function (ComputeDirectMatches $compute): int {
    if (! config('matching.enabled')) {
        $this->warn('Matching is disabled (MATCHING_ENABLED=false): nothing computed.');

        return 0;
    }

    $totals = ['suggested' => 0, 'updated' => 0, 'invalidated' => 0];
    $liveMatchRequestIds = MatchParticipant::query()
        ->whereHas('mobilityMatch', fn ($match) => $match->whereIn('status', MatchStatus::reevaluable()))
        ->select('mobility_request_id');

    MobilityRequest::withTrashed()
        ->where(fn ($query) => $query->where('status', MobilityRequestStatus::Published)->orWhereIn('id', $liveMatchRequestIds))
        ->chunkById(200, function ($requests) use ($compute, &$totals): void {
            foreach ($requests as $request) {
                foreach ($compute->handle($request) as $key => $count) {
                    $totals[$key] += $count;
                }
            }
        });

    $this->info(sprintf('%d suggested, %d updated, %d invalidated.', $totals['suggested'], $totals['updated'], $totals['invalidated']));

    return 0;
})->purpose('Recompute the matches of every published request (run once after enabling matching or changing the rules)');

Artisan::command('matches:expire-invitations', function (ExpireInvitations $expire): int {
    $this->info(sprintf('%d invitation(s) expired.', $expire->handle()));

    return 0;
})->purpose('Expire unanswered match invitations past their deadline');

Schedule::command('sanctum:prune-expired --hours=24')
    ->daily()
    ->onOneServer()
    ->withoutOverlapping();

Schedule::command('auth:prune-pending-accounts')
    ->daily()
    ->onOneServer()
    ->withoutOverlapping();

Schedule::command('requests:expire')
    ->hourly()
    ->onOneServer()
    ->withoutOverlapping();

Schedule::command('matches:expire-invitations')
    ->hourly()
    ->onOneServer()
    ->withoutOverlapping();
