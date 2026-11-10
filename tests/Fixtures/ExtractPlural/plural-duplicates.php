<?php

declare(strict_types=1);

namespace App\Duplicates;

use App\Attributes\Counts;
use Pollora\I18n\Runtime\Translatable as T;
use Pollora\I18n\Runtime\TranslatablePlural as P;

#[Counts(['a' => new P('One book', '%d books', comment: 'Zebra note'), 'b' => new P('One book', '%d books', comment: 'Alpha note')])]
#[Counts(['c' => new P('One book', '%d books', comment: 'Alpha note')])]
final class Same {}

#[Counts(['a' => new P('One film', '%d films')])]
#[Counts(['b' => new P('One film', '%d movies')])]
#[Counts(['c' => new T('One film')])]
#[Counts(['d' => new P('One film', '%d films')])]
final class Conflicting {}

#[Counts(['a' => new T('Shelf')])]
#[Counts(['b' => new P('Shelf', 'Shelves')])]
final class Singular {}

#[Counts(['a' => new P('Same', 'Plural'), 'b' => new P('Same', 'Another plural', null, 'context'), 'c' => new P('Same', 'Third plural', 'other')])]
final class Apart {}
