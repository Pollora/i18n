<?php

declare(strict_types=1);

namespace Pollora\I18n\Tests\Fixtures;

use Pollora\I18n\Runtime\Translatable as T;
use Pollora\I18n\Runtime\TranslatablePlural;
use Pollora\I18n\Runtime\TranslatablePlural as P;

#[ExtractPluralAttribute(labels: [
    'author' => new T('Written by', comment: 'Byline of an article'),
    'count' => new P('One book', '%d books'),
    'films' => ['title' => new P(singular: 'One film', plural: '%d films', context: 'cinema', comment: 'Number of films')],
    'deep' => ['a' => ['b' => [new T('Deep text'), new P('One deep', '%d deeps', 'own')]]],
], textDomain: 'library')]
#[ExtractPluralAttribute([
    'full' => new TranslatablePlural('One shelf', '%d shelves', null, 'furniture'),
    'relative' => new T('Relative '.'name', 'named', 'ctx'),
    'default' => new P('One book', '%d books'),
    'article' => new T(text: 'Article', context: 'noun', domain: 'library'),
])]
#[ExtractPluralAttribute(['again' => new P('One book', '%d books')], 'library')]
final class ExtractPluralCarrier {}
