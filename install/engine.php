<?php

/**
 * Loads the OpenSim engine, which provides what every script of the kit shares
 * with the other applications. Loaded from composer's autoload, so from the
 * start of every entry point.
 *
 * The settings and credentials of the kit are kept in the user's config
 * directory. Loading the engine does not create it: the setup does, when it
 * needs it (Engine_Settings::create_config_directory()).
 */

if (!defined('OPENSIM_ENGINE')) {
    define('OPENSIM_ENGINE', true);
}

if (!defined('OPENSIM_CONFIG_DIR')) {
    $home = getenv('HOME');
    if (($home === false || $home === '') && function_exists('posix_getpwuid')) {
        $home = posix_getpwuid(posix_geteuid())['dir'] ?? '';
    }
    $base = getenv('XDG_CONFIG_HOME');
    define(
        'OPENSIM_CONFIG_DIR',
        ($base !== false && $base !== '' ? $base : rtrim((string) $home, '/') . '/.config') . '/opensim-kit',
    );
    unset($home, $base);
}

require_once __DIR__ . '/../vendor/magicoli/opensim-engine/bootstrap.php';
