<?php

declare(strict_types=1);

namespace App\Lookalike;

use App\Attributes\AdminCols;
use Other\Runtime\Translatable;

#[AdminCols([new Translatable('Imported from elsewhere'), new Runtime\Translatable('Relative to elsewhere'), new Pollora\I18n\Runtime\Translatable('Under the namespace')])]
final class Lookalike {}

final class Translatable2 {}

#[AdminCols([new Translatable2('Short name only'), new class {}])]
final class Other {}
