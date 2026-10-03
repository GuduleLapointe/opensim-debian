#!/usr/bin/env php
<?php
declare(strict_types=1);

/**
 * opensim users: the accounts of a grid.
 *
 *   opensim users import FILE [--grid NICK] [--apply] [--keep-going] [--result FILE] [--format csv|json|yaml]
 *
 * Makes the accounts of a list, CSV (first, last, email, password; a header line is accepted) or JSON (a list of
 * objects with the same keys), directly in the database of the grid, as Robust does when `create user` is typed in
 * its console (see Grid\AccountWriter). Nothing is written without --apply: the list is checked and what would be
 * made is told. An account whose name exists is skipped; one without a password gets one, written in the result
 * file (mode 600) and never on the screen.
 */

require __DIR__ . '/../vendor/autoload.php';

use OpenSim\Installer\Config;
use OpenSim\Installer\Grid\AccountImporter;
use OpenSim\Installer\Grid\AccountList;
use OpenSim\Installer\Grid\Database;
use OpenSim\Installer\Grid\GridInfo;
use OpenSim\Installer\Ui\QuietUi;

const USAGE = "usage: opensim users import FILE [--grid NICK] [--apply] [--keep-going] [--result FILE] [--format csv|json|yaml]\n";

/** Say what is wrong and stop. */
function fail(string $message, int $code = 1): never
{
    fwrite(STDERR, "users: $message\n");
    exit($code);
}

$args = array_slice($argv, 1);
if (($args[0] ?? '') !== 'import') {
    fwrite(STDERR, USAGE);
    exit(($args[0] ?? '') === '-h' || ($args[0] ?? '') === '--help' ? 0 : 2);
}
array_shift($args);

$options = ['grid' => '', 'result' => '', 'format' => ''];
$flags = ['apply' => false, 'keep-going' => false];
$words = [];
for ($i = 0; $i < count($args); $i++) {
    if (preg_match('/^--(grid|result|format)(?:=(.*))?$/', $args[$i], $m)) {
        $options[$m[1]] = $m[2] ?? ($args[++$i] ?? fail("--{$m[1]} needs a value", 2));
    } elseif (preg_match('/^--(apply|keep-going)$/', $args[$i], $m)) {
        $flags[$m[1]] = true;
    } else {
        $words[] = $args[$i];
    }
}
if (count($words) !== 1) {
    fwrite(STDERR, USAGE);
    exit(2);
}
if (!is_readable($words[0])) {
    fail("cannot read {$words[0]}", 2);
}
if ($options['format'] === '' && preg_match('/\.ya?ml$/i', $words[0])) {
    $options['format'] = 'yaml';
}
if ($options['format'] !== '' && !in_array($options['format'], ['csv', 'json', 'yaml'], true)) {
    fail('the format is csv, json or yaml', 2);
}

$profile = (new Config())->profile();
$etc = $profile['EtcRoot'] ?? '';
$grids = array_map('basename', glob("$etc/grids/*", GLOB_ONLYDIR) ?: []);
if ($options['grid'] === '' && count($grids) === 1) {
    $options['grid'] = $grids[0];
}
$grid = $options['grid'] === '' ? null : GridInfo::load($profile, $options['grid']);
if ($grid === null || $grid->remote || $grid->dbName === '') {
    fail(
        $options['grid'] === ''
            ? 'choose the grid with --grid (' . implode(', ', $grids) . ')'
            : "no grid {$options['grid']} on this machine, with a database to write to",
        2,
    );
}

$list = AccountList::parse((string) file_get_contents($words[0]), $options['format'] ?: null);
foreach ($list['errors'] as $error) {
    fwrite(STDERR, "users: $error\n");
}
if ($list['accounts'] === []) {
    fail('nothing to make' . ($list['errors'] === [] ? '' : ': every line has a problem'), 2);
}

$done = AccountImporter::forDatabase(new Database(new QuietUi()))->run(
    $grid,
    $list['accounts'],
    $flags['apply'],
    $flags['keep-going'],
);
if ($done['error'] !== null) {
    fail($done['error']);
}

$counts = [];
foreach ($done['results'] as $row) {
    $counts[$row['status']] = ($counts[$row['status']] ?? 0) + 1;
    printf("%-14s %s %s%s\n", $row['status'], $row['first'], $row['last'], $row['detail'] !== '' ? " ({$row['detail']})" : '');
}
echo "\n" . implode(', ', array_map(static fn(string $s, int $n): string => "$n $s", array_keys($counts), $counts)) . "\n";
if (!$flags['apply']) {
    echo "Nothing was written: --apply makes the accounts.\n";
}

$generated = array_filter($done['results'], static fn(array $row): bool => $row['generated'] && $row['status'] !== AccountImporter::EXISTS);
$resultFile = $options['result'] !== '' ? $options['result'] : ($flags['apply'] ? 'users-result-' . date('Ymd-His') . '.csv' : '');
if ($resultFile !== '' && ($flags['apply'] || $generated !== [])) {
    $umask = umask(0o077);
    file_put_contents($resultFile, AccountImporter::resultCsv($done['results']));
    umask($umask);
    chmod($resultFile, 0o600);
    echo "Result (with the passwords made here): $resultFile\n";
}

$failed = ($counts[AccountImporter::FAILED] ?? 0) + ($counts[AccountImporter::NOT_TRIED] ?? 0);
exit($failed > 0 || $list['errors'] !== [] ? 1 : 0);
