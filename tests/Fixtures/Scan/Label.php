<?php

declare(strict_types=1);

namespace Pollora\I18n\Tests\Fixtures\Scan;

use Attribute;

#[Attribute(Attribute::TARGET_ALL | Attribute::IS_REPEATABLE)]
final class Label
{
    public function __construct(public string $value) {}
}
