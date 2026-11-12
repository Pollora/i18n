<?php

declare(strict_types=1);

use Pollora\I18n\Runtime\Translatable;
use Pollora\I18n\Runtime\TranslatableRegistry;
use Pollora\I18n\Runtime\Translator;

/*
 * Whether WordPress's functions exist is a property of the process: the case
 * where they do not runs as a request of its own (tests/Pest.php), the others
 * load the stand-ins here.
 */

it('returns the string unchanged when WordPress is not loaded', function (): void {
    expect(simulateRequest('none')['result'])->toBe('Book');
});

/*
 * Whether __() exists is a property of the process, and this process loads the stand-ins in other tests: the
 * decision "WordPress is absent" is handed to the method that acts on it, as resolve() hands it.
 */
it('gives a Translatable back as its text when told WordPress is absent', function (): void {
    $translateText = new ReflectionMethod(Translator::class, 'translateText');

    expect($translateText->invoke(null, new Translatable('Book', 'own', 'noun'), 'pollora', false))->toBe('Book');
});

describe('with WordPress loaded', function (): void {
    beforeEach(function (): void {
        require_once __DIR__.'/../Fixtures/wordpress-functions.php';

        $GLOBALS['i18n_wordpress'] = ['filters' => [], 'calls' => 0];

        TranslatableRegistry::flush();
        TranslatableRegistry::register(stdClass::class, ['singular']);
    });

    it('translates a declared field through __() with the given domain', function (): void {
        expect(Translator::translate(stdClass::class, 'singular', 'Book', 'pollora'))->toBe('[pollora] Book')
            ->and($GLOBALS['i18n_wordpress']['calls'])->toBe(1);
    });

    it('leaves a field that is not declared translatable alone, without calling __()', function (): void {
        expect(Translator::translate(stdClass::class, 'slug', 'book', 'pollora'))->toBe('book')
            ->and(Translator::translate(ArrayObject::class, 'singular', 'Book', 'pollora'))->toBe('Book')
            ->and($GLOBALS['i18n_wordpress']['calls'])->toBe(0);
    });

    it('does not call __() for an empty string', function (): void {
        expect(Translator::translate(stdClass::class, 'singular', '', 'pollora'))->toBe('')
            ->and($GLOBALS['i18n_wordpress']['calls'])->toBe(0);
    });

    it('calls __() for every text, as many times as it is asked', function (): void {
        Translator::translate(stdClass::class, 'singular', 'Book', 'pollora');
        Translator::translate(stdClass::class, 'singular', 'Book', 'pollora');

        expect($GLOBALS['i18n_wordpress']['calls'])->toBe(2);
    });
});
