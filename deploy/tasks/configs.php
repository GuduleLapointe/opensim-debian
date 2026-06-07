<?php

namespace Deployer;

/**
 * Grid config deployment from a private repository.
 *
 * The private configs repo is NOT part of this repository. Its local checkout
 * path is read from the OPENSIM_CONFIGS_REPO env var or the `configs_repo_local`
 * Deployer variable.
 *
 * Expected layout inside the private repo:
 *   grids/<grid-name>/
 *     common/          — config shared by all servers of this grid
 *       GridCommon.ini
 *       ...
 *     robust/          — robust-specific configs
 *       Robust.ini
 *       ...
 *     simulators/      — per-simulator configs
 *       <name>.ini
 *       ...
 *
 * Usage:
 *   dep configs:push speculoos
 *   dep configs:push w4os
 *   dep configs:push --hosts=ursull  (staging — uses grid label or 'staging' fallback)
 */

set('configs_repo_local', fn() => getenv('OPENSIM_CONFIGS_REPO') ?: '');

desc('Push grid configs from private repo to remote servers');
task('configs:push', function () {
    $repoLocal = get('configs_repo_local');

    if (!$repoLocal || !is_dir($repoLocal)) {
        writeln('<error>configs_repo_local is not set or does not exist.</error>');
        writeln('<comment>Set OPENSIM_CONFIGS_REPO env var or configs_repo_local in deploy.yaml.</comment>');
        return;
    }

    $host   = currentHost();
    $labels = $host->get('labels', []);
    $grid   = $labels['grid'] ?? 'staging';
    $roles  = (array)($labels['roles'] ?? []);
    $remote = $host->getRemoteUser() . '@' . $host->getHostname();
    $etc    = get('opensim_etc');

    $gridDir = rtrim($repoLocal, '/') . "/grids/$grid";

    if (!is_dir($gridDir)) {
        writeln("<comment>No config directory found for grid '$grid' at $gridDir, skipping.</comment>");
        return;
    }

    // Common grid config → etc root
    $commonDir = "$gridDir/common";
    if (is_dir($commonDir)) {
        rsyncConfigsTo("$commonDir/", "$remote:$etc/");
        writeln("  pushed common config for grid '$grid'");
    }

    // Robust config → etc/robust.d/
    if (in_array('robust', $roles) && is_dir("$gridDir/robust")) {
        run("mkdir -p $etc/robust.d");
        rsyncConfigsTo("$gridDir/robust/", "$remote:$etc/robust.d/");
        writeln("  pushed robust config");
    }

    // Simulator configs → etc/opensim.d/
    if ((in_array('simulator', $roles) || in_array('staging', $roles)) && is_dir("$gridDir/simulators")) {
        run("mkdir -p $etc/opensim.d");
        rsyncConfigsTo("$gridDir/simulators/", "$remote:$etc/opensim.d/");
        writeln("  pushed simulator configs");
    }
})->desc('Push grid configs from private repo');

desc('Pull the private configs repo before pushing (optional)');
task('configs:pull', function () {
    $repoLocal = get('configs_repo_local');

    if (!$repoLocal || !is_dir($repoLocal)) {
        writeln('<comment>configs_repo_local not set, skipping pull.</comment>');
        return;
    }

    runLocally("git -C " . escapeshellarg($repoLocal) . " pull --ff-only");
    writeln("  configs repo updated");
})->desc('Pull latest configs from private repo');

// Pull then push
task('configs:update', [
    'configs:pull',
    'configs:push',
])->desc('Pull and push grid configs');

/**
 * Rsync config files only (*.ini, *.xml, *.conf) without deleting remote extras.
 */
function rsyncConfigsTo(string $src, string $dest): void
{
    runLocally("rsync -az --include='*.ini' --include='*.xml' --include='*.conf' --include='*/' --exclude='*' "
        . escapeshellarg($src) . ' ' . $dest);
}
