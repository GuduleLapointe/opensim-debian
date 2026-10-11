<?php

/**
 * What every entry point of the kit loads first: the autoload (the kit, the engine and the other libraries), then
 * what must always be there.
 */

declare(strict_types=1);

require __DIR__ . '/vendor/autoload.php';

OpenSim\Installer\I18n::init();
