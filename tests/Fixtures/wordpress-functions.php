<?php

declare(strict_types=1);

/*
 * Stands in for the three WordPress functions the runtime may call. The state
 * they answer from lives in $GLOBALS['i18n_wordpress'].
 */

$GLOBALS['i18n_wordpress'] = ['locale' => 'fr_FR', 'loaded' => [], 'calls' => 0];

function get_locale(): string
{
    return $GLOBALS['i18n_wordpress']['locale'];
}

function is_textdomain_loaded(string $domain): bool
{
    return in_array($domain, $GLOBALS['i18n_wordpress']['loaded'], true);
}

function __(string $text, string $domain = 'default'): string
{
    $GLOBALS['i18n_wordpress']['calls']++;

    return '['.$domain.'] '.$text;
}
