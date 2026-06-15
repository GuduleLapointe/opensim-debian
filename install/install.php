<?php

declare(strict_types=1);

/**
 * OpenSim Debian installer (PHP wizard).
 *
 * The flow lives here and talks only to an InstallerUi; the CLI uses
 * PromptsUi (Laravel Prompts). A web frontend will implement the same
 * interface later. Phase 1: OpenSim version selection.
 */

require __DIR__ . '/../vendor/autoload.php';

use OpenSim\Installer\Releases;
use OpenSim\Installer\Ui\PromptsUi;

$ui = new PromptsUi();
$ui->intro('OpenSim Debian — installer');

$releases = new Releases();
$list = $ui->spin(static fn () => $releases->available(), 'Fetching the OpenSim release list…');

if ($list === []) {
    $ui->error('Could not retrieve the OpenSim release list (no network and no cache).');
    exit(1);
}

$options = [];
foreach ($list as $release) {
    $options[$release['version']] = 'opensim-' . $release['version']
        . ($release['installed'] ? '  (installed)' : '');
}
$options['dev'] = 'Development version (build from source — not yet implemented)';
$options['skip'] = 'Skip (install OpenSim manually later)';

$choice = $ui->choose(
    'OpenSim version to install',
    $options,
    array_key_first($options),
    'Move with arrows, Enter to select',
);

$ui->note("Selected: $choice");
$ui->outro('Phase 1 OK — version selection works.');
