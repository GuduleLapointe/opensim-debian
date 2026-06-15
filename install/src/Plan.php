<?php

declare(strict_types=1);

namespace OpenSim\Installer;

/**
 * All choices collected by the wizard. Phase 2 only fills it (no system
 * changes); phase 3 will apply it.
 */
final class Plan
{
    public string $version = '';
    public string $tarball = '';

    // Runtime decision
    public string $runtime = '';          // dotnet | mono
    public bool $rollForward = false;     // run on a newer .NET without installing
    public bool $installRuntime = false;  // install the runtime during apply
    public ?int $dotnetMajor = null;

    // Locations
    public string $layout = '';           // system | bundled | flat
    public string $installPath = '';
    public string $coreRoot = '';
    public string $coreDirectory = '';
    public string $etcRoot = '';
    public string $varRoot = '';
    public string $logsRoot = '';
    public string $cacheRoot = '';
    public string $dataRoot = '';
    public string $sourcesDirectory = '';

    // Profile
    public string $profile = '';          // config section name
    public bool $makeDefault = true;

    /** @return array<string,string> human-readable summary */
    public function summary(): array
    {
        $runtime = $this->runtime;
        if ($this->runtime === 'dotnet') {
            $runtime .= ' (.NET ' . $this->dotnetMajor
                . ($this->installRuntime ? ', will install' : ($this->rollForward ? ', roll-forward' : ', present')) . ')';
        } elseif ($this->installRuntime) {
            $runtime .= ' (will install)';
        }

        return [
            'OpenSim version' => $this->version,
            'Runtime' => $runtime,
            'Layout' => $this->layout,
            'Core directory' => $this->coreDirectory,
            'Etc directory' => $this->etcRoot,
            'Var directory' => $this->varRoot,
            'Logs directory' => $this->logsRoot,
            'Cache directory' => $this->cacheRoot,
            'Data directory' => $this->dataRoot,
            'Sources' => $this->sourcesDirectory,
            'Profile' => $this->profile . ($this->makeDefault ? ' (default)' : ''),
        ];
    }
}
