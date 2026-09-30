<?php

declare(strict_types=1);

namespace OpenSim\Installer\Grid;

use OpenSim\Installer\System;
use OpenSim\Installer\Ui\InstallerUi;

/**
 * The database of a grid: OpenSim cannot run without it, so the setup checks
 * it can be used with the given credentials, and only goes on when it can.
 *
 * In this order, and only as far as needed:
 *  1. the account: does it log in with these credentials? If it does, nothing
 *     is created for it.
 *  2. the database: can this account use it?
 *  3. what is missing (the account, the database, the rights on it) is
 *     created one statement at a time after confirmation, with the
 *     administrator access to the server when there is one.
 *
 * A problem of access (wrong password, unreachable server, no administrator
 * access to check or fix) can be tried again. A creation that fails ends the
 * setup, with the commands to run from an administrator account.
 *
 * The administrator access is what the running user already has: root, or
 * their own database account, or sudo without a password. Nothing asks for
 * more, so someone with only a database they may use gets there without it.
 *
 * MySQL and MariaDB only: Robust, and many modules, do not support others.
 */
final class Database
{
    /** The database is usable. */
    public const OK = 0;
    /** An access problem: the settings can be corrected and tried again. */
    public const RETRY = 1;
    /** A creation failed: nothing more to try from here. */
    public const ABORT = 2;

    /** @var array<string,string> passwords entered or generated during this session, by account */
    private static array $passwords = [];

    /** @var list<string>|false|null the command giving administrator access: null until searched, false when none */
    private array|false|null $adminCommand = null;

    /** @var string why there is no administrator access */
    private string $adminReason = '';

    public function __construct(private InstallerUi $ui)
    {
    }

    /** Keep the password of an account for the rest of the session. */
    public static function remember(string $host, string $user, string $password): void
    {
        self::$passwords["$user@$host"] = $password;
    }

    /** The password entered or generated earlier in this session for this account, if any. */
    public static function recall(string $host, string $user): ?string
    {
        return self::$passwords["$user@$host"] ?? null;
    }

    /** @return int self::OK, self::RETRY or self::ABORT (with the reason and the way out shown) */
    public function ensure(GridPlan $plan): int
    {
        $client = $this->client();
        if ($client === null) {
            $this->ui->error('The MySQL client is missing: install default-mysql-client (or mariadb-client).');

            return self::ABORT;
        }
        if (!preg_match('/^[A-Za-z0-9_$]+$/', $plan->dbName)) {
            $this->ui->error("Invalid database name '{$plan->dbName}': letters, digits, _ and $ only.");

            return self::RETRY;
        }

        // 1. The account, before any database: valid credentials need no creation.
        [$code, , $err] = $this->run($client, $plan, null);
        if ($code !== 0) {
            // Without a database, any "Access denied for user" is a refused
            // login: 1045 for a wrong password, 1698 for an account that does
            // not exist (MariaDB), or that has another authentication
            if (!in_array($this->errno($err), [1045, 1698], true) && stripos($err, 'Access denied for user') === false) {
                $this->ui->error("Cannot reach the database server {$plan->dbHost}: " . trim($err));
                $this->ui->error('OpenSim needs MySQL or MariaDB, on this machine (install default-mysql-server) or another one.');

                return self::RETRY;
            }
            $result = $this->account($client, $plan);
            if ($result !== self::OK) {
                return $result;
            }
        }

        // 2. The database, with this account.
        [$code, , $err] = $this->run($client, $plan, $plan->dbName);
        if ($code === 0) {
            $this->ui->note("Database {$plan->dbName} on {$plan->dbHost}: connection OK.");

            return self::OK;
        }
        // 1049 unknown database; 1044 what a server answers, for an account it
        // knows, about a database the account has no rights on, existing or not
        if (!in_array($this->errno($err), [1044, 1049], true)) {
            $this->ui->error("Cannot use database {$plan->dbName}: " . trim($err));

            return self::RETRY;
        }

        return $this->database($client, $plan);
    }

    /** Login refused: wrong password, or no such account for this host. */
    private function account(string $client, GridPlan $plan): int
    {
        $host = $this->accountHost($plan);
        $hosts = $this->accountHosts($client, $plan);

        if ($hosts === null) {
            $this->ui->error("Login refused for user {$plan->dbUser}: the password is wrong, or the account does not exist for '$host'. "
                . "The administrator access to the server, to check, is not available ({$this->adminReason}).");
            $this->showCommands($plan, $host, true, true);

            return self::RETRY;
        }
        if (in_array($host, $hosts, true)) {
            $this->ui->error("User '{$plan->dbUser}'@'$host' exists but the password is rejected: give the right password.");

            return self::RETRY;
        }

        $this->ui->warn($hosts === []
            ? "User {$plan->dbUser} does not exist on {$plan->dbHost}."
            : "User {$plan->dbUser} exists for " . implode(', ', $hosts) . ", not for '$host' (the host it connects from).");
        if (!$this->ui->confirm("Create user '{$plan->dbUser}'@'$host' on {$plan->dbHost}?", true)) {
            return self::RETRY;
        }

        $account = $this->quote($plan->dbUser) . '@' . $this->quote($host);
        [$code, , $err] = $this->admin($client, $plan, "CREATE USER $account IDENTIFIED BY " . $this->quote($plan->dbPass));
        if ($code !== 0) {
            return $this->failed('create the user', $err, $plan, $host, true, true);
        }
        [$code, , $err] = $this->run($client, $plan, null);
        if ($code !== 0) {
            $this->ui->error('The user was created but still cannot log in: ' . trim($err));

            return self::ABORT;
        }

        return self::OK;
    }

    /** The account works, the database does not: missing, or without rights. */
    private function database(string $client, GridPlan $plan): int
    {
        $host = $this->accountHost($plan);
        $this->ui->warn("User {$plan->dbUser} can connect to {$plan->dbHost}, but has no access to database {$plan->dbName}: it does not exist, or the user has no rights on it.");

        $exists = $this->databaseExists($client, $plan);
        if ($exists === null) {
            $this->ui->error("The administrator access to the server, to check or create it, is not available ({$this->adminReason}).");
            $this->showCommands($plan, $host, false, true);

            return self::RETRY;
        }

        $question = $exists
            ? "Give user {$plan->dbUser} access to database {$plan->dbName} on {$plan->dbHost}?"
            : "Create database {$plan->dbName} on {$plan->dbHost} and give user {$plan->dbUser} access to it?";
        if (!$this->ui->confirm($question, true)) {
            return self::RETRY;
        }

        if (!$exists) {
            [$code, , $err] = $this->admin($client, $plan, "CREATE DATABASE `{$plan->dbName}` CHARACTER SET utf8");
            if ($code !== 0) {
                return $this->failed('create the database', $err, $plan, $host, false, true);
            }
        }
        $account = $this->quote($plan->dbUser) . '@' . $this->quote($host);
        [$code, , $err] = $this->admin($client, $plan, "GRANT ALL ON `{$plan->dbName}`.* TO $account");
        if ($code !== 0) {
            return $this->failed('give the user access to the database', $err, $plan, $host, false, !$exists);
        }

        [$code, , $err] = $this->run($client, $plan, $plan->dbName);
        if ($code !== 0) {
            $this->ui->error("Still cannot use database {$plan->dbName}: " . trim($err));

            return self::ABORT;
        }
        $this->ui->note("Database {$plan->dbName} ready on {$plan->dbHost}.");

        return self::OK;
    }

    /** A creation failed: the setup ends here, with what to run from an administrator account. */
    private function failed(string $what, string $err, GridPlan $plan, string $host, bool $withUser, bool $withDatabase): int
    {
        // The client prints the failed statement, password included, before
        // its "ERROR <number>" line: only that line is shown
        $reason = preg_match('/^ERROR .*$/m', $err, $m) ? $m[0] : (trim($err) !== '' && !str_contains($err, 'IDENTIFIED') ? trim($err) : 'failed.');
        $this->ui->error("Could not $what: $reason");
        $this->showCommands($plan, $host, $withUser, $withDatabase);

        return self::ABORT;
    }

    /** The commands the administrator can run by hand, then the setup started again. */
    private function showCommands(GridPlan $plan, string $host, bool $withUser, bool $withDatabase): void
    {
        $account = $this->quote($plan->dbUser) . '@' . $this->quote($host);
        $lines = [];
        if ($withUser) {
            $lines[] = "CREATE USER $account IDENTIFIED BY '<password>';";
        }
        if ($withDatabase) {
            $lines[] = "CREATE DATABASE `{$plan->dbName}` CHARACTER SET utf8;";
        }
        $lines[] = "GRANT ALL ON `{$plan->dbName}`.* TO $account;";
        $this->ui->note("From an account with administrator rights on the database server, run what is missing:\n  " . implode("\n  ", $lines));
    }

    /**
     * The hosts the account exists for, or null when it cannot be told (no
     * administrator access).
     *
     * @return list<string>|null
     */
    private function accountHosts(string $client, GridPlan $plan): ?array
    {
        [$code, $out] = $this->admin($client, $plan, 'SELECT Host FROM mysql.user WHERE User=' . $this->quote($plan->dbUser));

        return $code === 0 ? array_values(array_filter(array_map('trim', explode("\n", $out)))) : null;
    }

    /** Whether the database exists, or null when it cannot be told. */
    private function databaseExists(string $client, GridPlan $plan): ?bool
    {
        [$code, $out] = $this->admin($client, $plan, 'SELECT SCHEMA_NAME FROM information_schema.SCHEMATA WHERE SCHEMA_NAME=' . $this->quote($plan->dbName));

        return $code === 0 ? trim($out) !== '' : null;
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

    /** The client prints "ERROR <number> (<state>): <message>". */
    private function errno(string $err): int
    {
        return preg_match('/ERROR (\d+)/', $err, $m) ? (int) $m[1] : 0;
    }

    private function quote(string $value): string
    {
        return "'" . strtr($value, ['\\' => '\\\\', "'" => "\\'"]) . "'";
    }

    /**
     * A statement as the grid's account, with or without a database (the
     * password goes through the environment, not the command line).
     *
     * @return array{0:int,1:string,2:string} [exit code, stdout, stderr]
     */
    private function run(string $client, GridPlan $plan, ?string $database, string $sql = 'SELECT 1'): array
    {
        $command = [$client, '-h', $plan->dbHost, '-u', $plan->dbUser, '--connect-timeout=5', '-BN', '-e', $sql];
        if ($database !== null) {
            $command[] = $database;
        }

        return $this->exec($command, ['MYSQL_PWD' => $plan->dbPass]);
    }

    /**
     * A statement as the administrator of a local server, with what the
     * running user already has, in this order: root through the socket, their
     * own database account, sudo without a password. Never a prompt.
     *
     * @return array{0:int,1:string,2:string}
     */
    private function admin(string $client, GridPlan $plan, string $sql): array
    {
        if (!$this->isLocal($plan->dbHost)) {
            $this->adminReason = 'the server is not on this machine';

            return [1, '', $this->adminReason];
        }

        if ($this->adminCommand === null) {
            $this->adminCommand = false;
            $this->adminReason = 'not root, no database account of your own with administrator rights, no sudo without a password';
            $candidates = [[$client]];
            if (System::commandExists('sudo')) {
                $candidates[] = ['sudo', '-n', $client];
            }
            foreach ($candidates as $candidate) {
                [$code] = $this->exec(array_merge($candidate, ['-BN', '-e', 'SELECT COUNT(*) FROM mysql.user']), []);
                if ($code === 0) {
                    $this->adminCommand = $candidate;
                    break;
                }
            }
        }
        if ($this->adminCommand === false) {
            return [1, '', $this->adminReason];
        }

        return $this->exec(array_merge($this->adminCommand, ['-BN', '-e', $sql]), []);
    }

    /** @return array{0:int,1:string,2:string} */
    private function exec(array $command, array $env): array
    {
        $descriptors = [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
        $process = @proc_open($command, $descriptors, $pipes, null, $env === [] ? null : array_merge(getenv(), $env));
        if (!is_resource($process)) {
            return [1, '', 'could not run ' . $command[0]];
        }
        fclose($pipes[0]);
        $out = (string) stream_get_contents($pipes[1]);
        $err = (string) stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);

        return [proc_close($process), $out, $err];
    }
}
