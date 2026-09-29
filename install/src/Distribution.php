<?php

declare(strict_types=1);

namespace OpenSim\Installer;

use OpenSim\Installer\Ui\InstallerUi;

/**
 * Downloads and extracts the chosen OpenSim distribution tarball.
 */
final class Distribution
{
    public function __construct(private InstallerUi $ui)
    {
    }

    public function fetchAndExtract(Plan $plan): void
    {
        $tarball = $plan->sourcesDirectory . '/' . basename($plan->tarball);

        if (is_file($tarball)) {
            $this->ui->note('Using previous download: ' . $tarball);
        } else {
            $this->ui->note('Downloading ' . $plan->tarball);
            $code = System::run(
                'curl -fSL ' . System::arg($plan->tarball) . ' -o ' . System::arg($tarball)
            );
            if ($code !== 0 || !is_file($tarball)) {
                $this->ui->error('Download failed: ' . $plan->tarball);
                exit(1);
            }
        }

        // The tarball holds a single opensim-<version> folder: its content goes
        // straight into the chosen core directory, whatever its name. As root,
        // tar would keep the numeric owners of the tarball.
        $code = $this->ui->spin(
            static fn (): int => System::run(
                'tar xzf ' . System::arg($tarball) . ' --no-same-owner --strip-components=1 -C ' . System::arg($plan->coreDirectory)
            ),
            'Extracting ' . basename($tarball) . ' …'
        );
        if ($code !== 0) {
            $this->ui->error('Extraction failed.');
            exit(1);
        }

        // On start, OpenSim puts its System.Drawing.Common.dll for Linux and
        // macOS in place when the one there differs: done now, so that a core
        // the instances cannot write to (e.g. owned by root) still starts.
        $dll = "{$plan->coreDirectory}/bin/System.Drawing.Common.dll";
        if (is_file("$dll.linux")) {
            copy("$dll.linux", $dll);
        }
    }
}
