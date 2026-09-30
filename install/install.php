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

use OpenSim\Installer\Hub;
use OpenSim\Installer\SetupFailed;
use OpenSim\Installer\Ui\PromptsUi;

// A failure is already explained on screen: end with an error code, rather
// than going back to the menu
try {
    (new Hub(new PromptsUi()))->run();
} catch (SetupFailed) {
    exit(1);
}
