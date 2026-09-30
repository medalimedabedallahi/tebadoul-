<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Version of the terms of use and privacy policy
    |--------------------------------------------------------------------------
    |
    | Recorded with each acceptance (users.terms_version) at registration. Change it whenever the
    | texts in lang/{fr,ar}/legal.php change in substance, so that each account keeps the version
    | it actually accepted.
    |
    */

    'version' => '2026-09-30',

    /*
    |--------------------------------------------------------------------------
    | Draft notice
    |--------------------------------------------------------------------------
    |
    | While true, both pages show that the texts await validation by a lawyer. Set it to false
    | only once the validated texts are published.
    |
    */

    'draft' => (bool) env('LEGAL_TEXTS_DRAFT', true),

];
