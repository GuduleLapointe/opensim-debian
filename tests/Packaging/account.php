<?php

// Makes an account in the grid TEST_GRID (default testgrid), through the console of its Robust, as the setup does,
// for tests/Packaging/scenario.sh. TEST_ACCOUNT (default "Later User"), its password TEST_ACCOUNT_PASSWORD.

require '/usr/share/opensim-tools/vendor/autoload.php';
require __DIR__ . '/ScriptedUi.php';

use OpenSim\Installer\Config;
use OpenSim\Installer\Grid\Database;
use OpenSim\Installer\Grid\GridAccounts;
use OpenSim\Installer\Grid\GridInfo;

$ui = new ScriptedUi([]);
$grid = GridInfo::load((new Config())->profile(), getenv('TEST_GRID') ?: 'testgrid');
if ($grid === null) {
    echo "grid not found\n";
    exit(1);
}

$created = (new GridAccounts(new Database($ui), $ui))->create(
    $grid,
    getenv('TEST_ACCOUNT') ?: 'Later User',
    getenv('TEST_ACCOUNT_PASSWORD') ?: 'laterpw1',
    'later@example.com',
);
exit($created ? 0 : 1);
