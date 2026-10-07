<?php

/**
 * The ports an instance gets: the simulators of a grid are looked for from the
 * thousand above it, in its hundred, where the users are used to find them; a
 * simulator has its HTTP port first in its block, its console on x4 and its
 * regions on the others.
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

    test('are a block of ten, the next block ten above', function () {
        expect(Ports::nextBlock(Ports::simulatorsFrom(8002), [9000, 9001, 9002, 9004]))->toBe(9010);
    });

    test('are the first free block from there', function () {
        expect(Ports::nextBlock(9100, [9000]))->toBeGreaterThanOrEqual(9100);
        expect(Ports::nextBlock(9100, [9000]) % 10)->toBe(0);
    });
});

describe('Ports of a simulator block', function () {
    test('belong to the block of the HTTP port, on x0 or on x2', function () {
        expect(Ports::simulatorBlock(19000))->toBe(19000);
        expect(Ports::simulatorBlock(19010))->toBe(19010);
        expect(Ports::simulatorBlock(19012))->toBe(19010);
        expect(Ports::simulatorBlock(19003))->toBeNull();
    });

    test('give the regions their ports', function () {
        $taken = [19000];
        $ports = [];
        for ($i = 0; $i < 8; $i++) {
            $ports[] = $taken[] = Ports::nextRegion(19000, $taken);
        }

        expect($ports)->toBe([19001, 19002, 19003, 19005, 19006, 19007, 19008, 19009]);
        expect(Ports::nextRegion(19000, $taken))->toBeNull();
    });

    test('keep x5 to x9 then x3 for a simulator made on x2', function () {
        $taken = [19002];
        $ports = [];
        for ($i = 0; $i < 6; $i++) {
            $ports[] = $taken[] = Ports::nextRegion(19002, $taken);
        }

        expect($ports)->toBe([19005, 19006, 19007, 19008, 19009, 19003]);
        expect(Ports::nextRegion(19002, $taken))->toBeNull();
    });

    test('give nothing for a port that is not in a block', function () {
        expect(Ports::nextRegion(19003))->toBeNull();
    });
});

describe('Ports bound on a machine', function () {
    test('are the ones of the sockets that listen and of UDP, not the ports of the machines it is connected to', function () {
        $bsd = <<<'NET'
        Active Internet connections (including servers)
        Proto Recv-Q Send-Q  Local Address          Foreign Address        (state)
        tcp4       0      0  192.168.8.101.62466    192.168.8.105.9000     ESTABLISHED
        tcp4       0      0  127.0.0.1.8090         *.*                    LISTEN
        tcp6       0      0  *.9000                 *.*                    LISTEN
        udp4       0      0  *.9001                 *.*
        NET;
        $linux = <<<'NET'
        Active Internet connections (servers and established)
        Proto Recv-Q Send-Q Local Address           Foreign Address         State
        tcp        0      0 0.0.0.0:8002            0.0.0.0:*               LISTEN
        tcp        0      0 10.0.0.2:41234          10.0.0.9:9010           ESTABLISHED
        udp        0      0 0.0.0.0:9011            0.0.0.0:*
        unix  2      [ ACC ]     STREAM     LISTENING     12345    /run/x.sock
        NET;

        expect(Ports::parseBound($bsd))
            ->toBe([8090, 9000, 9001])
            ->and(Ports::parseBound($linux))
            ->toBe([8002, 9011])
            ->and(Ports::parseBound(''))
            ->toBe([]);
    });
});
