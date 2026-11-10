<?php

declare(strict_types=1);

namespace Pollora\I18n\Tests\Fixtures;

use Pollora\I18n\Runtime\Translatable as T;

#[TranslatableLabels(['by' => new T('Written by')])]
final class TranslatableCarrier {}
