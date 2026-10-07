<?php

/**
 * The name proposed for a simulator: the one of its region, a random one when that does not fit.
 */

use OpenSim\Installer\Grid\SimName;

describe('The name proposed for a simulator', function () {
    $fine = static fn(string $v): ?string => null;
    $taken = static fn(string ...$names): Closure => static fn(string $v): ?string => in_array($v, $names, true) ? 'taken' : null;

    test('is the name of its region', function () use ($fine) {
        expect(SimName::suggest('Back Office', null, $fine))->toBe('Back Office');
        // The first simulator of a grid is named after its landing region as well
        expect(SimName::suggest('Welcome Center', 'Welcome', $fine))->toBe('Welcome Center');
    });

    test('has the accents of the name of its region transliterated', function () use ($fine) {
        expect(SimName::suggest('Noël', null, $fine))->toBe('Noel');
    });

    test('is a random one when the name of its region is taken or does not fit', function () use ($taken) {
        $name = SimName::suggest('Back Office', null, $taken('Back Office'));

        expect($name)->not->toBe('')->and($name)->not->toBe('Back Office');
        expect(SimName::suggest('Back Office', null, static fn(string $v): ?string => $v === 'Back Office' ? 'invalid' : null))
            ->not->toBe('Back Office');
    });

    test('is the name the grid gives when there is no region to be named after, else a random one', function () use ($fine) {
        expect(SimName::suggest(null, 'Welcome', $fine))->toBe('Welcome');
        expect(SimName::suggest(null, null, $fine))->not->toBe('');
    });
});
