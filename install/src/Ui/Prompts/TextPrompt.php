<?php

declare(strict_types=1);

namespace OpenSim\Installer\Ui\Prompts;

use Laravel\Prompts\TextPrompt as Base;

/** The Text prompt of Laravel Prompts, with the shortcuts of the setup (see Shortcuts). */
final class TextPrompt extends Base
{
    use Shortcuts;
}
