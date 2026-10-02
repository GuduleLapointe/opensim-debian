<?php

namespace Deployer;

/**
 * Code deployment tasks.
 *
 * Modules:
 *   core            — bin/, libexec/, lib/, share/, etc/ templates
 *   opensim-modules — OpenSim addon-modules (Gloebit, OMEconomy, OpenSimSearch…)
 *   web             — web integrations (opensim-helpers, rest-php…)
 *
 * Usage:
 *   dep deploy staging                         # core (default)
 *   dep deploy:module staging opensim-modules
 *   dep deploy:all staging
 */

set('local_src', __DIR__ . '/../..');

// ── Core ──────────────────────────────────────────────────────────────────────

desc('Deploy core module (bin, libexec, lib, share, etc templates)');
task('deploy:core', function () {
    $src = get('local_src');
    $bin = get('opensim_bin');
    $libexec = get('opensim_libexec');
    $lib = get('opensim_lib');
    $share = get('opensim_share');
    $etc = get('opensim_etc');
    $remote = remoteTarget();

    // Single user-facing orchestrator
    rsyncTo("$src/bin/opensim", "$remote:$bin/opensim");

    // Helper executables (called by opensim, not directly by users)
    rsyncTo("$src/libexec/", "$remote:$libexec/", excludeFile('core'));

    // PHP libraries (composer packages, linked packages copied as real files)
    rsyncTo("$src/vendor/", "$remote:$lib/vendor/", '', ['--copy-links']);

    // Static data and system templates
    rsyncTo("$src/share/systemd/", "$remote:$share/systemd/");
    rsyncTo("$src/share/mysql/", "$remote:$share/mysql/");
    rsyncTo("$src/share/bash_completion.d/", "$remote:$share/bash_completion.d/");
    rsyncTo("$src/share/cron.hourly/", "$remote:$share/cron.hourly/");

    // Config templates (exclude instance configs and generated paths.conf)
    rsyncTo("$src/etc/", "$remote:$etc/", excludeFile('core'), [
        '--exclude=opensim.d/',
        '--exclude=robust.d/',
        '--exclude=grids/',
        '--exclude=paths.conf',
    ]);
})->desc('Deploy core opensim-debian tools');

// ── OpenSim addon-modules ─────────────────────────────────────────────────────

desc('Deploy OpenSim addon-modules (Gloebit, OMEconomy, OpenSimSearch…)');
task('deploy:opensim-modules', function () {
    $src = get('local_src');
    $core = get('opensim_core');
    $remote = remoteTarget();

    $moduleSources = ['contrib/Gloebit', 'contrib/OMEconomy-Modules', 'contrib/OpenSimSearch'];

    foreach ($moduleSources as $modSrc) {
        if (is_dir("$src/$modSrc")) {
            rsyncTo("$src/$modSrc/", "$remote:$core/addon-modules/" . basename($modSrc) . '/');
        }
    }
})->desc('Deploy OpenSim addon-modules');

// ── Web integrations ──────────────────────────────────────────────────────────

desc('Deploy web integrations (opensim-helpers, offline messages…)');
task('deploy:web', function () {
    $src = get('local_src');
    $lib = get('opensim_lib');
    $remote = remoteTarget();

    if (is_dir("$src/vendor/magicoli/opensim-helpers")) {
        rsyncTo("$src/vendor/magicoli/opensim-helpers/", "$remote:$lib/opensim-helpers/", '', ['--copy-links']);
    }
})->desc('Deploy web integration modules');

// ── Composite tasks ───────────────────────────────────────────────────────────

desc('Deploy a named module (core|opensim-modules|web)');
task('deploy:module', function () {
    invoke('deploy:' . get('module', 'core'));
});

desc('Deploy all modules');
task('deploy:all', function () {
    invoke('layout:write-paths');
    foreach (['core', 'opensim-modules', 'web'] as $mod) {
        invoke("deploy:$mod");
    }
});

// Default `dep deploy` — write paths then deploy core
desc('Deploy core module with layout paths');
task('deploy', ['layout:write-paths', 'deploy:core']);

// ── Helpers ───────────────────────────────────────────────────────────────────

/**
 * Return "user@host" for the current host.
 */
function remoteTarget(): string
{
    $host = currentHost();
    return $host->getRemoteUser() . '@' . $host->getHostname();
}

/**
 * Run rsync from local to remote with standard options.
 *
 * @param string   $src     Local path
 * @param string   $dest    Remote path (user@host:path)
 * @param string   $exclude Path to --exclude-from file ('' = none)
 * @param string[] $extra   Additional rsync flags
 */
function rsyncTo(string $src, string $dest, string $exclude = '', array $extra = []): void
{
    $flags = ['-az', '--delete'];

    if ($exclude && file_exists($exclude)) {
        $flags[] = '--exclude-from=' . escapeshellarg($exclude);
    }

    foreach ($extra as $flag) {
        $flags[] = $flag;
    }

    $cmd = 'rsync ' . implode(' ', $flags) . ' ' . escapeshellarg($src) . ' ' . $dest;
    runLocally($cmd);
}

/**
 * Return the path to the exclude file for a given module.
 * Falls back to deploy/.rsync-exclude if no module-specific file exists.
 */
function excludeFile(string $module): string
{
    $base = __DIR__ . '/..';
    $specific = "$base/modules/$module.exclude";
    $default = "$base/.rsync-exclude";

    return file_exists($specific) ? $specific : $default;
}
