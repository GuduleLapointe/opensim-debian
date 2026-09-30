<?php

// Runs the simulator wizard of the installed kit with scripted answers, for
// packaging/test/scenario.sh.

require '/usr/share/opensim-tools/vendor/autoload.php';
require __DIR__ . '/ScriptedUi.php';

use OpenSim\Installer\Grid\NewSim;
use OpenSim\Installer\SetupFailed;

// TEST_GRID: the grid, TEST_SIM: the simulator, TEST_REGION: its first region,
// TEST_OWNER: an account of the grid for the estate (else the first one),
// TEST_NEW_OWNER / TEST_NEW_OWNER_PASSWORD: an account to create instead,
// TEST_START: start it, TEST_DB_PASSWORD: the database password (empty keeps
// the one of the grid), TEST_ADMIN_USER / TEST_ADMIN_PASSWORD: an administrator
// account of the database server. The retry after a database error is always
// declined.
$sim = getenv('TEST_SIM') ?: 'Sim1';
$answers = [
    'Try again' => false,
    'Simulator name' => $sim,
    'Enable simulator' => true,
    'Start simulator' => (bool) getenv('TEST_START'),
    'Create the first region' => true,
    'Region name' => getenv('TEST_REGION') ?: $sim,
    'Enter the credentials' => (bool) getenv('TEST_ADMIN_USER'),
    'Administrator user' => getenv('TEST_ADMIN_USER') ?: 'root',
    'Password of the new account' => getenv('TEST_NEW_OWNER_PASSWORD') ?: 'ownerpw1',
    'Email of the new account' => 'owner@example.com',
];
if (getenv('TEST_NEW_OWNER')) {
    $answers['Estate owner'] = '+';
    $answers['Name of the new account'] = getenv('TEST_NEW_OWNER');
} elseif (getenv('TEST_OWNER')) {
    $answers['Estate owner'] = getenv('TEST_OWNER');
}
if (getenv('TEST_ADMIN_PASSWORD')) {
    $answers['Password of ' . (getenv('TEST_ADMIN_USER') ?: 'root')] = getenv('TEST_ADMIN_PASSWORD');
}
if (getenv('TEST_DB_PASSWORD')) {
    $answers['Database password'] = getenv('TEST_DB_PASSWORD');
}

try {
    (new NewSim(new ScriptedUi($answers)))->run(getenv('TEST_GRID') ?: null);
} catch (SetupFailed) {
    echo "setup failed\n";
    exit(1);
}
