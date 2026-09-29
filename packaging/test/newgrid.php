<?php

// Runs the grid wizard of the installed kit with scripted answers, for
// packaging/test/scenario.sh.

require '/usr/share/opensim-tools/vendor/autoload.php';

use OpenSim\Installer\Grid\NewGrid;
use OpenSim\Installer\Ui\InstallerUi;

final class ScriptedUi implements InstallerUi
{
    /** @param array<string,string|bool> $answers label substring => answer */
    public function __construct(private array $answers) {}

    private function answer(string $label): string|bool|null
    {
        foreach ($this->answers as $match => $answer) {
            if (str_contains($label, $match)) {
                return $answer;
            }
        }

        return null;
    }

    public function intro(string $title): void { echo "== $title\n"; }
    public function note(string $message): void { echo "note: $message\n"; }
    public function warn(string $message): void { echo "warn: $message\n"; }
    public function error(string $message): void { echo "error: $message\n"; }
    public function outro(string $message): void { echo "$message\n"; }

    public function choose(string $label, array $options, ?string $default = null, ?string $hint = null): string
    {
        $choice = $this->answer($label) ?? $default ?? array_key_first($options);
        echo "choose: $label -> $choice\n";

        return (string) $choice;
    }

    public function text(string $label, string $default = '', ?\Closure $validate = null, ?string $hint = null): string
    {
        $value = (string) ($this->answer($label) ?? $default);
        echo "text: $label -> $value\n";

        return $value;
    }

    public function confirm(string $label, bool $default = true): bool
    {
        $value = $this->answer($label) ?? $default;
        echo "confirm: $label -> " . ($value ? 'yes' : 'no') . "\n";

        return (bool) $value;
    }

    public function spin(\Closure $callback, string $message): mixed
    {
        return $callback();
    }
}

(new NewGrid(new ScriptedUi([
    'Grid name' => 'Testgrid',
    'Grid nick' => 'testgrid',
    'Base hostname' => 'localhost',
    'Database password' => 'testpass',
    'Start grid' => false,
])))->run(null);
