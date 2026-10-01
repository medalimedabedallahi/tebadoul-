<?php

namespace App\Enums;

/**
 * Where an advertisement is shown. The layout picks the page placement from the current route;
 * the footer placement is shown on every page that uses the main layout.
 */
enum AdPlacement: string
{
    case Home = 'home';
    case Auth = 'auth';
    case Member = 'member';
    case Footer = 'footer';
}
