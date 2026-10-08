<?php

declare(strict_types=1);

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

    test('are copied into the presets of the machine, which the simulators read: every file, what is there is kept', function () {
        $kit = presets_root([
            'osgrid/config-include/GridCommon.ini' => "[Hypergrid]\r\nGatekeeperURI = \"http://hg.osgrid.org:80\"\r\n",
            'osgrid/OpenSim.ini' => "[Search]\nModule = OpenSimSearch\n",
            'osgrid/Helpers.ini' => "[Helpers]\nGridInfoURL = x\n",
        ]);
        $mine = presets_root(['osgrid/OpenSim.ini' => "[Search]\nModule = Mine\n"]);
        $presets = new Presets([$mine, $kit]);

        // The files a simulator includes, where it will read them (the machine's), those the preset has
        expect($presets->files('osgrid'))->toBe(['GridCommon' => "$mine/osgrid/config-include/GridCommon.ini", 'OpenSim' => "$mine/osgrid/OpenSim.ini"]);

        expect($presets->install('osgrid'))
            ->toBe(["$mine/osgrid/Helpers.ini", "$mine/osgrid/config-include/GridCommon.ini"])
            // Unix line endings, as every ini the setup writes
            ->and(file_get_contents("$mine/osgrid/config-include/GridCommon.ini"))
            ->toBe("[Hypergrid]\nGatekeeperURI = \"http://hg.osgrid.org:80\"\n")
            ->and(file_get_contents("$mine/osgrid/OpenSim.ini"))
            ->toBe("[Search]\nModule = Mine\n")
            // A second time changes nothing, and a preset of the machine alone has nothing to copy
            ->and($presets->install('osgrid'))
            ->toBe([])
            ->and((new Presets([$mine]))->install('osgrid'))
            ->toBe([]);
    });

    test('have no file a simulator includes when they have none of them, and none when they do not exist', function () {
        $root = presets_root(['speculoos/OpenSim.ini' => "[Search]\nModule = OpenSimSearch\n", 'osgrid/Helpers.ini' => "[Helpers]\n"]);
        $presets = new Presets([$root]);

        expect($presets->files('speculoos'))
            ->toBe(['OpenSim' => "$root/speculoos/OpenSim.ini"])
            ->and($presets->files('osgrid'))
            ->toBe([])
            ->and($presets->files('nothing'))
            ->toBe([]);
    });

    test('are included by the simulators that join the grid, after the architecture, so their settings win', function () {
        $plan = new SimPlan();
        $plan->gridDir = '/etc/opensim/grids/osgrid';
        $plan->presetFiles = ['GridCommon' => '/etc/opensim/presets/osgrid/config-include/GridCommon.ini', 'OpenSim' => '/etc/opensim/presets/osgrid/OpenSim.ini'];
        $text = (new SimConfig())->render($plan);

        expect($text)
            ->toContain('Include-PresetGridCommon = "/etc/opensim/presets/osgrid/config-include/GridCommon.ini"')
            ->toContain('Include-PresetOpenSim = "/etc/opensim/presets/osgrid/OpenSim.ini"')
            ->and(strpos($text, 'Include-PresetGridCommon'))
            ->toBeGreaterThan(strpos($text, 'Include-Architecture'))
            // A simulator of a grid of this machine has none
            ->and((new SimConfig())->render(new SimPlan()))
            ->not->toContain('Include-Preset');
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

