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
    /** The deepest array resolve() walks: more is a circular reference, not an argument list. */
    public const int MAX_DEPTH = 16;

    private const string SOURCE_LOCALE = 'en_US';

    /** WordPress is not loaded: a Translatable becomes its text. */
    private const int NO_WORDPRESS = 0;

    /** Translate every text. */
    private const int TRANSLATE = 1;

    /** Source locale: translate only the domains that have a catalogue loaded. */
    private const int SOURCE_ONLY = 2;

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
     * Replaces every Translatable in a value by its translation, at any depth of an array.
     *
     * A value that is neither an array nor a Translatable comes back at once.
     * An array with no Translatable comes back as it is, without a copy; one
     * with some is copied once, on the first replacement. Keys are kept and
     * objects other than Translatable are not looked into. No reflection.
     *
     * @param  mixed  $value  A Translatable, or an array that may hold some
     * @param  string  $defaultDomain  The text domain of a Translatable that names none
     *
     * @throws TranslatableTooDeepException When arrays are nested more than MAX_DEPTH levels
     */
    public static function resolve(mixed $value, string $defaultDomain): mixed
    {
        if (is_array($value)) {
            return self::resolveArray($value, $defaultDomain, self::mode(), 1);
        }

        return $value instanceof Translatable
            ? self::translateText($value, $defaultDomain, self::mode())
            : $value;
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

    /**
     * @param  array<array-key, mixed>  $values
     * @return array<array-key, mixed>
     */
    private static function resolveArray(array $values, string $defaultDomain, int $mode, int $depth): array
    {
        if ($depth > self::MAX_DEPTH) {
            throw TranslatableTooDeepException::exceeds(self::MAX_DEPTH);
        }

        $resolved = $values;

        foreach ($values as $key => $item) {
            if (is_array($item)) {
                $inner = self::resolveArray($item, $defaultDomain, $mode, $depth + 1);

                if ($inner !== $item) {
                    $resolved[$key] = $inner;
                }
            } elseif ($item instanceof Translatable) {
                $resolved[$key] = self::translateText($item, $defaultDomain, $mode);
            }
        }

        return $resolved;
    }

    private static function translateText(Translatable $translatable, string $defaultDomain, int $mode): string
    {
        $domain = $translatable->domain ?? $defaultDomain;

        if ($mode === self::NO_WORDPRESS || ($mode === self::SOURCE_ONLY && ! is_textdomain_loaded($domain))) {
            return $translatable->text;
        }

        return $translatable->context === null
            ? __($translatable->text, $domain)
            : _x($translatable->text, $translatable->context, $domain);
    }

    /**
     * What resolve() does with a text: the WordPress functions are looked up once per call, not once per text.
     * Without WordPress (the case tests/Fixtures/request.php runs in a process of its own) the texts stay as written.
     */
    private static function mode(): int
    {
        return match (true) {
            ! function_exists('__') => self::NO_WORDPRESS,
            function_exists('get_locale') && function_exists('is_textdomain_loaded') && get_locale() === self::SOURCE_LOCALE => self::SOURCE_ONLY,
            default => self::TRANSLATE,
        };
    }
}
