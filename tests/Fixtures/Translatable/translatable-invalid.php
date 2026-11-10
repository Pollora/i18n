<?php

declare(strict_types=1);

namespace App\Invalid;

use App\Attributes\AdminCols;
use Pollora\I18n\Runtime\Translatable as T;

#[AdminCols(['variable' => new T($label)])]
#[AdminCols(['call' => new T(strtoupper('x'))])]
#[AdminCols(['constant' => new T(self::LABEL)])]
#[AdminCols(['empty' => new T('')])]
#[AdminCols(['none' => new T])]
#[AdminCols(['spread' => new T(...$arguments)])]
#[AdminCols(['domain' => new T('Bad domain', $domain)])]
#[AdminCols(['context' => new T('Bad context', null, $context)])]
#[AdminCols(['fine' => new T('Fine', null, null)])]
final class Invalid {}

#[AdminCols(['inherited' => new T('Lost domain'), 'explicit' => new T('Kept domain', 'kept')], textDomain: $domain)]
final class Lost {}
