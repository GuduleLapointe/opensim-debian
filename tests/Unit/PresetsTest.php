<?php


/**
 * The presets of the grids a simulator can join: folders with the structure of the folder of a grid, any file of it optional,
 * read where they are.
 */

use OpenSim\Installer\Grid\Presets;
use OpenSim\Installer\Grid\SimConfig;
use OpenSim\Installer\Grid\SimPlan;

/** A folder of presets in a temporary tree. @param array<string,string> $files path => content */
function presets_root(array $files): string
{
    $root = sys_get_temp_dir() . '/presets-' . bin2hex(random_bytes(4));
    foreach ($files as $path => $content) {
        is_dir(dirname("$root/$path")) || mkdir(dirname("$root/$path"), 0o755, true);
        file_put_contents("$root/$path", $content);
    }

    return $root;
}

describe('The presets', function () {
    test('are the folders there are, the ones of the administrator before the ones of the kit', function () {
        $kit = presets_root([
            'osgrid/config-include/GridCommon.ini' => "[Hypergrid]\nGatekeeperURI = \"http://hg.osgrid.org:80\"\n",
            'speculoos/OpenSim.ini' => "[DataSnapshot]\ngridname = \"Kit Speculoos\"\n[Messaging]\nGatekeeper = http://speculoos.world:8002\n",
        ]);
        $mine = presets_root(['speculoos/OpenSim.ini' => "[DataSnapshot]\ngridname = \"My Speculoos\"\n[Messaging]\nGatekeeper = http://speculoos.world:8002\n"]);
        $presets = new Presets([$mine, $kit]);

        expect($presets->names())->toBe(['osgrid' => 'osgrid', 'speculoos' => 'My Speculoos']);
    });

    test('say where the grid is from the files they have, whichever they are', function () {
        $root = presets_root([
            'osgrid/config-include/GridCommon.ini' => "[Hypergrid]\n    GatekeeperURI = \"http://hg.osgrid.org:80\"\n[GridService]\n    GridServerURI = \"http://grid.osgrid.org\"\n",
            'osgrid/OpenSim.ini' => "[DataSnapshot]\n    gridname = \"OSGrid\"\n",
            'speculoos/OpenSim.ini' => "[Hypergrid]\n    GatekeeperURI = \"http://speculoos.world:8002\"\n[GridService]\n    GridServerURI = \"http://speculoos.world:8003\"\n",
        ]);
        $presets = new Presets([$root]);

        expect($presets->describe('osgrid'))
            ->toBe(['name' => 'OSGrid', 'nick' => 'osgrid', 'host' => 'hg.osgrid.org', 'publicPort' => 80, 'privatePort' => 80, 'hypergrid' => true])
            ->and($presets->describe('speculoos'))
            ->toBe(['name' => 'speculoos', 'nick' => 'speculoos', 'host' => 'speculoos.world', 'publicPort' => 8002, 'privatePort' => 8003, 'hypergrid' => true])
            ->and($presets->describe('nothing'))
            ->toBeNull()
            ->and($presets->path('../etc'))
            ->toBeNull();
    });

    test('are made complete the same way every time: the distribution merged with the preset and with what the machine has', function () {
        $bin = presets_root([
            'OpenSim.ini.example' => "[Const]\n    BaseHostname = \"127.0.0.1\"\n[Search]\n    ;Module = \"BasicSearchModule\"\n    ; a comment of the distribution\n[Messaging]\n    OfflineMessageModule = \"Offline Message Module V2\"\n",
            'OpenSimDefaults.ini' => "[Startup]\n    a = 1\n",
            'config-include/GridCommon.ini.example' => "[DatabaseService]\n    Include-Storage = \"config-include/storage/SQLiteStandalone.ini\"\n[Hypergrid]\n    HomeURI = \"http://\${Const|BaseHostname}\"\n",
            'config-include/GridHypergrid.ini' => "[Architecture]\n    Include-Common = \"config-include/GridCommon.ini\"\n",
        ]);
        $kit = presets_root([
            'osgrid/OpenSim.ini' => "[Search]\nModule = OpenSimSearch\n[Messaging]\nOfflineMessageURL = \"http://services.osgrid.org\"\n",
            'osgrid/config-include/GridCommon.ini' => "[Hypergrid]\r\nHomeURI = \"http://hg.osgrid.org:80\"\r\n",
            'osgrid/destinations.txt' => "Plaza|hop://x\n",
        ]);
        $mine = presets_root(['osgrid/OpenSim.ini' => "[Search]\nModule = Mine\n[Chat]\nsay_distance = 40\n"]);
        $presets = new Presets([$mine, $kit]);

        $presets->install('osgrid', "$bin", true);
        $merged = file_get_contents("$mine/osgrid/OpenSim.ini");

        // The complete file of the distribution, with the preset's settings over it, the machine's others kept
        expect($merged)
            ->toContain('a comment of the distribution')
            ->toContain('BaseHostname = "127.0.0.1"')
            ->toContain('Module = OpenSimSearch')
            ->toContain('OfflineMessageURL = "http://services.osgrid.org"')
            ->toContain('say_distance = 40')
            // The files of the distribution the preset has nothing for are there, and the ones that are not ini are copied
            ->and(is_file("$mine/osgrid/OpenSimDefaults.ini"))
            ->toBeTrue()
            ->and(file_get_contents("$mine/osgrid/destinations.txt"))
            ->toBe("Plaza|hop://x\n")
            // The services of the grid take the place of the generic ones; the storage of the distribution would override the database
            // of the simulator, and the includes are the ones of the folder
            ->and(file_get_contents("$mine/osgrid/config-include/GridCommon.ini"))
            ->toContain('HomeURI = "http://hg.osgrid.org:80"')
            ->toMatch('/^;\\s*Include-Storage/m')
            ->and(file_get_contents("$mine/osgrid/config-include/GridHypergrid.ini"))
            ->toContain("Include-Common = \"$mine/osgrid/config-include/GridCommon.ini\"");

        // The defaults of the application are a copy of the distribution's, whatever the preset or the machine says
        file_put_contents("$mine/osgrid/OpenSimDefaults.ini", "[Startup]\n    a = 99\n");
        $presets->install('osgrid', "$bin", true);
        expect(file_get_contents("$mine/osgrid/OpenSimDefaults.ini"))->toBe("[Startup]\n    a = 1\n");

        // The same again changes nothing, and a new setting of the distribution is taken
        $first = $presets->install('osgrid', "$bin", true);
        file_put_contents("$bin/OpenSimDefaults.ini", "[Startup]\n    a = 1\n    b = 2\n");
        $presets->install('osgrid', "$bin", true);

        expect($first)
            ->toBe([])
            ->and(file_get_contents("$mine/osgrid/OpenSimDefaults.ini"))
            ->toContain('b = 2');
    });

    test('give the simulators what they add to the distribution, not the whole file', function () {
        $bin = presets_root(['OpenSim.ini.example' => "[Const]\n    BaseHostname = \"127.0.0.1\"\n[Messaging]\n    OfflineMessageModule = \"Offline Message Module V2\"\n"]);
        $kit = presets_root(['osgrid/OpenSim.ini' => "[Search]\nModule = OpenSimSearch\n[Messaging]\nOfflineMessageModule = \"Offline Message Module V2\"\nOfflineMessageURL = \"http://services.osgrid.org\"\n"]);
        $mine = presets_root([]);
        $presets = new Presets([$mine, $kit]);
        $presets->install('osgrid', $bin, true);

        expect($presets->settings('osgrid', $bin))
            ->toBe("[Messaging]\nOfflineMessageURL = \"http://services.osgrid.org\"\n\n[Search]\nModule = OpenSimSearch\n\n")
            ->and($presets->settings('nothing', $bin))
            ->toBe('');
    });

    test('are read by the simulators from their own folder: the architecture, and the settings laid over their config', function () {
        $plan = new SimPlan();
        $plan->gridDir = '/etc/opensim/grids/osgrid';
        $plan->configDir = '/etc/opensim/presets/osgrid';
        $plan->presetSettings = "[Search]\nModule = OpenSimSearch\n[UserProfiles]\nProfileServiceURL = \"http://services.osgrid.org\"\n";
        $text = (new SimConfig())->render($plan);

        expect($text)
            ->toContain('Include-Architecture = "/etc/opensim/presets/osgrid/config-include/GridHypergrid.ini"')
            ->toContain('Include-osslDefaultEnable = "/etc/opensim/presets/osgrid/config-include/osslDefaultEnable.ini"')
            ->toContain('ProfileServiceURL = "http://services.osgrid.org"')
            ->not->toContain('ProfileServiceURL = "${Const|BaseURL}')
            // A simulator of a grid of this machine reads the folder of its grid
            ->and((new SimConfig())->render(new SimPlan()))
            ->toContain('Include-Architecture = "/config-include/')
            ->not->toContain('Module = OpenSimSearch');
    });
});

describe('The presets of the kit', function () {
    $kit = dirname(__DIR__, 2) . '/share/presets';

    test('are speculoos and osgrid, and say where their grids are', function () use ($kit) {
        $presets = new Presets([$kit]);

        expect(array_keys($presets->names()))
            ->toBe(['osgrid', 'speculoos'])
            ->and($presets->describe('osgrid'))
            ->toMatchArray(['name' => 'OSGrid', 'host' => 'hg.osgrid.org', 'publicPort' => 80])
            ->and($presets->describe('speculoos'))
            ->toMatchArray(['host' => 'speculoos.world', 'publicPort' => 8002, 'privatePort' => 8003]);
    });

    test('are ordinary ini files, each one readable', function () use ($kit) {
        foreach (array_merge(glob("$kit/*/OpenSim.ini") ?: [], glob("$kit/*/config-include/*.ini") ?: []) as $path) {
            expect(@parse_ini_file($path, true, INI_SCANNER_RAW))->toBeArray($path);
        }
    });
});

describe('The simulator that joins a grid by a preset', function () {
    test('is asked nothing more than the preset: the grid is described, kept with its preset', function () {
        require_once dirname(__DIR__) . '/Packaging/ScriptedUi.php';
        $etc = sys_get_temp_dir() . '/etc-' . bin2hex(random_bytes(4));
        mkdir($etc);
        $wizard = new OpenSim\Installer\Grid\NewSim(new ScriptedUi(['Grid to join' => 'osgrid']));
        $remote = new ReflectionMethod($wizard, 'remoteGrid');
        $remote->setAccessible(true);

        $grid = $remote->invoke($wizard, ['EtcRoot' => $etc, 'CoreDirectory' => '/opt/core', 'DataRoot' => '/var/lib/opensim/data', 'CacheRoot' => '/var/cache/opensim', 'LogsRoot' => '/var/log/opensim']);

        expect($grid)->not->toBeNull();
        expect([$grid->remote, $grid->nick, $grid->name, $grid->baseHostname, $grid->publicPort, $grid->preset])
            ->toBe([true, 'osgrid', 'OSGrid', 'hg.osgrid.org', 80, 'osgrid']);
        expect($grid->describe()['preset'])->toBe('osgrid');
    });
});

describe('The host scripts see as the one of the simulator', function () {
    test('is the address of the machine the simulator runs on, never the host of the grid', function () {
        $plan = new SimPlan();
        $plan->baseHostname = 'hg.osgrid.org';
        $plan->externalHost = 'play.example.org';

        expect(SimConfig::lslHost($plan))
            ->toBe('play.example.org')
            ->and((new SimConfig())->render($plan))
            ->toContain('ExternalHostNameForLSL = "play.example.org"')
            ->not->toContain('ExternalHostNameForLSL = "hg.osgrid.org"')
            // Without an address given, the name of the machine, not the one of the grid
            ->and(SimConfig::lslHost(new SimPlan()))
            ->not->toBe('hg.osgrid.org');
    });
});
