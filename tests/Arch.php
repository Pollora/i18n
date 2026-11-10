<?php

declare(strict_types=1);

use Pollora\Harness\Pest\Architecture;

/*
 * Architecture rules of the Pollora harness (ARCH-*, CONV-*).
 *
 * The rules follow the profile in .harness.json and the layers found under
 * the PSR-4 roots: nothing to maintain here. List them with
 * `vendor/bin/pollora-qa rules`.
 */

Architecture::for(dirname(__DIR__))->register();

/*
 * The two halves of the package: Runtime runs on every request and stays tiny,
 * Tooling runs in the console only. Runtime must never pull Tooling, a heavy
 * library or a framework in.
 */
arch('the runtime depends on nothing in Tooling')
    ->expect('Pollora\I18n\Runtime')
    ->not->toUse('Pollora\I18n\Tooling');

arch('the runtime depends on no framework and no parser')
    ->expect('Pollora\I18n\Runtime')
    ->not->toUse(['Illuminate', 'Laravel', 'PhpParser', 'Symfony']);

arch('the tooling depends on no framework')
    ->expect('Pollora\I18n\Tooling')
    ->not->toUse(['Illuminate', 'Laravel']);

arch('the runtime classes are final')
    ->expect('Pollora\I18n\Runtime')
    ->classes()
    ->toBeFinal();

arch('the tooling value objects are final and readonly')
    ->expect('Pollora\I18n\Tooling\CatalogEntry')
    ->toBeFinal()
    ->toBeReadonly();

arch('the exceptions are final and extend RuntimeException')
    ->expect('Pollora\I18n\Tooling\InvalidCatalogEntryException')
    ->toBeFinal()
    ->toExtend(RuntimeException::class);
