<?php

declare(strict_types=1);

namespace OpenSim\Installer\Ui\Prompts;

use Laravel\Prompts\ConfirmPrompt as Base;

/** The Confirm prompt of Laravel Prompts, with the shortcuts of the setup (see Shortcuts). */
final class ConfirmPrompt extends Base
{
    use Shortcuts;
}
