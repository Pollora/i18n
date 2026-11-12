<?php

declare(strict_types=1);

/*
 * Stands in for the WordPress functions the runtime may call. The state they
 * answer from lives in $GLOBALS['i18n_wordpress']; __() and _x() also write
 * each call down in its 'asked' key as [text, context, domain] (unless 'record'
 * is false: the benchmark makes millions of calls), and apply the
 * four gettext filters as wp-includes/l10n.php does (the 'filters' key maps a
 * hook to the suffix its callback appends). The text is marked with its domain
 * (and context) so that a test sees __() ran, unless 'plain' is true: then it
 * comes back as written, as WordPress does with no catalogue.
 */

$GLOBALS['i18n_wordpress'] = ['filters' => [], 'calls' => 0];

/**
 * What apply_filters() does with the gettext hooks, for the hooks named in $GLOBALS['i18n_wordpress']['filters']
 * (hook => suffix the callback appends to the translation).
 */
function i18nWordpressFilter(string $hook, string $translation): string
{
    return $translation.($GLOBALS['i18n_wordpress']['filters'][$hook] ?? '');
}

function __(string $text, string $domain = 'default'): string
{
    $GLOBALS['i18n_wordpress']['calls']++;
    ($GLOBALS['i18n_wordpress']['record'] ?? true) && $GLOBALS['i18n_wordpress']['asked'][] = [$text, null, $domain];

    $translation = ($GLOBALS['i18n_wordpress']['plain'] ?? false) ? $text : '['.$domain.'] '.$text;
    $translation = i18nWordpressFilter('gettext', $translation);

    return i18nWordpressFilter('gettext_'.$domain, $translation);
}

function _x(string $text, string $context, string $domain = 'default'): string
{
    $GLOBALS['i18n_wordpress']['calls']++;
    ($GLOBALS['i18n_wordpress']['record'] ?? true) && $GLOBALS['i18n_wordpress']['asked'][] = [$text, $context, $domain];

    $translation = ($GLOBALS['i18n_wordpress']['plain'] ?? false) ? $text : '['.$domain.'|'.$context.'] '.$text;
    $translation = i18nWordpressFilter('gettext_with_context', $translation);

    return i18nWordpressFilter('gettext_with_context_'.$domain, $translation);
}
