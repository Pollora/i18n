<?php

declare(strict_types=1);

use Pollora\I18n\Runtime\InvalidTranslatableException;
use Pollora\I18n\Runtime\Translatable;
use Pollora\I18n\Runtime\TranslatableTooDeepException;
use Pollora\I18n\Runtime\Translator;
use Pollora\I18n\Tests\Fixtures\TranslatableCarrier;

/**
 * An array nested the given number of levels, with a Translatable at the bottom.
 *
 * @return array<string, mixed>
 */
function translatableNested(int $levels): array
{
    $value = ['text' => new Translatable('Deep')];

    for ($level = 1; $level < $levels; $level++) {
        $value = ['inner' => $value];
    }

    return $value;
}

describe('Translatable', function (): void {
    it('is a Stringable that gives its text as written', function (): void {
        $translatable = new Translatable('Written by', 'my-domain', 'byline');

        expect($translatable)->toBeInstanceOf(Stringable::class)
            ->and((string) $translatable)->toBe('Written by')
            ->and($translatable->domain)->toBe('my-domain')
            ->and($translatable->context)->toBe('byline');
    });

    it('has no domain and no context by default', function (): void {
        $translatable = new Translatable('Written by');

        expect($translatable->domain)->toBeNull()
            ->and($translatable->context)->toBeNull();
    });

    it('refuses an empty text', function (): void {
        new Translatable('');
    })->throws(InvalidTranslatableException::class);

    it('can be written in an attribute argument', function (): void {
        $attribute = (new ReflectionClass(TranslatableCarrier::class))->getAttributes()[0]->newInstance();

        expect($attribute->labels['by'])->toEqual(new Translatable('Written by'));
    });
});

describe('Translator::resolve() without WordPress', function (): void {
    it('replaces a Translatable by its text', function (): void {
        expect(simulateRequest('resolve-none')['result'])->toBe('Written by|Book|Écrit par');
    });
});

describe('Translator::resolve() with WordPress loaded', function (): void {
    beforeEach(function (): void {
        require_once __DIR__.'/../Fixtures/wordpress-functions.php';

        $GLOBALS['i18n_wordpress'] = ['locale' => 'fr_FR', 'loaded' => [], 'calls' => 0];
    });

    it('replaces a Translatable on its own', function (): void {
        expect(Translator::resolve(new Translatable('Book'), 'pollora'))->toBe('[pollora] Book');
    });

    it('replaces a Translatable at several levels and keeps keys and other values', function (): void {
        $object = new stdClass;
        $arguments = [
            'label' => new Translatable('Author'),
            5 => 'plain',
            'columns' => [
                'by' => ['title' => new Translatable('Written by'), 'width' => 10, 'sortable' => true],
                'objects' => [$object, null, 1.5],
            ],
        ];

        $resolved = Translator::resolve($arguments, 'pollora');

        expect($resolved)->toBe([
            'label' => '[pollora] Author',
            5 => 'plain',
            'columns' => [
                'by' => ['title' => '[pollora] Written by', 'width' => 10, 'sortable' => true],
                'objects' => [$object, null, 1.5],
            ],
        ])
            ->and($resolved['columns']['objects'][0])->toBe($object)
            ->and($GLOBALS['i18n_wordpress']['calls'])->toBe(2);
    });

    it('uses the domain of the Translatable, else the default one', function (): void {
        $resolved = Translator::resolve([new Translatable('A', 'own'), new Translatable('B')], 'default-domain');

        expect($resolved)->toBe(['[own] A', '[default-domain] B']);
    });

    it('uses _x() when the Translatable has a context', function (): void {
        $resolved = Translator::resolve(['a' => new Translatable('Post', null, 'verb'), 'b' => new Translatable('Post', 'own', 'noun')], 'pollora');

        expect($resolved)->toBe(['a' => '[pollora|verb] Post', 'b' => '[own|noun] Post']);
    });

    it('does not look into objects other than Translatable', function (): void {
        $holder = new stdClass;
        $holder->text = new Translatable('Hidden');

        $resolved = Translator::resolve(['holder' => $holder], 'pollora');

        expect($resolved['holder'])->toBe($holder)
            ->and($holder->text)->toBeInstanceOf(Translatable::class)
            ->and($GLOBALS['i18n_wordpress']['calls'])->toBe(0);
    });

    it('returns the same array when there is nothing to resolve', function (): void {
        $arguments = ['title' => 'Book', 'nested' => ['a' => 1, 'b' => ['c' => null]], 'list' => []];

        expect(Translator::resolve($arguments, 'pollora'))->toBe($arguments)
            ->and($GLOBALS['i18n_wordpress']['calls'])->toBe(0);
    });

    it('leaves strings, scalars and other objects alone', function (): void {
        $object = new stdClass;

        expect(Translator::resolve('Book', 'pollora'))->toBe('Book')
            ->and(Translator::resolve(12, 'pollora'))->toBe(12)
            ->and(Translator::resolve(null, 'pollora'))->toBeNull()
            ->and(Translator::resolve($object, 'pollora'))->toBe($object)
            ->and($GLOBALS['i18n_wordpress']['calls'])->toBe(0);
    });

    it('does not change the array it was given', function (): void {
        $arguments = ['a' => ['b' => new Translatable('Book')]];

        Translator::resolve($arguments, 'pollora');

        expect($arguments['a']['b'])->toBeInstanceOf(Translatable::class);
    });

    it('skips __() in the source locale when no catalogue is loaded for the domain', function (): void {
        $GLOBALS['i18n_wordpress']['locale'] = 'en_US';

        $resolved = Translator::resolve(['a' => new Translatable('Book'), 'b' => new Translatable('Post', null, 'verb')], 'pollora');

        expect($resolved)->toBe(['a' => 'Book', 'b' => 'Post'])
            ->and($GLOBALS['i18n_wordpress']['calls'])->toBe(0);
    });

    it('still translates in the source locale the domains that have a catalogue', function (): void {
        $GLOBALS['i18n_wordpress'] = ['locale' => 'en_US', 'loaded' => ['own'], 'calls' => 0];

        $resolved = Translator::resolve([new Translatable('A', 'own'), new Translatable('B')], 'pollora');

        expect($resolved)->toBe(['[own] A', 'B']);
    });

    it('accepts arrays nested up to the maximum depth', function (): void {
        $resolved = Translator::resolve(translatableNested(Translator::MAX_DEPTH), 'pollora');

        for ($level = 1; $level < Translator::MAX_DEPTH; $level++) {
            $resolved = $resolved['inner'];
        }

        expect($resolved['text'])->toBe('[pollora] Deep');
    });

    it('refuses arrays nested deeper than the maximum', function (): void {
        Translator::resolve(translatableNested(Translator::MAX_DEPTH + 1), 'pollora');
    })->throws(TranslatableTooDeepException::class);

    it('refuses a circular reference instead of looping', function (): void {
        $loop = ['a' => 1];
        $loop['self'] = &$loop;

        Translator::resolve($loop, 'pollora');
    })->throws(TranslatableTooDeepException::class);
});
