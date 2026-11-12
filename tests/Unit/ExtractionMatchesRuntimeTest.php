<?php

declare(strict_types=1);

use Pollora\I18n\Runtime\InvalidTranslatableException;
use Pollora\I18n\Runtime\InvalidTranslatablePluralException;
use Pollora\I18n\Runtime\TranslatableRegistry;
use Pollora\I18n\Runtime\Translator;
use Pollora\I18n\Tests\Fixtures\ExtractPluralAttribute;
use Pollora\I18n\Tests\Fixtures\ExtractPluralCarrier;
use Pollora\I18n\Tests\Fixtures\ExtractPluralInvalidCarrier;
use Pollora\I18n\Tooling\AttributeSource;

/*
 * The strings the extraction writes in the template must be the ones the
 * runtime asks gettext for: the fixture classes are read by the extraction as
 * source and run by reflection, and both sides are compared. The text domain a
 * class gives its attribute is the one the code that registers it gives to
 * Translator::resolve(), as the framework does; 'fallback' otherwise.
 */

/**
 * What the extraction found in the file of a class, as "domain|msgctxt|msgid", sorted.
 *
 * @param  class-string  $class
 * @return list<string>
 */
function extractPluralExtracted(string $class): array
{
    $file = (string) (new ReflectionClass($class))->getFileName();
    $found = [];

    foreach ((new AttributeSource)->extract([$file], 'fallback')->entries as $domain => $entries) {
        foreach ($entries as $entry) {
            $found[] = $domain.'|'.($entry->msgctxt ?? '').'|'.$entry->msgid;
        }
    }

    sort($found);

    return $found;
}

/**
 * What the runtime asks gettext for when the attributes of a class are instantiated, resolved, and their plurals given a count.
 *
 * @param  class-string  $class
 * @return list<string>
 */
function extractPluralRequested(string $class): array
{
    $GLOBALS['i18n_wordpress']['asked'] = [];

    foreach ((new ReflectionClass($class))->getAttributes(ExtractPluralAttribute::class) as $reflection) {
        $attribute = $reflection->newInstance();
        extractPluralDisplay(Translator::resolve($attribute->labels, $attribute->textDomain ?? 'fallback'));
    }

    $requested = array_map(static fn (array $call): string => $call[2].'|'.($call[1] ?? '').'|'.$call[0], $GLOBALS['i18n_wordpress']['asked']);
    $requested = array_values(array_unique($requested));
    sort($requested);

    return $requested;
}

/**
 * What the code that shows a text does: a _n_noop() structure is given a count, a string is already translated.
 *
 * @param  array<array-key, mixed>  $resolved
 */
function extractPluralDisplay(array $resolved): void
{
    if (array_key_exists('singular', $resolved) && array_key_exists('plural', $resolved) && array_key_exists('context', $resolved) && array_key_exists('domain', $resolved)) {
        translate_nooped_plural($resolved, 1);

        return;
    }

    foreach ($resolved as $value) {
        if (is_array($value)) {
            extractPluralDisplay($value);
        }
    }
}

beforeEach(function (): void {
    require_once __DIR__.'/../Fixtures/wordpress-functions.php';
    require_once __DIR__.'/../Fixtures/wordpress-recording-functions.php';

    TranslatableRegistry::flush();
    $GLOBALS['i18n_wordpress'] = ['filters' => [], 'calls' => 0, 'asked' => []];
});

afterEach(function (): void {
    TranslatableRegistry::flush();
    $GLOBALS['i18n_wordpress'] = ['filters' => [], 'calls' => 0, 'asked' => []];
});

it('extracts exactly the strings the runtime asks gettext for, texts and plurals, nested, with their contexts and domains', function (): void {
    $extracted = extractPluralExtracted(ExtractPluralCarrier::class);

    expect($extracted)->toBe(extractPluralRequested(ExtractPluralCarrier::class))
        ->and($extracted)->toBe([
            'fallback|furniture|One shelf',
            'fallback||One book',
            'library|cinema|One film',
            'library|noun|Article',
            'library||Deep text',
            'library||One book',
            'library||Written by',
            'named|ctx|Relative name',
            'own||One deep',
        ]);
});

it('asks gettext for the singular form of a plural, with its context and its own domain', function (): void {
    extractPluralRequested(ExtractPluralCarrier::class);

    expect($GLOBALS['i18n_wordpress']['asked'])->toContain(['One shelf', 'furniture', 'fallback'], ['One film', 'cinema', 'library'], ['One deep', null, 'own'], ['Written by', null, 'library']);
});

it('refuses at run time what the extraction reports and leaves out: an empty text, singular, plural or context', function (): void {
    $file = (string) (new ReflectionClass(ExtractPluralInvalidCarrier::class))->getFileName();
    $extraction = (new AttributeSource)->extract([$file], 'fallback');
    $outcomes = [];

    foreach ((new ReflectionClass(ExtractPluralInvalidCarrier::class))->getAttributes() as $reflection) {
        try {
            $reflection->newInstance();
            $outcomes[] = 'accepted';
        } catch (InvalidTranslatableException|InvalidTranslatablePluralException) {
            $outcomes[] = 'refused';
        }
    }

    expect($outcomes)->toBe(['refused', 'refused', 'refused', 'refused', 'refused', 'accepted'])
        ->and($extraction->diagnostics)->toHaveCount(5)
        ->and($extraction->entriesFor('fallback'))->toHaveCount(1)
        ->and($extraction->entriesFor('fallback')[0]->msgid.'|'.$extraction->entriesFor('fallback')[0]->msgctxt)->toBe('Fine|kept');
});
