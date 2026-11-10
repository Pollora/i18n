<?php

declare(strict_types=1);

namespace App\Columns;

use App\Attributes\AdminCols;
use App\Attributes\Tabs;
use Pollora\I18n\Runtime\Translatable as T;

#[AdminCols(['author' => ['title' => new T('Written by'), 'sortable' => true], 'date' => new T('Published', 'theme', 'column')])]
final class Alias {}

#[Tabs(textDomain: 'tabs', items: ['one' => new T('First tab'), 'two' => new T('Second tab', domain: 'other')])]
#[AdminCols(label: new T(text: 'Named text', context: 'named'))]
final class Named {}

#[AdminCols(['a' => ['b' => ['c' => ['d' => [new T('Deep'), 7 => new T('Seven'), new T('Eight'), 'x' => new T('Joined '.'in two')]]]]], null)]
final class Deep {}

#[AdminCols(new T('Direct'), ['k' => new T('Written by')])]
final class Direct {}
