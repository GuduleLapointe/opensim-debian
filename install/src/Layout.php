<?php

declare(strict_types=1);

namespace OpenSim\Installer;

/**
 * Directory layouts and their default locations.
 */
final class Layout
{
    /** @var array<string,string> key => menu label */
    public const LABELS = [
        'system' => 'System — /etc/opensim, /var/lib/opensim, /usr/local/share/opensim',
        'bundled' => 'Bundled — everything under one base directory (e.g. /opt/opensim)',
        'flat' => "Flat — OpenSim's default, all files in the core directory",
    ];

    /** Whether this layout needs a base install path from the user. */
    public static function needsBase(string $layout): bool
    {
        return $layout !== 'system';
    }

    public static function defaultBase(string $layout): string
    {
        return match ($layout) {
            'system' => '/usr/local/share/opensim',
            'bundled' => '/opt/opensim',
            'flat' => dirname(__DIR__, 2) . '/core',
            default => '',
        };
    }

    /** Fill $plan with this layout's default locations (before user edits). */
    public static function applyDefaults(Plan $plan): void
    {
        $v = $plan->version;
        $base = $plan->installPath;

        switch ($plan->layout) {
            case 'system':
                $plan->coreRoot = '/usr/local/share/opensim';
                $plan->coreDirectory = "{$plan->coreRoot}/opensim-$v";
                $plan->etcRoot = '/etc/opensim';
                $plan->varRoot = '/var/lib/opensim';
                $plan->logsRoot = '/var/log/opensim';
                $plan->cacheRoot = '/var/cache/opensim';
                $plan->dataRoot = '/var/lib/opensim/data';
                break;
            case 'bundled':
                $plan->coreRoot = "$base/core";
                $plan->coreDirectory = "{$plan->coreRoot}/opensim-$v";
                $plan->etcRoot = "$base/etc";
                $plan->varRoot = "$base/var";
                $plan->logsRoot = "$base/var/logs";
                $plan->cacheRoot = "$base/var/cache";
                $plan->dataRoot = "$base/var/data";
                break;
            case 'flat':
                $plan->coreRoot = "$base/opensim-$v";
                $plan->coreDirectory = $plan->coreRoot;
                $plan->etcRoot = "{$plan->coreRoot}/bin";
                $plan->varRoot = "{$plan->coreRoot}/bin";
                $plan->logsRoot = "{$plan->coreRoot}/bin";
                $plan->cacheRoot = "{$plan->coreRoot}/bin";
                $plan->dataRoot = "{$plan->coreRoot}/bin";
                break;
        }
    }
}
