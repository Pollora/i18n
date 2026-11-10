<?php

declare(strict_types=1);

/*
 * _n_noop() and _nx_noop() as wp-includes/l10n.php defines them, copied
 * without the documentation. They stay out of wordpress-functions.php so that
 * the tests can run the runtime with and without them: once loaded they exist
 * for the rest of the process.
 */

function _n_noop(string $singular, string $plural, ?string $domain = null): array
{
    $GLOBALS['i18n_wordpress']['noop_calls'] = ($GLOBALS['i18n_wordpress']['noop_calls'] ?? 0) + 1;

    return [
        0 => $singular,
        1 => $plural,
        'singular' => $singular,
        'plural' => $plural,
        'context' => null,
        'domain' => $domain,
    ];
}

function _nx_noop(string $singular, string $plural, string $context, ?string $domain = null): array
{
    $GLOBALS['i18n_wordpress']['noop_calls'] = ($GLOBALS['i18n_wordpress']['noop_calls'] ?? 0) + 1;

    return [
        0 => $singular,
        1 => $plural,
        2 => $context,
        'singular' => $singular,
        'plural' => $plural,
        'context' => $context,
        'domain' => $domain,
    ];
}
