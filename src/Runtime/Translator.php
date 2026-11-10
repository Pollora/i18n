<?php

declare(strict_types=1);

namespace Pollora\I18n\Runtime;

/**
 * Translates a string that came from a constant, such as an attribute argument.
 *
 * It makes one call to WordPress's __() and nothing else: no reflection, no
 * file read. It does nothing when WordPress is not loaded, when the field is
 * not declared translatable, or when the site runs in its source language
 * with no catalogue loaded for the domain.
 */
final class Translator
{
    private const string SOURCE_LOCALE = 'en_US';

    /**
     * @param  class-string  $class  The class that carries the string
     * @param  string  $field  The field of that class holding it
     * @param  string  $value  The string in its source language
     * @param  string  $domain  The text domain of the catalogue
     */
    public static function translate(string $class, string $field, string $value, string $domain): string
    {
        if ($value === '' || ! function_exists('__') || ! in_array($field, TranslatableRegistry::fieldsFor($class), true)) {
            return $value;
        }

        if (self::needsNoCatalogue($domain)) {
            return $value;
        }

        return __($value, $domain);
    }

    /**
     * Source locale and no catalogue for the domain: __() would return the string unchanged. Reads WordPress's memory only.
     */
    private static function needsNoCatalogue(string $domain): bool
    {
        return function_exists('get_locale')
            && function_exists('is_textdomain_loaded')
            && get_locale() === self::SOURCE_LOCALE
            && ! is_textdomain_loaded($domain);
    }
}
