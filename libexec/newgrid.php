<?php

declare(strict_types=1);

/**
 * newgrid (PHP) — configure a grid on top of an installed framework.
 *
 * Thin entry point: the flow lives in OpenSim\Installer\Grid\NewGrid so the
 * setup hub can reuse it. Runnable standalone too.
 */

require dirname(__DIR__) . '/vendor/autoload.php';

use OpenSim\Installer\Grid\NewGrid;
use OpenSim\Installer\Ui\PromptsUi;

(new NewGrid(new PromptsUi()))->run();
