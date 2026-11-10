<?php

declare(strict_types=1);

namespace Pollora\I18n\Tooling\Console;

use Pollora\I18n\Tooling\InvalidRunException;
use Symfony\Component\Console\Input\InputInterface;

/**
 * Reads typed values out of the options of a command.
 */
final class Options
{
    public static function string(InputInterface $input, string $name): ?string
    {
        $value = $input->getOption($name);

        return is_string($value) && $value !== '' ? $value : null;
    }

    /**
     * @throws InvalidRunException
     */
    public static function requiredString(InputInterface $input, string $name): string
    {
        return self::string($input, $name) ?? throw InvalidRunException::missingOption($name);
    }

    /**
     * @return list<string>
     */
    public static function strings(InputInterface $input, string $name): array
    {
        $value = $input->getOption($name);

        return is_array($value) ? array_values(array_filter($value, static fn (mixed $item): bool => is_string($item) && $item !== '')) : [];
    }

    /**
     * @return non-empty-list<string>
     *
     * @throws InvalidRunException
     */
    public static function requiredStrings(InputInterface $input, string $name): array
    {
        $values = self::strings($input, $name);

        return $values === [] ? throw InvalidRunException::missingOption($name) : $values;
    }

    public static function flag(InputInterface $input, string $name): bool
    {
        return $input->getOption($name) === true;
    }
}
