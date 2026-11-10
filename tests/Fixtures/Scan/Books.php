<?php

declare(strict_types=1);

namespace App\Books;

use Pollora\I18n\Tests\Fixtures\Scan\Label;
use Pollora\I18n\Tests\Fixtures\Scan\Plain;
use Pollora\I18n\Tests\Fixtures\Scan\PostType as Type;
use Pollora\I18n\Tests\Fixtures\Scan\Tags;

#[Type('Book', plural: 'Books')]
#[Plain('Never extracted')]
final class Book {}

#[Type(singular: 'Film', plural: 'Films', textDomain: 'theme')]
final class Film {}

#[Label('Hello'), Label('World')]
final class Greeting
{
    #[Label('Hello')]
    public string $property = '';

    public function make(): object
    {
        return new #[Label('Inside')] class {};
    }
}

#[Tags(['One', 'Two'])]
#[Type('Joined '.'in two', plural: null)]
#[Type('')]
final class Misc {}
