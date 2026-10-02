<?php
/**
 * Placeholder site of a grid: its name, how to connect to it, where its helpers are.
 *
 * Installed by the opensim-web package as the web site of a grid that has none yet; replace it by
 * your own site (the helpers do not need it). The settings are those of the grid, read from the
 * OpenSim kit by the engine: nothing to edit here. The grid is the one named by OPENSIM_GRID (set by
 * the virtual host), else the only one of the machine.
 */

declare(strict_types=1);

$helpersRoot = getenv('OPENSIM_HELPERS_ROOT') ?: '/usr/share/opensim-helpers';
$settings = null;
if (is_file("$helpersRoot/vendor/autoload.php")) {
    define('OPENSIM_ENGINE', true);
    require_once "$helpersRoot/vendor/autoload.php";
    $settings = OpenSim_Kit::settings();
}

/** Escape for HTML. */
function e(?string $text): string
{
    return htmlspecialchars((string) $text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

$name = $settings['grid_name'] ?? 'OpenSimulator grid';
$login = $settings['login_uri'] ?? null;
$services = [];
if ($settings !== null) {
    foreach (['search', 'currency', 'offline', 'guide'] as $service) {
        $services[$service] = rtrim($settings['web_url'], '/') . OpenSim_Kit::script_path($settings, $service);
    }
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($name) ?></title>
<style>
:root { --bg: #f7f7f5; --fg: #1c1c1a; --muted: #66665f; --accent: #3a5ea8; --card: #fff; --line: #deded8; }
@media (prefers-color-scheme: dark) {
    :root { --bg: #151514; --fg: #ececea; --muted: #9a9a92; --accent: #8fb0f0; --card: #1f1f1d; --line: #34342f; }
}
body { margin: 0; background: var(--bg); color: var(--fg); font: 16px/1.5 system-ui, sans-serif; }
main { max-width: 42rem; margin: 0 auto; padding: 3rem 1rem; }
h1 { font-size: 2rem; margin: 0 0 .25rem; }
p.lead { color: var(--muted); margin-top: 0; }
section { background: var(--card); border: 1px solid var(--line); border-radius: .5rem; padding: 1rem 1.25rem; margin: 1.25rem 0; }
h2 { font-size: 1.05rem; margin: 0 0 .5rem; }
code { background: var(--bg); padding: .1rem .35rem; border-radius: .25rem; word-break: break-all; }
a { color: var(--accent); }
dl { margin: 0; display: grid; grid-template-columns: max-content 1fr; gap: .25rem 1rem; }
dt { color: var(--muted); }
dd { margin: 0; word-break: break-all; }
footer { color: var(--muted); font-size: .85rem; }
</style>
</head>
<body>
<main>
    <h1><?= e($name) ?></h1>
    <p class="lead">A virtual world grid, run with OpenSimulator.</p>
<?php if ($login !== null) { ?>
    <section>
        <h2>Connect</h2>
        <p>In your viewer, add this grid with its login address:</p>
        <p><code><?= e($login) ?></code></p>
        <p><a href="<?= e($login) ?>/get_grid_info">Grid information</a></p>
<?php if (!empty($settings['hypergrid'])) { ?>
        <p>From another grid (Hypergrid), teleport to <code><?= e(preg_replace('#^https?://#', '', $login)) ?>:Welcome</code>.</p>
<?php } ?>
    </section>
    <section>
        <h2>Services</h2>
        <dl>
<?php foreach ($services as $service => $url) { ?>
            <dt><?= e($service) ?></dt><dd><?= e($url) ?></dd>
<?php } ?>
        </dl>
    </section>
<?php } else { ?>
    <section>
        <h2>Not configured yet</h2>
        <p>This site shows the grid once it is set up with <code>opensim setup</code>.</p>
    </section>
<?php } ?>
    <footer>Placeholder page of the OpenSim kit: replace it by the site of your grid.</footer>
</main>
</body>
</html>
