<?php

declare(strict_types=1);

namespace App\Books;

use Pollora\I18n\Tests\Fixtures\Scan\Label;
use Pollora\I18n\Tests\Fixtures\Scan\PostType;
use Pollora\I18n\Tests\Fixtures\Scan\Tags;

#[PostType(self::NAME, plural: 'Fine')]
#[Label(SOME_CONSTANT)]
#[Tags(['Kept', Other::TAG])]
#[PostType('Wrong domain', textDomain: self::DOMAIN)]
final class Skipped
{
    public const NAME = 'x';
}
