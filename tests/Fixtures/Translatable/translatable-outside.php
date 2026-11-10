<?php

declare(strict_types=1);

namespace App\Outside;

use App\Attributes\AdminCols;
use Pollora\I18n\Runtime\Translatable as T;

final class Outside
{
    public const LABELS = ['constant' => 'Not a label'];

    public function labels(): array
    {
        return ['method' => new T('In a method')];
    }
}

#[AdminCols(['inside' => new T('Inside the attribute')])]
final class Mixed
{
    public object $property;

    public function __construct()
    {
        $this->property = new T('In a constructor');
    }
}

$loose = new T('At the top');
