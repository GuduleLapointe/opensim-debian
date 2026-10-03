<?php

declare(strict_types=1);

namespace OpenSim\Installer\Ui\Prompts;

use Laravel\Prompts\MultiSelectPrompt as Base;

/** The MultiSelect prompt of Laravel Prompts, with the shortcuts of the setup (see Shortcuts). */
final class MultiSelectPrompt extends Base
{
    use Shortcuts;
}
