<?php

// A frontend of the setup that answers from a table, for the scripted tests
// (newgrid.php, newsim.php).

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

    public function secret(string $label, ?\Closure $validate = null, ?string $hint = null): string
    {
        $value = (string) ($this->answer($label) ?? '');
        echo "secret: $label -> " . ($value === '' ? '(empty)' : '***') . "\n";

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
