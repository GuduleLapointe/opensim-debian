<?php

declare(strict_types=1);

/**
 * OpenSim Debian installer (PHP wizard).
 *
 * The flow lives here and talks only to an InstallerUi; the CLI uses PromptsUi
 * (Laravel Prompts). A web frontend will implement the same interface later.
 *
 * Phase 1: version selection. Phase 2: gather the rest into a Plan (no system
 * changes yet). Phase 3 will apply the plan.
 */

require __DIR__ . '/../vendor/autoload.php';

use OpenSim\Installer\Config;
use OpenSim\Installer\Distribution;
use OpenSim\Installer\Layout;
use OpenSim\Installer\Packages;
use OpenSim\Installer\Plan;
use OpenSim\Installer\Releases;
use OpenSim\Installer\Runtime;
use OpenSim\Installer\System;
use OpenSim\Installer\Ui\PromptsUi;

$ui = new PromptsUi();
$ui->intro('OpenSim Debian — installer');

// --- Version selection -------------------------------------------------------
$releases = new Releases();
$list = $ui->spin(static fn () => $releases->available(), 'Fetching the OpenSim release list…');
if ($list === []) {
    $ui->error('Could not retrieve the OpenSim release list (no network and no cache).');
    exit(1);
}

$options = [];
$byVersion = [];
foreach ($list as $release) {
    $options[$release['version']] = 'opensim-' . $release['version']
        . ($release['installed'] ? '  (installed)' : '');
    $byVersion[$release['version']] = $release;
}
$options['dev'] = 'Development version (build from source — not yet implemented)';
$options['skip'] = 'Skip (install OpenSim manually later)';

$choice = $ui->choose('OpenSim version to install', $options, array_key_first($options), 'Move with arrows, Enter to select');

if ($choice === 'dev') {
    $ui->error('Building from source is not implemented yet.');
    exit(1);
}
if ($choice === 'skip') {
    $ui->outro('Skipped — install OpenSim manually later, then re-run.');
    exit(0);
}

$plan = new Plan();
$plan->version = $choice;
$plan->tarball = $byVersion[$choice]['tarball'];

// --- Runtime gate (discriminating) -------------------------------------------
(new Runtime($ui))->gate($plan);

// --- Layout + locations ------------------------------------------------------
$absolute = static fn (string $v): ?string => str_starts_with($v, '/') ? null : 'Use an absolute path (starting with /).';

$plan->layout = $ui->choose('Installation layout', Layout::LABELS, 'system');
$plan->installPath = Layout::needsBase($plan->layout)
    ? $ui->text('Install base directory', Layout::defaultBase($plan->layout), $absolute)
    : Layout::defaultBase($plan->layout);

Layout::applyDefaults($plan);

// Confirm/adjust each location.
$plan->coreRoot = $ui->text('Core root', $plan->coreRoot, $absolute);
$plan->coreDirectory = $ui->text('Core directory', $plan->coreDirectory, $absolute);
$plan->etcRoot = $ui->text('Etc directory', $plan->etcRoot, $absolute);
$plan->varRoot = $ui->text('Var directory', $plan->varRoot, $absolute);
$plan->logsRoot = $ui->text('Logs directory', $plan->logsRoot, $absolute);
$plan->cacheRoot = $ui->text('Cache directory', $plan->cacheRoot, $absolute);
$plan->dataRoot = $ui->text('Data directory', $plan->dataRoot, $absolute);
$plan->sourcesDirectory = $ui->text('Sources directory', dirname(__DIR__) . '/src', $absolute);

// --- Profile name + default --------------------------------------------------
$plan->profile = $ui->text(
    'Profile name',
    "opensim-{$plan->version}",
    static fn (string $v): ?string => preg_match('/^[A-Za-z0-9._-]+$/', $v) ? null : 'Letters, digits, dot, dash and underscore only.',
);

$current = (new Config())->defaultProfile();
$plan->makeDefault = $current === null || $current === $plan->profile
    || $ui->confirm("Make '{$plan->profile}' the default install (current: $current)?", false);

// --- Show the gathered plan --------------------------------------------------
$lines = [];
foreach ($plan->summary() as $label => $value) {
    $lines[] = sprintf('  %-16s %s', $label . ':', $value);
}
$ui->note("Installation plan:\n" . implode("\n", $lines));

// --- Apply -------------------------------------------------------------------
if (!$ui->confirm('Proceed with the installation?', true)) {
    $ui->outro('Aborted — nothing changed.');
    exit(0);
}

$packages = new Packages($ui);
$packages->ensure('screen', 'screen'); // needed to run instances later

$ui->note('Updating git submodules…');
System::run('git -C ' . System::arg(dirname(__DIR__)) . ' submodule update --init');

// Create directories, owned by the current user.
$user = getenv('USER') ?: get_current_user();
$dirs = [
    $plan->etcRoot, "{$plan->etcRoot}/opensim.d", "{$plan->etcRoot}/robust.d", "{$plan->etcRoot}/grids",
    $plan->sourcesDirectory, $plan->coreRoot, $plan->coreDirectory,
    $plan->varRoot, $plan->logsRoot, $plan->cacheRoot, $plan->dataRoot,
];
foreach (array_unique($dirs) as $dir) {
    if (!is_dir($dir)) {
        System::run('sudo install -d -o ' . System::arg($user) . ' ' . System::arg($dir));
    }
}

(new Distribution($ui))->fetchAndExtract($plan);
(new Runtime($ui))->install($plan);

if (!is_file("{$plan->coreDirectory}/bin/OpenSim.exe")) {
    $ui->error("Core not found after install: {$plan->coreDirectory}/bin/OpenSim.exe");
    exit(1);
}

$path = (new Config())->write($plan);
$ui->note("Config written: $path (linked from ~/.config/opensim/opensim.conf)");

$ui->outro("OpenSim {$plan->version} installed. Configure a grid next (newgrid).");
