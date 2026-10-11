#!/usr/bin/env php
<?php

/**
 * What a package that is installed adds to the grids it finds, without the setup: the files of the grid that are missing
 * (the MoneyServer.ini the helpers need, from the original of the MoneyServer, with its key made at random). What exists is
 * not touched: the setup does the merging.
 */

require dirname(__DIR__) . '/bootstrap.php';

use OpenSim\Installer\Config;
use OpenSim\Installer\Grid\MoneyServerConfig;

$etcRoot = (new Config())->profile()['EtcRoot'] ?? '';
foreach (glob("$etcRoot/grids/*", GLOB_ONLYDIR) ?: [] as $dir) {
    // A grid with helpers has a Helpers.ini, and its helpers need the key
    if (!is_file("$dir/Helpers.ini") || ($made = MoneyServerConfig::ensure($dir)) === null) {
        continue;
    }
    echo "opensim: wrote $made\n";
}

