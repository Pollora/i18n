<?php

declare(strict_types=1);

namespace Pollora\I18n;

use App\Attributes\Counts;
use Pollora\I18n\Runtime\TranslatablePlural;
use Pollora\I18n\Runtime\TranslatablePlural as P;

#[Counts(['books' => new P('One book', '%d books'), 'films' => ['label' => new P(singular: 'One film', plural: '%d films', context: 'cinema', comment: 'Number of films')]])]
final class Positional {}

#[Counts(textDomain: 'shop', items: ['a' => new P('One item', '%d items'), 'b' => new P('One own', '%d owns', 'own', 'ctx', 'Has its own domain'), 'c' => new P('One null', '%d nulls', null, null, null)])]
final class Domains {}

#[Counts([new TranslatablePlural('Imported by its name', 'Imported by their names'), new P('Imported', 'Importeds'), new \POLLORA\I18N\RUNTIME\TRANSLATABLEPLURAL('Other case', 'Other cases')])]
final class Spellings {}

#[Counts(['joined' => new P('One '.'joined', '%d '.'joined', comment: 'Joined '.'comment'), 7 => [new P('Seven', 'Sevens')], new P('Eight', 'Eights'), 'blank' => new P('Blank', 'Blanks', comment: '')])]
final class Shapes {}
