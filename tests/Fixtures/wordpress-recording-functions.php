<?php

declare(strict_types=1);

/*
 * _n(), _nx() and translate_nooped_plural() as the plural tests need them: the
 * last one follows wp-includes/l10n.php, and each call to gettext is
 * written down, as __() and _x() of wordpress-functions.php do, in
 * $GLOBALS['i18n_wordpress']['asked'] as [text, context, domain].
 */

function _n(string $single, string $plural, int $number, string $domain = 'default'): string
{
    $GLOBALS['i18n_wordpress']['asked'][] = [$single, null, $domain];

    return $number === 1 ? $single : $plural;
}

function _nx(string $single, string $plural, int $number, string $context, string $domain = 'default'): string
{
    $GLOBALS['i18n_wordpress']['asked'][] = [$single, $context, $domain];

    return $number === 1 ? $single : $plural;
}

/**
 * @param  array<int|string, string|null>  $nooped_plural
 */
function translate_nooped_plural(array $nooped_plural, int $count, string $domain = 'default'): string
{
    if ($nooped_plural['domain']) {
        $domain = $nooped_plural['domain'];
    }

    if ($nooped_plural['context']) {
        return _nx((string) $nooped_plural['singular'], (string) $nooped_plural['plural'], $count, $nooped_plural['context'], $domain);
    }

    return _n((string) $nooped_plural['singular'], (string) $nooped_plural['plural'], $count, $domain);
}
