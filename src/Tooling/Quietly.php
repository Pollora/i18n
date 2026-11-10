<?php

declare(strict_types=1);

namespace Pollora\I18n\Tooling;

/**
 * Runs a filesystem call whose failure the caller handles itself, so PHP raises no warning for it.
 *
 * @internal
 */
final class Quietly
{
    /**
     * @template T
     *
     * @param  callable(): T  $operation
     * @return T
     */
    public static function call(callable $operation): mixed
    {
        set_error_handler(static fn (): bool => true);

        try {
            return $operation();
        } finally {
            restore_error_handler();
        }
    }
}
