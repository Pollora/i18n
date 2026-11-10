<?php

declare(strict_types=1);

namespace Pollora\I18n\Tests\Fixtures;

use Pollora\I18n\Runtime\Translatable as T;
use Pollora\I18n\Runtime\TranslatablePlural as P;

#[ExtractPluralAttribute(['text' => new T('')])]
#[ExtractPluralAttribute(['context' => new T('Text', context: '')])]
#[ExtractPluralAttribute(['singular' => new P('', 'Many')])]
#[ExtractPluralAttribute(['plural' => new P('One', '')])]
#[ExtractPluralAttribute(['context' => new P('One', 'Many', context: '')])]
#[ExtractPluralAttribute(['fine' => new P('Fine', 'Fines', context: 'kept')])]
final class ExtractPluralInvalidCarrier {}
