<?php

/**
 * The ports an instance gets: the simulators of a grid are looked for from the
 * thousand above it, in its hundred, where the users are used to find them.
 */

use OpenSim\Installer\Ports;

describe('Ports of the simulators', function () {
    test('start from 9000 for the usual grid', function () {
        expect(Ports::simulatorsFrom(8002))->toBe(9000);
        expect(Ports::simulatorsFrom(8012))->toBe(9000);
    });

    test('follow the hundred of their grid when it is not the first', function () {
        expect(Ports::simulatorsFrom(8102))->toBe(9100);
        expect(Ports::simulatorsFrom(8202))->toBe(9200);
    });

    test('are a block of ten whose public port ends with 2, the next block 10 above', function () {
        $block = Ports::nextBlock(Ports::simulatorsFrom(8002), [9000, 9001, 9002, 9003, 9004, 9005]);

        expect($block)->toBe(9010);
        expect($block + 2)->toBe(9012);
    });

    test('are the first free block from there, and the first one when nothing is used', function () {
        expect(Ports::nextBlock(9000, [9000]))->toBeGreaterThanOrEqual(9010);
        expect(Ports::nextBlock(9100, [9000]) % 10)->toBe(0);
        expect(Ports::nextBlock(9100, [9000]))->toBeGreaterThanOrEqual(9100);
    });
});
