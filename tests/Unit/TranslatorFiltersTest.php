<?php

declare(strict_types=1);

/*
 * With no catalogue, __() and _x() still run the gettext filters of WordPress
 * (Polylang, WPML...), so Translator always calls them when WordPress is there,
 * for the first text of a domain and for the following ones. Each case is one
 * simulated request in a process of its own (tests/Fixtures/filters.php).
 */

it('applies a filter of each of the four gettext hooks to the first and the following texts', function (string $hook, ?string $context, string $expected): void {
    $request = simulateFilteredRequest([[['own', $context], ['own', $context]], [['own', $context]]], [$hook => ' (filtered)']);

    expect($request['results'])->toBe([$expected, $expected, $expected])
        ->and($request['asked'])->toHaveCount(3);
})->with([
    'gettext' => ['gettext', null, '[own] Book (filtered)'],
    'gettext_with_context' => ['gettext_with_context', 'noun', '[own|noun] Book (filtered)'],
    'gettext_{domain}' => ['gettext_own', null, '[own] Book (filtered)'],
    'gettext_with_context_{domain}' => ['gettext_with_context_own', 'noun', '[own|noun] Book (filtered)'],
]);

it('is not changed by a filter of another domain or of the other kind of text', function (): void {
    $request = simulateFilteredRequest([[['own', null], ['own', 'noun']]], ['gettext_other' => ' (filtered)', 'gettext_with_context' => ' (context)']);

    expect($request['results'])->toBe(['[own] Book', '[own|noun] Book (context)']);
});

it('gives the texts back as written when no filter changes them', function (): void {
    $request = simulateFilteredRequest([[['own', null], ['own', null]], [['own', 'noun']]]);

    expect($request['results'])->toBe(['Book', 'Book', 'Book'])
        ->and($request['asked'])->toHaveCount(3);
});

it('gives the texts back as written when WordPress is not loaded', function (): void {
    $request = simulateFilteredRequest([[['own', null], ['own', 'noun']]], wordpress: false);

    expect($request['results'])->toBe(['Book', 'Book'])
        ->and($request['asked'])->toBe([]);
});
