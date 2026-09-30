<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Matching activation
    |--------------------------------------------------------------------------
    |
    | ADR 0001: matching stays disabled until the compatibility rules are validated by the
    | education and health experts. While disabled, no match is computed and existing matches
    | are left untouched. Enable, then run `php artisan matching:recompute` once.
    |
    */

    'enabled' => (bool) env('MATCHING_ENABLED', false),

    /*
    |--------------------------------------------------------------------------
    | Availability tolerance
    |--------------------------------------------------------------------------
    |
    | Two requests are compatible when their periods [available_from, expires_at] overlap, or
    | are at most this many days apart (PRD 7.1 "tolerance configuree").
    |
    */

    'availability_tolerance_days' => (int) env('MATCHING_AVAILABILITY_TOLERANCE_DAYS', 0),

    /*
    |--------------------------------------------------------------------------
    | Invitation delay
    |--------------------------------------------------------------------------
    |
    | Days the invited participant has to answer. An unanswered invitation then expires
    | (hourly task `matches:expire-invitations`, PRD 8.2 "delai d'action depasse").
    |
    */

    'invitation_ttl_days' => (int) env('MATCHING_INVITATION_TTL_DAYS', 14),

];
