<?php

namespace Deployer;

/**
 * Systemd service management and simulator activation via symlinks.
 *
 * Simulator enabling/disabling follows the pattern:
 *   etc/opensim.d/enabled/<name>.ini  →  ../opensim.d/<name>.ini
 *
 * bin/opensim only starts simulators found in opensim.d/enabled/.
 *
 * Usage:
 *   dep sim:enable  sandbox ursull
 *   dep sim:disable sandbox ursull
 *   dep sim:list ursull
 *   dep services:reload ursull
 */

// ── Simulator activation ──────────────────────────────────────────────────────

desc('Enable a simulator by creating its symlink in opensim.d/enabled/');
task('sim:enable', function () {
    // Pass name via: dep sim:enable --set sim=<name> [host]  OR  SIM=<name> dep sim:enable [host]
    $name = get('sim', getenv('SIM') ?: '');

    if (!$name) {
        writeln('<error>Specify simulator name: dep sim:enable --set sim=<name> [host]</error>');
        return;
    }

    $etc     = get('opensim_etc');
    $simIni  = "$etc/opensim.d/$name.ini";
    $enabled = "$etc/opensim.d/enabled";

    run("[ -f $simIni ] || { echo 'Config $simIni not found'; exit 1; }");
    run("mkdir -p $enabled");
    run("ln -sf ../$name.ini $enabled/$name.ini");
    writeln("  enabled simulator '$name'");
})->desc('Enable a simulator (create symlink)');

desc('Disable a simulator by removing its symlink from opensim.d/enabled/');
task('sim:disable', function () {
    $name = get('sim', getenv('SIM') ?: '');

    if (!$name) {
        writeln('<error>Specify simulator name: dep sim:disable --set sim=<name> [host]</error>');
        return;
    }

    $etc     = get('opensim_etc');
    $enabled = "$etc/opensim.d/enabled";

    run("rm -f $enabled/$name.ini");
    writeln("  disabled simulator '$name'");
})->desc('Disable a simulator (remove symlink)');

desc('List enabled simulators on remote host');
task('sim:list', function () {
    $etc     = get('opensim_etc');
    $enabled = "$etc/opensim.d/enabled";

    $result = run("ls -1 $enabled/*.ini 2>/dev/null | xargs -I{} basename {} .ini || echo '(none)'");
    writeln("Enabled simulators on " . currentHost()->getAlias() . ":\n  $result");
})->desc('List enabled simulators');

// ── Systemd ───────────────────────────────────────────────────────────────────

desc('Reload systemd daemon on remote host');
task('services:reload', function () {
    run('sudo systemctl daemon-reload');
    writeln('  systemd reloaded');
})->desc('Reload systemd daemon');

desc('Restart opensim service (all enabled simulators)');
task('services:restart', function () {
    run('sudo systemctl restart opensim.service 2>/dev/null || sudo systemctl restart opensim@*.service 2>/dev/null || true');
    writeln('  opensim restarted');
})->desc('Restart opensim service');

desc('Show opensim service status');
task('services:status', function () {
    $result = run('sudo systemctl status opensim.service 2>/dev/null || sudo systemctl status "opensim@*.service" 2>/dev/null || echo "No opensim service found"');
    writeln($result);
})->desc('Show opensim service status');
