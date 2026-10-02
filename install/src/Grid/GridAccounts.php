<?php

declare(strict_types=1);

namespace OpenSim\Installer\Grid;

use OpenSim\Installer\Console;
use OpenSim\Installer\System;
use OpenSim\Installer\Ui\InstallerUi;

/**
 * The user accounts of a grid, kept by its Robust: read from its database,
 * created through its console (Robust makes the account, its inventory and its
 * password, which a row in the database would not).
 */
final class GridAccounts
{
    public function __construct(private Database $database, private InstallerUi $ui) {}

    /** What a name of a person is made of: first and last name, as OpenSimulator has it. */
    public static function validName(string $name): bool
    {
        return preg_match('/^[A-Za-z0-9._-]+ [A-Za-z0-9._-]+$/', $name) === 1;
    }

    /**
     * The accounts of people ("First Last"), oldest first, or null when the
     * database of the grid cannot be read.
     *
     * @return list<string>|null
     */
    public function names(GridInfo $grid): ?array
    {
        return $this->database->select(
            $grid->databasePlan(),
            "SELECT CONCAT(FirstName, ' ', LastName) FROM UserAccounts WHERE NOT (FirstName = 'GRID' AND LastName = 'SERVICES') ORDER BY Created",
        );
    }

    /** Whether an account exists, or null when it cannot be told. */
    public function exists(GridInfo $grid, string $name): ?bool
    {
        if (!self::validName($name)) {
            return false;
        }
        [$first, $last] = explode(' ', $name);
        $rows = $this->database->select(
            $grid->databasePlan(),
            "SELECT 1 FROM UserAccounts WHERE FirstName = '$first' AND LastName = '$last'",
        );

        return $rows === null ? null : $rows !== [];
    }

    /**
     * Create an account, through the console of the grid's Robust, which is
     * started when it is not running. Runs as the user of the instances.
     */
    public function create(GridInfo $grid, string $name, string $password, string $email): bool
    {
        $opensim = dirname(__DIR__, 3) . '/bin/opensim';
        $instance = $grid->robustInstance();
        // The answers of the prompts that follow: a random ID, the default model
        // The console cuts a line at the spaces, except between double quotes
        $typed = preg_match('/\s/', $password) ? '"' . $password . '"' : $password;
        $lines = "create user $name $typed $email\n\n\n";

        if (!Console::send($instance, $lines)) {
            $this->ui->note("Starting the grid '{$grid->nick}' to create the account.");
            [$code] = System::runShown(System::arg($opensim) . ' start ' . System::arg($grid->nick));
            if ($code !== 0 || !Console::send($instance, $lines)) {
                $this->ui->error("Could not reach the console of the grid '{$grid->nick}' to create the account.");

                return false;
            }
        }

        // The console answers nothing to tell: look for the account
        for ($i = 0; $i < 20; $i++) {
            if ($this->exists($grid, $name) === true) {
                return true;
            }
            sleep(1);
        }
        $this->ui->error("The account $name was not created: see the console of the grid.");

        return false;
    }
}
