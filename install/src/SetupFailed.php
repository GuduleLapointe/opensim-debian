<?php

declare(strict_types=1);

namespace OpenSim\Installer;

/**
 * The setup cannot go on: what went wrong is already on screen, the program
 * ends with an error code instead of going back to the menu.
 */
final class SetupFailed extends \RuntimeException
{
}
