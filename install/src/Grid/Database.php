<?php

declare(strict_types=1);

namespace OpenSim\Installer\Grid;

use OpenSim\Installer\System;
use OpenSim\Installer\Ui\InstallerUi;

/**
 * The database of a grid: OpenSim cannot run without it, so the setup checks
 * it can be reached with the given credentials, and stops when it cannot.
 *
 * What is missing (database, account, or the account for this host) is
 * created after confirmation, through the administrator access of the
 * server: as root the local socket, otherwise sudo. A remote server is only
 * checked: the commands to run there are shown.
 *
 * MySQL and MariaDB only: Robust, and many modules, do not support others.
 */
final class Database
{
    public function __construct(private InstallerUi $ui)
    {
    }

    /** True when the database can be used, false (with the reason shown) when the setup must stop. */
    public function ensure(GridPlan $plan): bool
    {
        $client = $this->client();
        if ($client === null) {
            $this->ui->error('The MySQL client is missing: install default-mysql-client (or mariadb-client).');

            return false;
        }
        if (!preg_match('/^[A-Za-z0-9_$]+$/', $plan->dbName)) {
            $this->ui->error("Invalid database name '{$plan->dbName}': letters, digits, _ and $ only.");

            return false;
        }

        [$code, , $err] = $this->run($client, $plan, $plan->dbName);
        if ($code === 0) {
            $this->ui->note("Database {$plan->dbName} on {$plan->dbHost}: connection OK.");

            return true;
        }

        if (stripos($err, 'Unknown database') !== false) {
            // The account works: only the database is missing.
            $this->ui->warn("User {$plan->dbUser} can connect, but database {$plan->dbName} does not exist.");

            return $this->create($client, $plan, false);
        }
        if (stripos($err, 'Access denied') !== false) {
            return $this->repairAccess($client, $plan);
        }

        $this->ui->error("Cannot reach the database server {$plan->dbHost}: " . trim($err));
        $this->ui->error('OpenSim needs MySQL or MariaDB, on this machine (install default-mysql-server) or another one.');

        return false;
    }

    /** Access denied: no such account, wrong password, or an account for another host. */
    private function repairAccess(string $client, GridPlan $plan): bool
    {
        $host = $this->accountHost($plan);
        [$code, $out] = $this->admin($client, $plan, "SELECT Host FROM mysql.user WHERE User=" . $this->quote($plan->dbUser));
        if ($code !== 0) {
            $this->ui->error("Access denied for user {$plan->dbUser}, and the administrator access to the server is not available to check why.");
            $this->showCommands($plan, $host);

            return false;
        }

        $hosts = array_filter(array_map('trim', explode("\n", $out)));
        if (in_array($host, $hosts, true)) {
            $this->ui->error("User '{$plan->dbUser}'@'$host' exists but the password is rejected: fix the password, then retry.");

            return false;
        }

        $this->ui->warn($hosts === []
            ? "User {$plan->dbUser} does not exist on {$plan->dbHost}."
            : "User {$plan->dbUser} exists for " . implode(', ', $hosts) . ", not for '$host' (the host it connects from).");

        return $this->create($client, $plan, true);
    }

    /** Create the account (when $withUser) and the database, after confirmation. */
    private function create(string $client, GridPlan $plan, bool $withUser): bool
    {
        $host = $this->accountHost($plan);
        $what = ($withUser ? "user '{$plan->dbUser}'@'$host' and " : '') . "database {$plan->dbName}";
        if (!$this->ui->confirm("Create $what on {$plan->dbHost}?", true)) {
            $this->ui->error('OpenSim cannot run without its database.');

            return false;
        }

        $sql = [];
        if ($withUser) {
            $sql[] = 'CREATE USER IF NOT EXISTS ' . $this->quote($plan->dbUser) . '@' . $this->quote($host)
                . ' IDENTIFIED BY ' . $this->quote($plan->dbPass);
        }
        $sql[] = "CREATE DATABASE IF NOT EXISTS `{$plan->dbName}` CHARACTER SET utf8";
        $sql[] = "GRANT ALL ON `{$plan->dbName}`.* TO " . $this->quote($plan->dbUser) . '@' . $this->quote($host);

        [$code, , $err] = $this->admin($client, $plan, implode('; ', $sql));
        if ($code !== 0) {
            $this->ui->error('Could not create it: ' . trim($err !== '' ? $err : 'no administrator access to the server.'));
            $this->showCommands($plan, $host);

            return false;
        }

        [$code, , $err] = $this->run($client, $plan, $plan->dbName);
        if ($code !== 0) {
            $this->ui->error("Still cannot connect to {$plan->dbName}: " . trim($err));

            return false;
        }
        $this->ui->note("Database {$plan->dbName} ready on {$plan->dbHost}.");

        return true;
    }

    /** The commands the administrator can run by hand. */
    private function showCommands(GridPlan $plan, string $host): void
    {
        $this->ui->note("As the database administrator, run:\n"
            . '  CREATE USER IF NOT EXISTS ' . $this->quote($plan->dbUser) . '@' . $this->quote($host)
            . " IDENTIFIED BY '<password>';\n"
            . "  CREATE DATABASE IF NOT EXISTS `{$plan->dbName}` CHARACTER SET utf8;\n"
            . "  GRANT ALL ON `{$plan->dbName}`.* TO " . $this->quote($plan->dbUser) . '@' . $this->quote($host) . ';');
    }

    /** The host part of the account: the server sees local connections as coming from localhost. */
    private function accountHost(GridPlan $plan): string
    {
        return $this->isLocal($plan->dbHost) ? 'localhost' : '%';
    }

    private function isLocal(string $host): bool
    {
        return in_array($host, ['localhost', '127.0.0.1', '::1'], true);
    }

    private function client(): ?string
    {
        foreach (['mysql', 'mariadb'] as $command) {
            if (System::commandExists($command)) {
                return $command;
            }
        }

        return null;
    }

    private function quote(string $value): string
    {
        return "'" . strtr($value, ['\\' => '\\\\', "'" => "\\'"]) . "'";
    }

    /**
     * A statement as the grid's account (the password goes through the
     * environment, not the command line).
     *
     * @return array{0:int,1:string,2:string} [exit code, stdout, stderr]
     */
    private function run(string $client, GridPlan $plan, string $database, string $sql = 'SELECT 1'): array
    {
        return $this->exec(
            [$client, '-h', $plan->dbHost, '-u', $plan->dbUser, '--connect-timeout=5', '-BN', '-e', $sql, $database],
            ['MYSQL_PWD' => $plan->dbPass],
        );
    }

    /**
     * A statement as the administrator of a local server: root through the
     * socket, otherwise sudo (which may ask for its password on a terminal).
     * A remote server has no such access.
     *
     * @return array{0:int,1:string,2:string}
     */
    private function admin(string $client, GridPlan $plan, string $sql): array
    {
        if (!$this->isLocal($plan->dbHost)) {
            return [1, '', 'the server is not on this machine.'];
        }
        $command = [$client, '-BN', '-e', $sql];
        if (function_exists('posix_geteuid') && posix_geteuid() !== 0) {
            $command = array_merge(['sudo'], stream_isatty(STDIN) ? [] : ['-n'], $command);
        }

        return $this->exec($command, [], true);
    }

    /** @return array{0:int,1:string,2:string} */
    private function exec(array $command, array $env, bool $terminal = false): array
    {
        $descriptors = [0 => $terminal ? STDIN : ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
        $process = @proc_open($command, $descriptors, $pipes, null, $env === [] ? null : array_merge(getenv(), $env));
        if (!is_resource($process)) {
            return [1, '', 'could not run ' . $command[0]];
        }
        if (!$terminal) {
            fclose($pipes[0]);
        }
        $out = (string) stream_get_contents($pipes[1]);
        $err = (string) stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);

        return [proc_close($process), $out, $err];
    }
}
