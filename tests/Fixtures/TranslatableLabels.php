<?php

declare(strict_types=1);

namespace Pollora\I18n\Tests\Fixtures;

use Attribute;

/**
 * An attribute taking an array argument, as the framework's attributes do.
 */
#[Attribute(Attribute::TARGET_CLASS)]
final readonly class TranslatableLabels
{
    /**
     * @param  array<string, mixed>  $labels
     */
    public function __construct(public array $labels = []) {}
}
