<?php

declare(strict_types=1);

/**
 * OpenSim Debian — setup hub (PHP wizard).
 *
 * Single entry point: detect the current state and route to the relevant
 * action (install a core, create/modify a grid, …). The flow lives in classes
 * (Hub, Installer, Grid\NewGrid) driven through an InstallerUi, so a web
 * frontend can reuse them later.
 */

require __DIR__ . '/../vendor/autoload.php';

OpenSim\Installer\I18n::init();

use OpenSim\Installer\Actions;
use OpenSim\Installer\Grid\GridPlan;
use OpenSim\Installer\Grid\NewGrid;
use OpenSim\Installer\Grid\NewSim;
use OpenSim\Installer\Grid\SimPlan;
use OpenSim\Installer\Hub;
use OpenSim\Installer\Setup\SetupFile;
use OpenSim\Installer\Setup\SetupRunner;
use OpenSim\Installer\SetupFailed;
use OpenSim\Installer\Ui\PromptsUi;

/**
 * opensim setup --file FILE [--check] [--result FILE]: make a setup described in a file, or only check it.
 *
 * @param list<string> $args
 */
function setupFromFile(array $args): int
{
    $path = '';
    $check = false;
    $result = '';
    while ($args) {
        $arg = array_shift($args);
        if ($arg === '--check') {
            $check = true;
        } elseif ($arg === '--result') {
            $result = (string) array_shift($args);
        } else {
            $path = $arg;
        }
    }
    if ($path === '') {
        fwrite(STDERR, "usage: opensim setup --file FILE [--check] [--result FILE]\n");

        return 2;
    }
    try {
        $data = SetupFile::load($path);
    } catch (InvalidArgumentException $e) {
        fwrite(STDERR, $e->getMessage() . "\n");

        return 2;
    }
    if ($check) {
        echo "ok\n";

        return 0;
    }

    return (new SetupRunner(new PromptsUi()))->run($data, [], $result) === null ? 1 : 0;
}

// A failure is already explained on screen: end with an error code, rather
// than going back to the menu
try {
    if (($argv[1] ?? '') === '--apply-grid') {
        // The writing of a grid, run as the system user by the setup of
        // another user, which sends the plan on the standard input
        $data = json_decode((string) stream_get_contents(STDIN), true, 512, JSON_THROW_ON_ERROR);
        (new NewGrid(new PromptsUi()))->apply(GridPlan::fromArray($data['plan']), $data['profile']);
    } elseif (($argv[1] ?? '') === '--apply-sim') {
        // The same for a simulator
        $data = json_decode((string) stream_get_contents(STDIN), true, 512, JSON_THROW_ON_ERROR);
        (new NewSim(new PromptsUi()))->apply(SimPlan::fromArray($data['plan']), $data['profile']);
    } elseif (($argv[1] ?? '') === '--apply-action') {
        // What a menu does to the install (enable, disable, restart), as the system user
        $data = json_decode((string) stream_get_contents(STDIN), true, 512, JSON_THROW_ON_ERROR);
        exit((new Actions(new PromptsUi()))->perform($data['op'], $data['args'], $data['profile']) ? 0 : 1);
    } elseif (($argv[1] ?? '') === '--file') {
        // A setup described in a file (JSON or YAML), made without questions
        exit(setupFromFile(array_slice($argv, 2)));
    } else {
        (new Hub(new PromptsUi()))->run();
    }
} catch (SetupFailed) {
    exit(1);
}
