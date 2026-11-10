<?php

declare(strict_types=1);

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

describe('with WordPress loaded', function (): void {
    beforeEach(function (): void {
        require_once __DIR__.'/../Fixtures/wordpress-functions.php';

        $GLOBALS['i18n_wordpress'] = ['locale' => 'fr_FR', 'loaded' => [], 'calls' => 0];

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

    it('skips __() in the source locale when no catalogue is loaded for the domain', function (): void {
        $GLOBALS['i18n_wordpress']['locale'] = 'en_US';

        expect(Translator::translate(stdClass::class, 'singular', 'Book', 'pollora'))->toBe('Book')
            ->and($GLOBALS['i18n_wordpress']['calls'])->toBe(0);
    });

    it('still translates in the source locale when a catalogue is loaded for the domain', function (): void {
        $GLOBALS['i18n_wordpress'] = ['locale' => 'en_US', 'loaded' => ['pollora'], 'calls' => 0];

        expect(Translator::translate(stdClass::class, 'singular', 'Book', 'pollora'))->toBe('[pollora] Book');
    });
});
