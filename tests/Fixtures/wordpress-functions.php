<?php

declare(strict_types=1);

/*
 * Stands in for the four WordPress functions the runtime may call. The state
 * they answer from lives in $GLOBALS['i18n_wordpress']; __() and _x() also write
 * each call down in its 'asked' key as [text, context, domain].
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
    $GLOBALS['i18n_wordpress']['asked'][] = [$text, null, $domain];

    return '['.$domain.'] '.$text;
}

function _x(string $text, string $context, string $domain = 'default'): string
{
    $GLOBALS['i18n_wordpress']['calls']++;
    $GLOBALS['i18n_wordpress']['asked'][] = [$text, $context, $domain];

    return '['.$domain.'|'.$context.'] '.$text;
}
