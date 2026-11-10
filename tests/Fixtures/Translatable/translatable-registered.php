<?php

declare(strict_types=1);

namespace App\Registered;

use Pollora\I18n\Runtime\Translatable as T;
use Pollora\I18n\Tests\Fixtures\Scan\Label;
use Pollora\I18n\Tests\Fixtures\Scan\PostType;
use Pollora\I18n\Tests\Fixtures\Scan\Tags;

#[PostType('Book', textDomain: 'books'), Tags([new T('Fiction'), new T('Essay', 'own')], textDomain: 'books')]
#[Label(new T('Label text'))]
final class Registered {}

#[Tags([new T('Lost')], textDomain: $domain)]
final class Lost {}
