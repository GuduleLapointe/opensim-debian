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

        $code = $this->ui->spin(
            static fn (): int => System::run(
                'tar xzf ' . System::arg($tarball) . ' -C ' . System::arg($plan->coreRoot)
            ),
            'Extracting ' . basename($tarball) . ' …'
        );
        if ($code !== 0) {
            $this->ui->error('Extraction failed.');
            exit(1);
        }
    }
}
