<?php


namespace OpenSim\Installer\Ui\Prompts;

use Laravel\Prompts\PasswordPrompt as Base;

/** The Password prompt of Laravel Prompts, with the shortcuts of the setup (see Shortcuts). */
final class PasswordPrompt extends Base
{
    use Shortcuts;
}
