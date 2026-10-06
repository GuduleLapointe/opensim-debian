<?php
/**
 * A fake HYPEvents server for the tests of the helpers (tests/Packaging/pages-scenario.sh): events.json, the events a calendar of
 * a grid publishes, from the file the test writes (/tmp/hypevents/events.json).
 *
 *   php -S 127.0.0.1:8098 hypevents-fake.php
 */

if (parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) === '/events.json') {
    header('Content-Type: application/json');
    readfile('/tmp/hypevents/events.json');

    return;
}
http_response_code(404);
