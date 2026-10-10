<?php

/**
 * The port mapping of the instances: ports mapped before an instance starts and closed after it stops, as the grid asks in
 * its config, with every port `opensim ports` lists.
 */

use OpenSimKit\PortMapping\ModuleInterface;
use OpenSimKit\PortMapping\PortMappingService;
use OpenSimKit\PortMapping\UpnpcModule;

/** A module that records what it is asked, and can refuse a port. */
final class FakeModule implements ModuleInterface
{
    /** @var list<string> */
    public static array $calls = [];
    public static int $refuse = 0;
    public static string $address = '203.0.113.7';

    public function prepare(): bool
    {
        self::$calls[] = 'prepare';

        return true;
    }

    public function open(int $port, string $proto): bool
    {
        self::$calls[] = "open $port/$proto";

        return $port !== self::$refuse;
    }

    public function close(int $port, string $proto): bool
    {
        self::$calls[] = "close $port/$proto";

        return true;
    }

    public function address(): string
    {
        return self::$address;
    }
}

/**
 * A grid with its Robust and a simulator, and the service to map them.
 *
 * @return array{service:PortMappingService,grid:string,robust:string,sim:string,regions:string,log:ArrayObject}
 */
function portmapping_setup(string $config): array
{
    $root = test_tmp() . '/portmapping-' . bin2hex(random_bytes(4));
    $grid = "$root/grids/demo";
    mkdir("$grid/sims/demo_sim/regions", 0o755, true);
    file_put_contents("$grid/demo.conf", "[Grid]\n$config");
    file_put_contents("$grid/Robust.HG.ini", "[Const]\n");
    file_put_contents("$grid/sims/demo_sim.ini", "[Startup]\nregionload_regionsdir = \"$grid/sims/demo_sim/regions\"\n");
    file_put_contents("$grid/sims/demo_sim/regions/R.ini", "[R]\nExternalHostName = SYSTEMIP\n");

    FakeModule::$calls = [];
    FakeModule::$refuse = 0;
    FakeModule::$address = '203.0.113.7';
    $log = new ArrayObject();
    // What opensim ports lists: public, private and console ones, and one not enabled: all of them are mapped
    $ports = static fn(string $ini): array => str_contains($ini, 'Robust')
        ? [['port' => 8002, 'proto' => 'tcp'], ['port' => 8003, 'proto' => 'tcp']]
        : [
            ['port' => 9020, 'proto' => 'tcp'],
            ['port' => 9021, 'proto' => 'udp'],
            ['port' => 9024, 'proto' => 'tcp'],
            ['port' => 9025, 'proto' => 'tcp'],
        ];
    $service = new PortMappingService(
        "$root/var/portmap",
        static fn(string $message, bool $problem) => $log->append(($problem ? '! ' : '') . $message),
        $ports,
        ['fake' => static fn(): ModuleInterface => new FakeModule()],
    );

    return [
        'service' => $service,
        'grid' => $grid,
        'robust' => "$grid/Robust.HG.ini",
        'sim' => "$grid/sims/demo_sim.ini",
        'regions' => "$grid/sims/demo_sim/regions/R.ini",
        'log' => $log,
    ];
}

describe('Port mapping, enabled', function () {
    test('maps every port opensim ports lists, then gives the regions the public address', function () {
        $t = portmapping_setup("PortMappingModule = fake\nPortMappingEnabled = true\n");

        $t['service']->start('demo', $t['robust']);
        $t['service']->start('demo_sim', $t['sim']);

        expect(FakeModule::$calls)
            ->toContain('open 8002/tcp', 'open 8003/tcp', 'open 9020/tcp', 'open 9021/udp', 'open 9024/tcp', 'open 9025/tcp')
            ->and(file_get_contents($t['regions']))
            ->toContain('ExternalHostName = 203.0.113.7')
            ->and((array) $t['log'])
            ->toContain('demo_sim: mapping ports 9020/tcp, 9021/udp, 9024/tcp, 9025/tcp (fake), this can take a moment');

        return $t;
    });

    test('calls the module for nothing on a restart', function (array $t) {
        FakeModule::$calls = [];

        $t['service']->start('demo_sim', $t['sim']);

        expect(FakeModule::$calls)->toBe([]);
    })->depends('maps every port opensim ports lists, then gives the regions the public address');

    test('closes the ports when it is stopped, those it has and those it had', function () {
        $t = portmapping_setup("PortMappingModule = fake\nPortMappingEnabled = true\n");
        $t['service']->start('demo', $t['robust']);
        $t['service']->start('demo_sim', $t['sim']);
        file_put_contents(dirname($t['grid'], 2) . '/var/portmap/demo_sim', "8888 tcp\n", FILE_APPEND);
        FakeModule::$calls = [];

        $t['service']->stop('demo_sim', $t['sim']);

        expect(FakeModule::$calls)
            ->toContain('close 8888/tcp', 'close 9020/tcp', 'close 9021/udp', 'close 9024/tcp', 'close 9025/tcp')
            ->and(file_exists(dirname($t['grid'], 2) . '/var/portmap/demo_sim'))
            ->toBeFalse()
            ->and(array_values(array_filter((array) $t['log'], static fn(string $l): bool => str_contains($l, 'closing'))))
            ->toHaveCount(1);
    });

    test('does not map a simulator before its grid is', function () {
        $t = portmapping_setup("PortMappingModule = fake\nPortMappingEnabled = true\n");

        $t['service']->start('demo_sim', $t['sim']);

        expect(FakeModule::$calls)
            ->toBe([])
            ->and(file_get_contents($t['regions']))
            ->toContain('ExternalHostName = SYSTEMIP');
    });

    test('maps a simulator of a grid on another machine without its grid', function () {
        $t = portmapping_setup("Remote = true\nPortMappingModule = fake\nPortMappingEnabled = true\n");

        $t['service']->start('demo_sim', $t['sim']);

        expect(FakeModule::$calls)->toContain('open 9020/tcp');
    });

    test('starts without mapping, and says so, when a port is refused', function () {
        $t = portmapping_setup("PortMappingModule = fake\nPortMappingEnabled = true\n");
        FakeModule::$refuse = 9021;
        $t['service']->start('demo', $t['robust']);

        $t['service']->start('demo_sim', $t['sim']);

        expect((array) $t['log'])
            ->toContain('! demo_sim: port mapping failed, starting without port mapping')
            ->and(FakeModule::$calls)
            ->toContain('close 9020/tcp', 'close 9021/udp')
            ->and(file_get_contents($t['regions']))
            ->toContain('ExternalHostName = SYSTEMIP');
    });

    test('starts without mapping when the module is unknown', function () {
        $t = portmapping_setup("PortMappingModule = nothing\nPortMappingEnabled = true\n");

        $t['service']->start('demo', $t['robust']);

        expect((array) $t['log'])->toContain("! demo: unknown port mapping module 'nothing'");
    });
});

describe('Port mapping, not enabled', function () {
    test('closes what was open, even without a state file, and says which ports', function () {
        $t = portmapping_setup("PortMappingModule = fake\nPortMappingEnabled = false\n");

        $t['service']->start('demo_sim', $t['sim']);
        $t['service']->stop('demo_sim', $t['sim']);

        expect(FakeModule::$calls)
            ->toContain('close 9020/tcp', 'close 9021/udp', 'close 9024/tcp', 'close 9025/tcp')
            ->and((array) $t['log'])
            ->toContain('demo_sim: closing ports 9020/tcp, 9021/udp, 9024/tcp, 9025/tcp (fake)');
    });

    test('closes once on a restart, not once for the stop and once for the start', function () {
        $t = portmapping_setup("PortMappingModule = fake\nPortMappingEnabled = true\n");
        $t['service']->start('demo', $t['robust']);
        $t['service']->start('demo_sim', $t['sim']);
        file_put_contents($t['grid'] . '/demo.conf', "[Grid]\nPortMappingModule = fake\nPortMappingEnabled = false\n");
        $t['log']->exchangeArray([]);

        $t['service']->stop('demo_sim', $t['sim']);
        $t['service']->start('demo_sim', $t['sim']);

        expect(array_values(array_filter((array) $t['log'], static fn(string $l): bool => str_contains($l, 'closing'))))
            ->toHaveCount(1);
    });

    test('gives the regions SYSTEMIP back on a restart, the state being gone', function () {
        $t = portmapping_setup("PortMappingModule = fake\nPortMappingEnabled = true\n");
        $t['service']->start('demo', $t['robust']);
        $t['service']->start('demo_sim', $t['sim']);
        file_put_contents($t['grid'] . '/demo.conf', "[Grid]\nPortMappingModule = fake\nPortMappingEnabled = false\n");

        $t['service']->stop('demo_sim', $t['sim']);
        $t['service']->start('demo_sim', $t['sim']);

        expect(file_get_contents($t['regions']))->toContain('ExternalHostName = SYSTEMIP');
    });

    test('does nothing without a module', function () {
        $t = portmapping_setup("PortMappingEnabled = false\n");

        $t['service']->start('demo_sim', $t['sim']);
        $t['service']->stop('demo_sim', $t['sim']);

        expect(FakeModule::$calls)->toBe([]);
    });
});

describe('Upnpc module', function () {
    test('reads the LAN address, opens and closes with upnpc, gives the public address of external-ip', function () {
        $ran = [];
        $run = static function (array $command) use (&$ran): array {
            $ran[] = implode(' ', $command);

            return match ($command[0] . ($command[1] ?? '')) {
                'upnpc-s' => [0, "Found valid IGD\nLocal LAN ip address : 172.30.0.2\n"],
                'external-ip' => [0, "65.21.145.217\n"],
                default => [0, ''],
            };
        };
        $module = new UpnpcModule($run);

        expect($module->prepare())
            ->toBeTrue()
            ->and($module->open(9021, 'udp'))
            ->toBeTrue()
            ->and($module->close(9021, 'udp'))
            ->toBeTrue()
            ->and($module->address())
            ->toBe('65.21.145.217')
            ->and($ran)
            ->toBe(['upnpc -s', 'upnpc -a 172.30.0.2 9021 9021 udp', 'upnpc -d 9021 udp', 'external-ip']);
    });

    test('opens nothing without a LAN address, and gives no address that is not one', function () {
        $module = new UpnpcModule(static fn(array $command): array => $command[0] === 'external-ip' ? [0, "error\n"] : [1, '']);

        expect($module->prepare())
            ->toBeFalse()
            ->and($module->open(9021, 'udp'))
            ->toBeFalse()
            ->and($module->address())
            ->toBe('');
    });
});
