<?php

declare(strict_types=1);

namespace Pollora\I18n\Runtime;

/**
 * Port to the strings already resolved for a class, built ahead of the request.
 *
 * An adapter reads them from wherever the project keeps its caches (a generated
 * PHP file served by opcache, the cache of a framework). The runtime asks for
 * them by class and never learns where they live.
 */
interface ResolvedStringsStore
{
    /**
     * @param  class-string  $class
     * @return array<string, string> The source string of each translatable field, empty when none is stored
     */
    public function resolvedFor(string $class): array;
}
