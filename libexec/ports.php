#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * opensim ports: the ports the enabled instances listen on, with what each one
 * is for and who has to reach it (see InstancePorts).
 *
 *   opensim ports [instance...]               the table
 *   opensim ports --publish [instance...]     the -p options of podman or docker
 *   opensim ports --ufw [instance...]         the rules of the firewall
 *   --private-address=ADDRESS                 where the private and console ports
 *                                             are published (default 127.0.0.1)
 */

require __DIR__ . '/../vendor/autoload.php';

use OpenSim\Installer\Config;
use OpenSim\Installer\Grid\GridInfo;
use OpenSim\Installer\InstancePorts;

$mode = 'table';
$address = '127.0.0.1';
$names = [];
foreach (array_slice($argv, 1) as $arg) {
    if ($arg === '--publish' || $arg === '--ufw') {
        $mode = substr($arg, 2);
    } elseif (str_starts_with($arg, '--private-address=')) {
        $address = substr($arg, strlen('--private-address='));
    } elseif ($arg === '-h' || $arg === '--help') {
        echo "usage: opensim ports [--publish|--ufw] [--private-address=ADDRESS] [instance...]\n";
        exit(0);
    } else {
        $names[] = GridInfo::instanceName($arg);
    }
}

$etc = (new Config())->profile()['EtcRoot'] ?? '';
$instances = [];
foreach (['robust' => 'robust.d', 'opensim' => 'opensim.d'] as $type => $folder) {
    foreach (glob("$etc/$folder/*.ini") ?: [] as $ini) {
        $name = GridInfo::instanceName(basename($ini, '.ini'));
        if ($names === [] || in_array($name, $names, true)) {
            $instances[] = [$name, $type, $ini];
        }
    }
}
if ($instances === []) {
    fwrite(STDERR, "ports: no enabled instance" . ($names !== [] ? ' of that name' : '') . "\n");
    exit(1);
}

$rows = [];
foreach ($instances as [$name, $type, $ini]) {
    foreach (InstancePorts::of($ini) as $use) {
        $rows[] = $use + ['instance' => $name, 'type' => $type];
    }
}

switch ($mode) {
    case 'publish':
        // Public ports for everyone; private and console ones only where they are
        // asked for: they have no protection of their own
        $options = [];
        foreach ($rows as $row) {
            if ($row['use'] === 'off') {
                continue;
            }
            $bind = $row['use'] === 'public' ? '' : "$address:";
            $options[] = "-p $bind{$row['port']}:{$row['port']}" . ($row['proto'] === 'udp' ? '/udp' : '');
        }
        echo implode(' ', array_unique($options)), "\n";
        break;

    case 'ufw':
        foreach ($rows as $row) {
            if ($row['use'] === 'off') {
                continue;
            }
            echo $row['use'] === 'public'
                ? "ufw allow {$row['port']}/{$row['proto']}    # {$row['instance']}: {$row['role']}\n"
                : "# {$row['port']}/{$row['proto']} ({$row['instance']}: {$row['role']}): only from the machines that need it, e.g. ufw allow from <address> to any port {$row['port']} proto {$row['proto']}\n";
        }
        break;

    default:
        $line = static fn (array $cells): string => sprintf('%-22s %-8s %-6s %-5s %-8s %s', ...$cells);
        echo $line(['INSTANCE', 'TYPE', 'PORT', 'PROTO', 'USE', 'FOR']), "\n";
        foreach ($rows as $row) {
            echo $line([$row['instance'], $row['type'], $row['port'], $row['proto'], $row['use'], $row['role']]), "\n";
        }
}
