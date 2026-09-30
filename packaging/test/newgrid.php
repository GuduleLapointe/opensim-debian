<?php

// Runs the grid wizard of the installed kit with scripted answers, for
// packaging/test/scenario.sh.

require '/usr/share/opensim-tools/vendor/autoload.php';

use OpenSim\Installer\Grid\NewGrid;
use OpenSim\Installer\SetupFailed;

require __DIR__ . '/ScriptedUi.php';

// The grid and the database password can be given by the environment; the
// retry after a database error is always declined, and the grid is not
// enabled when TEST_NO_ENABLE is set
$name = getenv('TEST_GRID') ?: 'Testgrid';

// TEST_DB_PASSWORD empty: the password proposed is kept; TEST_DB_USER: the account. TEST_REPEAT runs the
// setup several times in the same process, as the setup hub does. TEST_ADMIN_USER and TEST_ADMIN_PASSWORD
// answer the request for an administrator account, which is declined when they are not set.
$answers = [
    'Grid name' => $name,
    'Grid nick' => strtolower($name),
    'Base hostname' => 'localhost',
    'Try again' => false,
    'Enable grid' => getenv('TEST_NO_ENABLE') ? false : true,
    'Start grid' => false,
    'Enter the credentials' => (bool) getenv('TEST_ADMIN_USER'),
    'Administrator user' => getenv('TEST_ADMIN_USER') ?: 'root',
    'Password of' => getenv('TEST_ADMIN_PASSWORD') ?: '',
];
$password = getenv('TEST_DB_PASSWORD');
if ($password !== '') {
    $answers['Database password'] = $password ?: 'testpass';
}
if (getenv('TEST_DB_USER')) {
    $answers['Database user'] = getenv('TEST_DB_USER');
}

// Start the grid when TEST_START is set (the setup started as root, as
// in the packaged install)
if (getenv('TEST_START')) {
    $answers['Start grid'] = true;
}

try {
    for ($i = 0; $i < (int) (getenv('TEST_REPEAT') ?: 1); $i++) {
        (new NewGrid(new ScriptedUi($answers)))->run(null);
    }
} catch (SetupFailed) {
    echo "setup failed\n";
    exit(1);
}
