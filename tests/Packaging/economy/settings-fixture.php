<?php
/**
 * The constants an economy test needs that no setting of the grid gives (a money server, the rate of a currency, the key of
 * the confirm value): defined before the helpers read the config, from /tmp/economy-constants.json, which the scenario
 * (tests/Packaging/economy-scenario.sh) writes. Loaded by the test web server with auto_prepend_file.
 */

foreach (json_decode((string) @file_get_contents('/tmp/economy-constants.json'), true) ?: [] as $name => $value) {
    defined($name) || define($name, $value);
}
