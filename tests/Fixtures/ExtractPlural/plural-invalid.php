<?php

declare(strict_types=1);

namespace App\Invalid;

use App\Attributes\Counts;
use Pollora\I18n\Runtime\Translatable as T;
use Pollora\I18n\Runtime\TranslatablePlural as P;

#[Counts(['singular' => new P($label, 'Many')])]
#[Counts(['plural' => new P('One', strtoupper('x'))])]
#[Counts(['empty singular' => new P('', 'Many')])]
#[Counts(['empty plural' => new P('One', '')])]
#[Counts(['missing plural' => new P('One')])]
#[Counts(['none' => new P])]
#[Counts(['domain' => new P('One', 'Many', $domain)])]
#[Counts(['context' => new P('One', 'Many', null, $context)])]
#[Counts(['empty context' => new P('One', 'Many', context: '')])]
#[Counts(['empty text context' => new T('Text', context: '')])]
#[Counts(['comment' => new P('Kept one', 'Kept many', comment: $note)])]
#[Counts(['text comment' => new T('Kept text', comment: self::NOTE)])]
#[Counts(['fine' => new P('Fine', 'Fines', null, null, null)])]
final class Invalid {}

#[Counts(['inherited' => new P('Lost domain', 'Lost domains'), 'explicit' => new P('Kept domain', 'Kept domains', 'kept')], textDomain: $domain)]
final class Lost {}
