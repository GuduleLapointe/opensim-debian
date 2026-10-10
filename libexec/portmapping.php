#!/usr/bin/env php
<?php
declare(strict_types=1);

/**
 * opensim portmapping: the port mapping of an instance, called by `opensim start` and `opensim stop`.
 *
 *   portmapping.php start <instance> <config file>   before it starts: ports mapped, regions announcing the public address
 *   portmapping.php stop <instance> <config file>    after it stopped: ports closed
 *
 * What the grid asks is in its config (see PortMappingService). Always succeeds: an instance starts, and stops, whatever
 * happens to its mapping.
 */

require __DIR__ . '/../bootstrap.php';

use OpenSim\Installer\Config;
use OpenSimKit\PortMapping\PortMappingService;

[$action, $instance, $ini] = array_pad(array_slice($argv, 1), 3, '');
if (!in_array($action, ['start', 'stop'], true) || $instance === '' || !is_file($ini)) {
    fwrite(STDERR, "usage: portmapping.php start|stop <instance> <config file>\n");
    exit(2);
}

$profile = (new Config())->profile();
$var = $profile['VarRoot'] ?? '';
if ($var === '') {
    $var = ($profile['DataRoot'] ?? sys_get_temp_dir());
}

$service = new PortMappingService(
    "$var/portmap",
    static function (string $message, bool $problem): void {
        // As the launcher says it: "opensim: <message>", on the standard error for a problem
        fwrite($problem ? STDERR : STDOUT, "opensim: $message\n");
    },
);
$action === 'start' ? $service->start($instance, $ini) : $service->stop($instance, $ini);
