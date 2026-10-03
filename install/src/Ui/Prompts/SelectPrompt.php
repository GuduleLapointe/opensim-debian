<?php

declare(strict_types=1);

namespace OpenSim\Installer\Ui\Prompts;

use Laravel\Prompts\SelectPrompt as Base;

/** The Select prompt of Laravel Prompts, with the shortcuts of the setup (see Shortcuts). */
final class SelectPrompt extends Base
{
    use Shortcuts;
}
