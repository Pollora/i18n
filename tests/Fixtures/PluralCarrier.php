<?php

declare(strict_types=1);

namespace Pollora\I18n\Tests\Fixtures;

use Pollora\I18n\Runtime\TranslatablePlural as P;

#[TranslatableLabels(['count' => new P('One book', '%d books')])]
final class PluralCarrier {}
