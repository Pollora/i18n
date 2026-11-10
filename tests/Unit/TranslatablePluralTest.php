<?php

declare(strict_types=1);

use Pollora\I18n\Runtime\InvalidTranslatableException;
use Pollora\I18n\Runtime\InvalidTranslatablePluralException;
use Pollora\I18n\Runtime\Translatable;
use Pollora\I18n\Runtime\TranslatablePlural;
use Pollora\I18n\Runtime\TranslatableTooDeepException;
use Pollora\I18n\Runtime\Translator;
use Pollora\I18n\Tests\Fixtures\PluralCarrier;

/**
 * Where WordPress is installed: POLLORA_WORDPRESS_PATH, or the public/cms folder of a Pollora project in DDEV.
 */
function pluralWordPressPath(): ?string
{
    foreach ([getenv('POLLORA_WORDPRESS_PATH'), '/var/www/html/public/cms'] as $path) {
        if (is_string($path) && is_file($path.'/wp-includes/l10n.php')) {
            return $path;
        }
    }

    return null;
}

/**
 * What _n_noop() returns, key for key and in this order.
 *
 * @return array<int|string, string|null>
 */
function pluralNoop(string $singular, string $plural, ?string $domain): array
{
    return [0 => $singular, 1 => $plural, 'singular' => $singular, 'plural' => $plural, 'context' => null, 'domain' => $domain];
}

/**
 * What _nx_noop() returns, key for key and in this order.
 *
 * @return array<int|string, string|null>
 */
function pluralNoopWithContext(string $singular, string $plural, string $context, ?string $domain): array
{
    return [0 => $singular, 1 => $plural, 2 => $context, 'singular' => $singular, 'plural' => $plural, 'context' => $context, 'domain' => $domain];
}

/**
 * An array nested the given number of levels, with a TranslatablePlural at the bottom.
 *
 * @return array<string, mixed>
 */
function pluralNested(int $levels): array
{
    $value = ['count' => new TranslatablePlural('One', 'Many')];

    for ($level = 1; $level < $levels; $level++) {
        $value = ['inner' => $value];
    }

    return $value;
}

describe('TranslatablePlural', function (): void {
    it('keeps the two forms, the domain, the context and the comment', function (): void {
        $plural = new TranslatablePlural('One book', '%d books', 'my-domain', 'library', 'Number of books');

        expect($plural->singular)->toBe('One book')
            ->and($plural->plural)->toBe('%d books')
            ->and($plural->domain)->toBe('my-domain')
            ->and($plural->context)->toBe('library')
            ->and($plural->comment)->toBe('Number of books');
    });

    it('has no domain, context or comment by default', function (): void {
        $plural = new TranslatablePlural('One book', '%d books');

        expect($plural->domain)->toBeNull()
            ->and($plural->context)->toBeNull()
            ->and($plural->comment)->toBeNull();
    });

    it('refuses an empty singular form', function (): void {
        new TranslatablePlural('', '%d books');
    })->throws(InvalidTranslatablePluralException::class, 'singular');

    it('refuses an empty plural form', function (): void {
        new TranslatablePlural('One book', '');
    })->throws(InvalidTranslatablePluralException::class, 'plural');

    it('refuses an empty context, and takes null for none', function (): void {
        expect(fn () => new TranslatablePlural('One book', '%d books', null, ''))->toThrow(InvalidTranslatablePluralException::class, 'context')
            ->and((new TranslatablePlural('One book', '%d books', null, null))->context)->toBeNull();
    });

    it('can be written in an attribute argument', function (): void {
        $attribute = (new ReflectionClass(PluralCarrier::class))->getAttributes()[0]->newInstance();

        expect($attribute->labels['count'])->toEqual(new TranslatablePlural('One book', '%d books'));
    });

    it('refuses an empty context on a Translatable, and takes null for none', function (): void {
        expect(fn () => new Translatable('Written by', null, ''))->toThrow(InvalidTranslatableException::class, 'context')
            ->and((new Translatable('Written by', null, null))->context)->toBeNull();
    });

    it('gives its singular form as written when cast to a string', function (): void {
        expect((string) new TranslatablePlural('One book', '%d books'))->toBe('One book')
            ->and(new TranslatablePlural('One book', '%d books'))->toBeInstanceOf(Stringable::class);
    });

    it('leaves other Stringable objects alone when resolving', function (): void {
        $other = new ArrayObject;

        expect(Translator::resolve(['x' => new class implements Stringable
        {
            public function __toString(): string
            {
                return 'x';
            }
        }, 'y' => $other], 'pollora')['y'])->toBe($other);
    });

    it('adds a translator comment to Translatable too, without changing its text', function (): void {
        $translatable = new Translatable('Written by', 'my-domain', 'byline', 'Shown under the title');

        expect($translatable->comment)->toBe('Shown under the title')
            ->and((string) $translatable)->toBe('Written by')
            ->and((new Translatable('Written by'))->comment)->toBeNull();
    });
});

describe('Translator::resolve() of a TranslatablePlural without WordPress', function (): void {
    it('builds the structure of _n_noop() and _nx_noop() by hand', function (): void {
        $result = simulateRequest('resolve-plural-none')['result'];

        expect(json_decode($result, true, flags: JSON_THROW_ON_ERROR))->toBe([
            'plain' => pluralNoop('One book', '%d books', 'pollora'),
            'context' => pluralNoopWithContext('One film', '%d films', 'cinema', 'own'),
        ]);
    });
});

describe('Translator::resolve() of a TranslatablePlural with WordPress loaded', function (): void {
    beforeEach(function (): void {
        require_once __DIR__.'/../Fixtures/wordpress-functions.php';

        $GLOBALS['i18n_wordpress'] = ['locale' => 'fr_FR', 'loaded' => [], 'calls' => 0];
    });

    it('gives the structure of _n_noop() on its own, with the default domain', function (): void {
        expect(Translator::resolve(new TranslatablePlural('One book', '%d books'), 'pollora'))
            ->toBe(pluralNoop('One book', '%d books', 'pollora'));
    });

    it('keeps the domain of the plural over the default one', function (): void {
        expect(Translator::resolve(new TranslatablePlural('One book', '%d books', 'own'), 'pollora'))
            ->toBe(pluralNoop('One book', '%d books', 'own'));
    });

    it('gives the structure of _nx_noop() with a context', function (): void {
        $resolved = Translator::resolve(['films' => new TranslatablePlural('One film', '%d films', null, 'cinema')], 'pollora');

        expect($resolved)->toBe(['films' => pluralNoopWithContext('One film', '%d films', 'cinema', 'pollora')]);
    });

    it('never gives a string, even in the source locale with no catalogue', function (): void {
        $GLOBALS['i18n_wordpress']['locale'] = 'en_US';

        expect(Translator::resolve(new TranslatablePlural('One book', '%d books'), 'pollora'))
            ->toBe(pluralNoop('One book', '%d books', 'pollora'))
            ->and($GLOBALS['i18n_wordpress']['calls'])->toBe(0);
    });

    it('ignores the comment', function (): void {
        $with = Translator::resolve(new TranslatablePlural('One book', '%d books', null, 'library', 'Number of books'), 'pollora');
        $without = Translator::resolve(new TranslatablePlural('One book', '%d books', null, 'library'), 'pollora');

        expect($with)->toBe($without)
            ->and(Translator::resolve(new Translatable('Book', null, null, 'A comment'), 'pollora'))->toBe('[pollora] Book');
    });

    it('resolves plurals and texts mixed at several levels, keeping keys and other values', function (): void {
        $object = new stdClass;
        $arguments = [
            'label' => new Translatable('Author'),
            5 => 'plain',
            'columns' => [
                'books' => ['title' => new TranslatablePlural('One book', '%d books'), 'width' => 10, 'by' => new Translatable('Written by')],
                'objects' => [$object, null, 1.5, new TranslatablePlural('One film', '%d films', 'own', 'cinema')],
            ],
        ];

        $resolved = Translator::resolve($arguments, 'pollora');

        expect($resolved)->toBe([
            'label' => '[pollora] Author',
            5 => 'plain',
            'columns' => [
                'books' => ['title' => pluralNoop('One book', '%d books', 'pollora'), 'width' => 10, 'by' => '[pollora] Written by'],
                'objects' => [$object, null, 1.5, pluralNoopWithContext('One film', '%d films', 'cinema', 'own')],
            ],
        ])
            ->and($GLOBALS['i18n_wordpress']['calls'])->toBe(2);
    });

    it('does not change the array it was given', function (): void {
        $arguments = ['a' => ['b' => new TranslatablePlural('One', 'Many')]];

        Translator::resolve($arguments, 'pollora');

        expect($arguments['a']['b'])->toBeInstanceOf(TranslatablePlural::class);
    });

    it('accepts arrays nested up to the maximum depth', function (): void {
        $resolved = Translator::resolve(pluralNested(Translator::MAX_DEPTH), 'pollora');

        for ($level = 1; $level < Translator::MAX_DEPTH; $level++) {
            $resolved = $resolved['inner'];
        }

        expect($resolved['count'])->toBe(pluralNoop('One', 'Many', 'pollora'));
    });

    it('refuses arrays nested deeper than the maximum', function (): void {
        Translator::resolve(pluralNested(Translator::MAX_DEPTH + 1), 'pollora');
    })->throws(TranslatableTooDeepException::class);
});

describe('Translator::resolve() of a TranslatablePlural against the functions of WordPress', function (): void {
    it('gives what _n_noop() and _nx_noop() give, and translate_nooped_plural() accepts it', function (): void {
        $path = pluralWordPressPath();

        if ($path === null) {
            $this->markTestSkipped('WordPress is not installed here (set POLLORA_WORDPRESS_PATH).');
        }

        $result = simulateRequest('resolve-plural-wordpress', $path)['result'];

        expect(json_decode($result, true, flags: JSON_THROW_ON_ERROR))->toBe([
            'identical' => true,
            'plain' => ['One book', '%d books', 'One book', '%d books'],
            'context' => ['One film', '%d films', 'One film', '%d films'],
            'order' => [0, 1, 'singular', 'plural', 'context', 'domain'],
            'orderWithContext' => [0, 1, 2, 'singular', 'plural', 'context', 'domain'],
        ]);
    });
});

/*
 * The only test that defines _n_noop() and _nx_noop() in this process: once
 * defined they stay, so it runs the case where they are absent and the case
 * where they exist one after the other, and no other test loads them. Whatever
 * the order of the tests, the hand-built branch and the WordPress branch are
 * both run here, and the other tests see the same structure either way.
 */
it('builds the structure by hand without _n_noop() and _nx_noop(), and calls them once WordPress provides them', function (): void {
    require_once __DIR__.'/../Fixtures/wordpress-functions.php';

    $GLOBALS['i18n_wordpress'] = ['locale' => 'fr_FR', 'loaded' => [], 'calls' => 0, 'noop_calls' => 0];
    $arguments = [
        'plain' => new TranslatablePlural('One book', '%d books'),
        'context' => new TranslatablePlural('One film', '%d films', 'own', 'cinema'),
    ];
    $expected = [
        'plain' => pluralNoop('One book', '%d books', 'pollora'),
        'context' => pluralNoopWithContext('One film', '%d films', 'cinema', 'own'),
    ];

    expect(function_exists('_n_noop') || function_exists('_nx_noop'))->toBeFalse()
        ->and(Translator::resolve($arguments, 'pollora'))->toBe($expected);

    require_once __DIR__.'/../Fixtures/wordpress-noop-functions.php';

    expect(Translator::resolve($arguments, 'pollora'))->toBe($expected)
        ->and($GLOBALS['i18n_wordpress']['noop_calls'])->toBe(2);
});
