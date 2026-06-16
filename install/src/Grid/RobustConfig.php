<?php

declare(strict_types=1);

namespace OpenSim\Installer\Grid;

use OpenSim\Installer\Ini;

/**
 * Generates a grid's Robust.HG.ini by overlaying our settings onto the
 * distribution's Robust(.HG).ini.example, preserving its comments and defaults.
 *
 * No [Launch] section: that was a pre-Const artifact. Everything lives in the
 * standard [Const]/[Startup]/… sections; the launcher reads BinDirectory from
 * [Const] and derives the rest.
 */
final class RobustConfig
{
    public function example(GridPlan $plan): string
    {
        return $plan->binDir . '/' . ($plan->enableHypergrid ? 'Robust.HG.ini.example' : 'Robust.ini.example');
    }

    public function generate(GridPlan $plan): string
    {
        $ini = Ini::load($this->example($plan));

        $ini->merge([
            'Const' => [
                'BaseHostname' => $this->q($plan->baseHostname),
                'BaseURL' => $this->q("http://{$plan->baseHostname}"),
                'WebURL' => $this->q($plan->webUrl),
                'PublicPort' => (string) $plan->publicPort,
                'PrivatePort' => (string) $plan->privatePort,
                'BinDirectory' => $this->q($plan->binDir),
                'EtcDirectory' => $this->q($plan->etcDirectory),
                'DataDirectory' => $this->q($plan->dataDirectory),
                'CacheDirectory' => $this->q($plan->cacheDirectory),
                'LogsDirectory' => $this->q($plan->logsDirectory),
            ],
            'Startup' => [
                'PIDFile' => $this->q('${Const|CacheDirectory}/' . $plan->gridNick . '.pid'),
                'RegistryLocation' => $this->q('${Const|DataDirectory}/registry'),
                'ConfigDirectory' => $this->q('${Const|EtcDirectory}/robust-include'),
                'ConsoleHistoryFile' => $this->q('${Const|LogsDirectory}/' . $plan->gridNick . '.RobustConsoleHistory.txt'),
            ],
            'DatabaseService' => [
                'ConnectionString' => $this->q($plan->connectionString()),
            ],
            'AssetService' => [
                'BaseDirectory' => $this->q('${Const|DataDirectory}/fsassets/data'),
                'SpoolDirectory' => $this->q('${Const|CacheDirectory}/fsassets/tmp'),
                'AssetLoaderArgs' => $this->q('${Const|EtcDirectory}/assets/AssetSets.xml'),
            ],
            'GridService' => [
                'Region_Welcome' => $this->q('DefaultRegion, DefaultHGRegion, FallbackRegion, Persistent'),
                'MapTileDirectory' => $this->q('${Const|CacheDirectory}/maptiles'),
            ],
            'LibraryService' => [
                'LibraryName' => $this->q("{$plan->gridName} Library"),
                'DefaultLibrary' => $this->q('${Const|EtcDirectory}/inventory/Libraries.xml'),
            ],
            'LoginService' => [
                'DestinationGuide' => $this->q('${Const|WebURL}/guide'),
            ],
            'MapImageService' => [
                'TilesStoragePath' => $this->q('${Const|DataDirectory}/maptiles'),
            ],
            'GridInfoService' => [
                'gridname' => $this->q($plan->gridName),
                'gridnick' => $this->q($plan->gridNick),
                'welcome' => $this->q('${Const|WebURL}'),
            ],
            'BakedTextureService' => [
                'BaseDirectory' => $this->q('${Const|CacheDirectory}/bakes'),
            ],
        ]);

        if ($plan->enableHypergrid) {
            $ini->merge([
                'Hypergrid' => [
                    'HomeURI' => $this->q('${Const|BaseURL}:${Const|PublicPort}'),
                    'GatekeeperURI' => $this->q('${Const|BaseURL}:${Const|PublicPort}'),
                ],
            ]);
        }

        // Enable user profiles (connector + service).
        $ini->uncomment('UserProfilesServiceConnector');
        $ini->set('UserProfilesService', 'Enabled', 'true');

        return $ini->toString();
    }

    private function q(string $value): string
    {
        return '"' . $value . '"';
    }
}
