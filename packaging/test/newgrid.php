<?php

// Runs the grid wizard of the installed kit with scripted answers, for
// packaging/test/scenario.sh.

require '/usr/share/opensim-tools/vendor/autoload.php';

use OpenSim\Installer\Grid\NewGrid;
use OpenSim\Installer\SetupFailed;
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

// The grid and the database password can be given by the environment; the
// retry after a database error is always declined, and the grid is not
// enabled when TEST_NO_ENABLE is set
$name = getenv('TEST_GRID') ?: 'Testgrid';

// TEST_DB_PASSWORD empty: the password proposed is kept; TEST_DB_USER: the account. TEST_REPEAT runs the
// setup several times in the same process, as the setup hub does.
$answers = [
    'Grid name' => $name,
    'Grid nick' => strtolower($name),
    'Base hostname' => 'localhost',
    'Try again' => false,
    'Enable grid' => getenv('TEST_NO_ENABLE') ? false : true,
    'Start grid' => false,
];
$password = getenv('TEST_DB_PASSWORD');
if ($password !== '') {
    $answers['Database password'] = $password ?: 'testpass';
}
if (getenv('TEST_DB_USER')) {
    $answers['Database user'] = getenv('TEST_DB_USER');
}

// Start the grid when TEST_START is set (the setup started as root, as
// in the packaged install)
if (getenv('TEST_START')) {
    $answers['Start grid'] = true;
}

try {
    for ($i = 0; $i < (int) (getenv('TEST_REPEAT') ?: 1); $i++) {
        (new NewGrid(new ScriptedUi($answers)))->run(null);
    }
} catch (SetupFailed) {
    echo "setup failed\n";
    exit(1);
}
