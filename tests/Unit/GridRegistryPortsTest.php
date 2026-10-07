<?php

/**
 * The ports of the regions a grid knows, whatever machine serves them: the UDP port of a region, and the port of its HTTP
 * listener, which is in its URI.
 */

use OpenSim\Installer\Grid\GridRegistry;

describe('The ports of the registered regions', function () {
    test('are the port of each region and the one of its address, once', function () {
        expect(GridRegistry::portsOf(["9001\thttp://10.0.0.5:9000/", "9002\thttp://10.0.0.5:9000/", "9101\thttp://play.example.org:9100"]))
            ->toBe([9001, 9000, 9002, 9101, 9100]);
    });

    test('are only what is a port: a row with nothing, an address without a port', function () {
        expect(GridRegistry::portsOf(["\thttp://host/", "9005\t", "NULL\tnothing", ""]))->toBe([9005]);
    });
});
