<?php

declare(strict_types=1);

namespace OpenSim\Installer;

use OpenSim\Installer\Ui\InstallerUi;

/**
 * Discriminating runtime gate: decides whether the chosen OpenSim version can
 * run (mono for < 0.9.3.0, .NET for >= 0.9.3.0) and records the decision in the
 * plan. Aborts the wizard if no usable runtime and the user declines to install.
 *
 * The exact .NET version is re-verified from the binaries at launch
 * (bin/opensim); here we map version -> runtime before anything is downloaded.
 */
final class Runtime
{
    private const DOTNET_THRESHOLD = '0.9.3.0';
    private const DOTNET_MAJOR = 8; // OpenSim 0.9.3.x targets .NET 8 (net8.0)

    public function __construct(
        private InstallerUi $ui,
    ) {}

    public function gate(Plan $plan): void
    {
        if (version_compare($plan->version, self::DOTNET_THRESHOLD, '>=')) {
            $this->gateDotnet($plan);
        } else {
            $this->gateMono($plan);
        }
    }

    private function gateDotnet(Plan $plan): void
    {
        $plan->runtime = 'dotnet';
        $plan->dotnetMajor = self::DOTNET_MAJOR;
        $major = self::DOTNET_MAJOR;
        $installed = $this->dotnetMajors();

        if (in_array($major, $installed, true)) {
            $this->ui->note(".NET $major is installed; OpenSim {$plan->version} will use it.");

            return;
        }

        $newer = array_filter($installed, static fn(int $m): bool => $m > $major);
        if ($newer !== []) {
            $choice = $this->ui->choose(
                "OpenSim {$plan->version} targets .NET $major. Installed: " . implode(', ', $installed),
                [
                    'rollforward' => 'Use roll-forward on .NET ' . max($newer) . ' (no additional install)',
                    'install' => "Install native .NET $major",
                ],
                'rollforward',
            );
            $choice === 'install' ? ($plan->installRuntime = true) : ($plan->rollForward = true);

            return;
        }

        $msg = $installed !== []
            ? 'Installed .NET (' . implode(', ', $installed) . ") is older than $major. "
            : 'No .NET runtime is installed. ';
        if ($this->ui->confirm($msg . "OpenSim {$plan->version} needs .NET $major. Install it now?", true)) {
            $plan->installRuntime = true;
        } else {
            $this->ui->error("OpenSim {$plan->version} cannot run without .NET $major.");
            exit(1);
        }
    }

    private function gateMono(Plan $plan): void
    {
        $plan->runtime = 'mono';

        if (System::commandExists('mono')) {
            $this->ui->note("Mono is installed; OpenSim {$plan->version} will use it.");

            return;
        }

        if ($this->ui->confirm("OpenSim {$plan->version} needs Mono. Install it now?", true)) {
            $plan->installRuntime = true;
        } else {
            $this->ui->error("OpenSim {$plan->version} cannot run without Mono.");
            exit(1);
        }
    }

    /** Install the runtime when the plan requires it (apply phase). */
    public function install(Plan $plan): void
    {
        if (!$plan->installRuntime) {
            return;
        }

        if ($plan->runtime === 'dotnet') {
            $this->installDotnet((int) $plan->dotnetMajor);
        } else {
            $this->installMono();
        }
    }

    private function installDotnet(int $major): void
    {
        // Install into the active dotnet root so the existing 'dotnet' finds it.
        [$code, $path] = System::capture('command -v dotnet');
        $dir = ($code === 0 && trim($path) !== '')
            ? dirname(realpath(trim($path)) ?: trim($path))
            : (getenv('HOME') ?: '') . '/.dotnet';
        $sudo = is_writable($dir) ? '' : 'sudo ';

        $script = sys_get_temp_dir() . '/dotnet-install.sh';
        if (System::run('curl -fsSL https://builds.dotnet.microsoft.com/dotnet/scripts/v1/dotnet-install.sh -o ' . System::arg($script)) !== 0) {
            $this->ui->error('Could not fetch dotnet-install.sh.');
            exit(1);
        }

        $this->ui->note("Installing .NET $major into $dir");
        $code = System::run($sudo . 'bash ' . System::arg($script)
            . ' --runtime dotnet --channel ' . System::arg("$major.0")
            . ' --install-dir ' . System::arg($dir));
        if ($code !== 0) {
            $this->ui->error(".NET $major installation failed.");
            exit(1);
        }
    }

    private function installMono(): void
    {
        $package = PHP_OS_FAMILY === 'Darwin' ? 'mono' : 'mono-complete';
        if ((new Packages($this->ui))->install($package) !== 0) {
            $this->ui->error('Mono installation failed.');
            exit(1);
        }
    }

    /** @return list<int> installed Microsoft.NETCore.App major versions, ascending */
    private function dotnetMajors(): array
    {
        if (!System::commandExists('dotnet')) {
            return [];
        }

        [, $out] = System::capture('dotnet --list-runtimes');
        preg_match_all('/Microsoft\.NETCore\.App (\d+)\./', $out, $m);
        $majors = array_values(array_unique(array_map('intval', $m[1])));
        sort($majors);

        return $majors;
    }
}
