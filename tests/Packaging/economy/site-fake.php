<?php
/**
 * The web site of the helpers for the economy tests, as the router of its test server: the settings the test gives
 * (settings-fixture.php) first, then the entry of the site, which the helpers answer. The built-in server does not run
 * auto_prepend_file for a router, so this is the router.
 *
 *   OPENSIM_GRID=testgrid php -S 127.0.0.1:8090 site-fake.php      (from the docroot of the site)
 */

require __DIR__ . '/settings-fixture.php';

return require getcwd() . '/index.php';
