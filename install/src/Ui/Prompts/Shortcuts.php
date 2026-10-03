<?php

declare(strict_types=1);

namespace OpenSim\Installer\Ui\Prompts;

use Laravel\Prompts\Key;
use OpenSim\Installer\Ui\Back;
use OpenSim\Installer\Ui\Quit;

/**
 * The keys of a terminal that Laravel Prompts does not know, for every prompt of the setup:
 * Escape gives up the screen (Back), Ctrl-Q leaves the setup (Quit); in a text, Ctrl-U clears the
 * line and Ctrl-W the word before the cursor.
 *
 * The keys are caught where the prompts read them (runLoop), before Laravel handles them: it takes
 * Ctrl-U for "revert the form", which has no meaning in a prompt of its own.
 */
trait Shortcuts
{
    private const CTRL_Q = "\x11";
    private const CTRL_W = "\x17";

    public function runLoop(callable $callable): mixed
    {
        // The terminal keeps Ctrl-Q for itself (flow control) unless told not to
        static::terminal()->setTty('-ixon');

        return parent::runLoop(function (string $key) use ($callable) {
            if ($key === Key::ESCAPE) {
                $this->leave();

                throw new Back();
            }
            if ($key === self::CTRL_Q) {
                $this->leave();

                throw new Quit();
            }
            if (property_exists($this, 'typedValue') && in_array($key, [Key::CTRL_U, self::CTRL_W], true)) {
                if ($key === Key::CTRL_U) {
                    $this->typedValue = '';
                    $this->cursorPosition = 0;
                } else {
                    $this->deleteWordBackward();
                }
                $this->render();

                return null;
            }

            return $callable($key);
        });
    }

    /** The themes know the prompts of Laravel by their class: this one is drawn as the one it extends. */
    protected function getRenderer(): callable
    {
        $class = (string) get_parent_class($this);

        return new (static::$themes[static::$theme][$class] ?? static::$themes['default'][$class])($this);
    }

    /** Leave the terminal as it was found: the exception goes up through the prompt. */
    private function leave(): void
    {
        $this->showCursor();
        static::terminal()->restoreTty();
        // The line the prompt was drawn on is left as it is, the next screen starts under it
        static::output()->writeln('');
    }
}
