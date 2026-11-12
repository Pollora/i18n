<?php

declare(strict_types=1);

namespace Pollora\I18n\Runtime;

use Stringable;

/**
 * Translates a string that came from a constant, such as an attribute argument.
 *
 * It makes one call to WordPress's __() and nothing else: no reflection, no
 * file read. It does nothing when WordPress is not loaded or when the field is
 * not declared translatable. Otherwise it always calls __(): whether a
 * catalogue is loaded, or the site runs in its source language, is for
 * WordPress and its gettext filters to decide, not for this class.
 */
final class Translator
{
    /** The deepest array resolve() walks: more is a circular reference, not an argument list. */
    public const int MAX_DEPTH = 16;

    /**
     * @param  class-string  $class  The class that carries the string
     * @param  string  $field  The field of that class holding it
     * @param  string  $value  The string in its source language
     * @param  string  $domain  The text domain of the catalogue
     */
    public static function translate(string $class, string $field, string $value, string $domain): string
    {
        if ($value === '' || ! function_exists('__') || ! function_exists('_x') || ! in_array($field, TranslatableRegistry::fieldsFor($class), true)) {
            return $value;
        }

        return __($value, $domain);
    }

    /**
     * Replaces every Translatable in a value by its translation, at any depth of an array.
     *
     * A TranslatablePlural becomes the structure of WordPress's _n_noop() or,
     * with a context, _nx_noop(): the count is not known yet, and the code that
     * displays the text gives it to translate_nooped_plural(). It never becomes
     * a string, whatever the locale.
     *
     * A value that is neither an array nor one of these comes back at once.
     * An array with none comes back as it is, without a copy; one with some is
     * copied once, on the first replacement. Keys are kept and objects other
     * than these are not looked into. No reflection.
     *
     * @param  mixed  $value  A Translatable, a TranslatablePlural, or an array that may hold some
     * @param  string  $defaultDomain  The text domain of a Translatable that names none
     *
     * @throws TranslatableTooDeepException When arrays are nested more than MAX_DEPTH levels
     */
    public static function resolve(mixed $value, string $defaultDomain): mixed
    {
        if (is_array($value)) {
            return self::resolveArray($value, $defaultDomain, self::hasWordPress(), 1);
        }

        if ($value instanceof Translatable) {
            return self::translateText($value, $defaultDomain, self::hasWordPress());
        }

        return $value instanceof TranslatablePlural
            ? self::nooped($value, $defaultDomain)
            : $value;
    }

    /**
     * @param  array<array-key, mixed>  $values
     * @return array<array-key, mixed>
     */
    private static function resolveArray(array $values, string $defaultDomain, bool $hasWordPress, int $depth): array
    {
        if ($depth > self::MAX_DEPTH) {
            throw TranslatableTooDeepException::exceeds(self::MAX_DEPTH);
        }

        $resolved = $values;

        foreach ($values as $key => $item) {
            if (is_array($item)) {
                $inner = self::resolveArray($item, $defaultDomain, $hasWordPress, $depth + 1);

                if ($inner !== $item) {
                    $resolved[$key] = $inner;
                }
            } elseif ($item instanceof Stringable) {
                // One check for every scalar; the two marked types are Stringable.
                if ($item instanceof Translatable) {
                    $resolved[$key] = self::translateText($item, $defaultDomain, $hasWordPress);
                } elseif ($item instanceof TranslatablePlural) {
                    $resolved[$key] = self::nooped($item, $defaultDomain);
                }
            }
        }

        return $resolved;
    }

    private static function translateText(Translatable $translatable, string $defaultDomain, bool $hasWordPress): string
    {
        $domain = $translatable->domain ?? $defaultDomain;

        if (! $hasWordPress) {
            return $translatable->text;
        }

        return $translatable->context === null
            ? __($translatable->text, $domain)
            : _x($translatable->text, $translatable->context, $domain);
    }

    /**
     * The structure of _n_noop() or _nx_noop(), key for key: built by WordPress when it is there, by hand when it is not.
     *
     * @return array<int|string, string|null>
     */
    private static function nooped(TranslatablePlural $plural, string $defaultDomain): array
    {
        $domain = $plural->domain ?? $defaultDomain;

        if ($plural->context === null) {
            return function_exists('_n_noop')
                ? _n_noop($plural->singular, $plural->plural, $domain)
                : [
                    0 => $plural->singular,
                    1 => $plural->plural,
                    'singular' => $plural->singular,
                    'plural' => $plural->plural,
                    'context' => null,
                    'domain' => $domain,
                ];
        }

        return function_exists('_nx_noop')
            ? _nx_noop($plural->singular, $plural->plural, $plural->context, $domain)
            : [
                0 => $plural->singular,
                1 => $plural->plural,
                2 => $plural->context,
                'singular' => $plural->singular,
                'plural' => $plural->plural,
                'context' => $plural->context,
                'domain' => $domain,
            ];
    }

    /**
     * What resolve() does with a text: the WordPress functions are looked up once per call, not once per text.
     * Without WordPress (the case tests/Fixtures/request.php runs in a process of its own) the texts stay as written.
     * WordPress is there only when both __() and _x() exist: Laravel declares a __() of its own, with another signature,
     * and a text with a context calls _x().
     */
    private static function hasWordPress(): bool
    {
        return function_exists('__') && function_exists('_x');
    }
}
