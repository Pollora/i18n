<?php

declare(strict_types=1);

namespace Pollora\I18n\Tooling;

/**
 * A compiled translation file WordPress reads: .mo everywhere, .l10n.php from WordPress 6.5.
 */
enum CompiledFormat: string
{
    /**
     * @param  string  $list  Formats separated by commas, such as "mo,php"
     * @return list<CompiledFormat> Each format once, in the order mo then php
     *
     * @throws InvalidRunException
     */
    public static function fromList(string $list): array
    {
        $found = [];

        foreach (explode(',', $list) as $name) {
            $format = self::tryFrom(trim($name));

            if (! $format instanceof self) {
                throw InvalidRunException::unknownFormat(trim($name));
            }

            $found[$format->value] = $format;
        }

        return array_values(array_filter(self::cases(), static fn (self $format): bool => isset($found[$format->value])));
    }

    /**
     * The extension of the compiled file for a .po file named "{domain}-{locale}".
     */
    public function extension(): string
    {
        return match ($this) {
            self::Mo => '.mo',
            self::Php => '.l10n.php',
        };
    }

    case Mo = 'mo';
    case Php = 'php';
}
