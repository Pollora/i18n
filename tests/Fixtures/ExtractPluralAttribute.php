<?php

declare(strict_types=1);

namespace Pollora\I18n\Tests\Fixtures;

use Attribute;

/**
 * An attribute with an array of labels and a text domain, as the framework's attributes have.
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::IS_REPEATABLE)]
final readonly class ExtractPluralAttribute
{
    /**
     * @param  array<array-key, mixed>  $labels
     */
    public function __construct(public array $labels = [], public ?string $textDomain = null) {}
}
