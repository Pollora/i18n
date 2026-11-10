<?php

declare(strict_types=1);

namespace Pollora\I18n\Runtime;

/**
 * Which fields of which class hold a string a person reads.
 *
 * One static array, filled once and never read from disk: looking a class up
 * costs one array access, and nothing here uses reflection.
 */
final class TranslatableRegistry
{
    /** @var array<class-string, list<string>> */
    private static array $fields = [];

    /**
     * Declares the translatable fields of a class. Declaring a class again adds to its fields.
     *
     * @param  class-string  $class
     * @param  list<string>  $fields
     */
    public static function register(string $class, array $fields): void
    {
        self::$fields[$class] = array_values(array_unique([...(self::$fields[$class] ?? []), ...$fields]));
    }

    /**
     * @param  class-string  $class
     * @return list<string> The translatable fields of the class, empty when none is declared
     */
    public static function fieldsFor(string $class): array
    {
        return self::$fields[$class] ?? [];
    }

    /**
     * Forgets every declaration (tests, long-running workers).
     */
    public static function flush(): void
    {
        self::$fields = [];
    }
}
