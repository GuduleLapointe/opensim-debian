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

use OpenSim\Installer\Actions;
use OpenSim\Installer\Grid\GridPlan;
use OpenSim\Installer\Grid\NewGrid;
use OpenSim\Installer\Grid\NewSim;
use OpenSim\Installer\Grid\SimPlan;
use OpenSim\Installer\Hub;
use OpenSim\Installer\SetupFailed;
use OpenSim\Installer\Ui\PromptsUi;

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
    } else {
        (new Hub(new PromptsUi()))->run();
    }
} catch (SetupFailed) {
    exit(1);
}
