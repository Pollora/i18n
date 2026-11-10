<?php

declare(strict_types=1);

namespace Pollora\I18n;

use App\Attributes\AdminCols;
use Pollora\I18n\Runtime\Translatable;

#[AdminCols([new Translatable('Fully qualified'), new Translatable('Relative to the namespace'), new Translatable('Through a namespace alias')])]
final class FullName {}

#[AdminCols([new Translatable('Imported'), new Translatable('Other case')])]
final class Imported {}
