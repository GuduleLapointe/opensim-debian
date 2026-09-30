#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * opensim rest: type commands in the remote (REST) console of an instance, on
 * this machine, in a container or on another machine (see RestConsole).
 *
 *   rest.php --ini FILE [--wait SECONDS] (-- <command> | - | --repl)
 *   rest.php --url http://HOST:PORT --user USER [...]     password in OPENSIM_REST_PASSWORD
 *
 *   -- <command>   one command (the rest of the arguments)
 *   -              the lines of the standard input, one command or answer each
 *                  (an empty line answers a question with its default)
 *   --repl         a prompt, as the console of the instance
 *
 * --ini is the config of an instance of this machine: its port, user and password
 * are read from its [Network], and the console is reached at 127.0.0.1.
 * Exit code: 0 done, 1 cannot reach or open the console, 2 usage.
 */

require __DIR__ . '/../vendor/autoload.php';

use OpenSim\Installer\RestConsole;

$wait = 5.0;
$ini = $url = $user = null;
$mode = null;
$command = [];
$args = array_slice($argv, 1);
while ($args !== []) {
    $arg = array_shift($args);
    switch ($arg) {
        case '--ini':
            $ini = array_shift($args);
            break;
        case '--url':
            $url = array_shift($args);
            break;
        case '--user':
            $user = array_shift($args);
            break;
        case '--wait':
            $wait = (float) array_shift($args);
            break;
        case '--repl':
            $mode = 'repl';
            break;
        case '-':
            $mode = 'stdin';
            break;
        case '--':
            $mode = 'command';
            $command = $args;
            $args = [];
            break;
        default:
            fwrite(STDERR, "rest: unknown argument $arg\n");
            exit(2);
    }
}
if ($mode === null || ($ini === null && $url === null)) {
    fwrite(STDERR, "usage: rest.php (--ini FILE | --url URL --user USER) [--wait SECONDS] (-- command | - | --repl)\n");
    exit(2);
}

if ($ini !== null) {
    $console = RestConsole::fromIni($ini);
    if ($console === null) {
        fwrite(STDERR, "rest: no remote console is set in $ini (ConsolePort, ConsoleUser and ConsolePass of [Network])\n");
        exit(1);
    }
} else {
    $parts = parse_url((string) $url);
    $password = (string) getenv('OPENSIM_REST_PASSWORD');
    if (($parts['host'] ?? '') === '' || ($parts['port'] ?? 0) === 0 || $user === null || $password === '') {
        fwrite(STDERR, "rest: --url http://HOST:PORT, --user and OPENSIM_REST_PASSWORD are needed\n");
        exit(2);
    }
    $console = new RestConsole($parts['host'], (int) $parts['port'], $user, $password, $parts['scheme'] ?? 'http');
}

if (!$console->connect()) {
    fwrite(STDERR, "rest: {$console->error}\n");
    exit(1);
}

$send = static function (string $line) use ($console, $wait): bool {
    $result = $console->command($line, $wait);
    foreach ($result['lines'] as $text) {
        echo $text, "\n";
    }

    return !$result['closed'];
};

switch ($mode) {
    case 'command':
        $send(implode(' ', $command));
        break;
    case 'stdin':
        while (($line = fgets(STDIN)) !== false) {
            if (!$send(rtrim($line, "\r\n"))) {
                break;
            }
        }
        break;
    case 'repl':
        while (true) {
            echo $console->prompt !== '' ? $console->prompt : '> ';
            $line = fgets(STDIN);
            if ($line === false) {
                echo "\n";
                break;
            }
            if (!$send(rtrim($line, "\r\n"))) {
                break;
            }
        }
        break;
}
$console->close();
