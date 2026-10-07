<?php

declare(strict_types=1);

namespace OpenSim\Installer\Grid;

use OpenSim\Installer\Ini;

/**
 * What `opensim enable|disable <grid> <feature>` writes: the settings a feature of a grid needs, in the files of its
 * simulators (sims/*.ini, which each simulator reads) and, for what Robust says, in its Robust config. Nothing else is
 * touched: the operator customizes the rest in the files, and a setting that is there is changed in place.
 *
 *  - search: the module of search of the simulators (`[Search]`) and their registration to the search service of the helpers
 *    (`[DataSnapshot]`), which the helpers read back (`query.php` answers the module, `register.php` the snapshots);
 *  - economy: the economy module of the simulators (the beta module, or Gloebit, whose keys are the operator's: no default
 *    secret), and for Gloebit the address of the economy that Robust tells the viewers (`currency.php`, `landtool.php`);
 *  - profiles: the profile service the simulators ask for the profiles and their classifieds (`[UserProfiles]`), served by
 *    Robust (`[UserProfilesService]`).
 */
final class Features
{
    public const SEARCH = 'search';
    public const ECONOMY = 'economy';
    public const PROFILES = 'profiles';
    public const NAMES = [self::SEARCH, self::ECONOMY, self::PROFILES];

    /** The money modules the command knows, by the word it takes, and what OpenSim calls them in `economymodule`. */
    public const MONEY = ['beta' => 'BetaGridLikeMoneyModule', 'gloebit' => 'Gloebit'];

    /** @param array<string,mixed> $profile the setup of the user (CoreDirectory, EnabledModules, ModulesRoot) */
    public function __construct(private GridInfo $grid, private array $profile = []) {}

    public static function isFeature(string $word): bool
    {
        return in_array(strtolower($word), self::NAMES, true);
    }

    /**
     * Enable a feature of the grid.
     *
     * @param  array{prefix?:string,money?:string} $options the address of the services (a prefix: `<prefix>/query.php`), the
     *                                                      money module (beta or gloebit)
     * @return array{error:?string,sims:list<string>,robust:bool,notes:list<string>}
     */
    public function enable(string $feature, array $options = []): array
    {
        return $this->apply($feature, true, $options);
    }

    /**
     * Disable a feature of the grid.
     *
     * @return array{error:?string,sims:list<string>,robust:bool,notes:list<string>}
     */
    public function disable(string $feature): array
    {
        return $this->apply($feature, false, []);
    }

    /**
     * The simulators of the grid made on this machine: the name of the instance (grid_sim) and its ini file.
     *
     * @return array<string,string> instance => path
     */
    public function simulators(): array
    {
        $sims = [];
        $files = glob("{$this->grid->dir}/sims/*.ini") ?: [];
        sort($files);
        foreach ($files as $file) {
            $sims[basename($file, '.ini')] = $file;
        }

        return $sims;
    }

    /**
     * @param  array{prefix?:string,money?:string} $options
     * @return array{error:?string,sims:list<string>,robust:bool,notes:list<string>}
     */
    private function apply(string $feature, bool $on, array $options): array
    {
        $result = ['error' => null, 'sims' => [], 'robust' => false, 'notes' => []];
        $prefix = rtrim(trim($options['prefix'] ?? ''), '/');
        if ($prefix !== '' && !preg_match('#^https?://[^\s/]+#i', $prefix)) {
            $result['error'] = "'$prefix' is not an address, give the start of the address of the services (https://www.example.org/helpers)";

            return $result;
        }

        switch (strtolower($feature)) {
            case self::SEARCH:
                return $this->search($on, $prefix, $result);
            case self::ECONOMY:
                $money = strtolower($options['money'] ?? 'beta');
                if ($on && !isset(self::MONEY[$money])) {
                    $result['error'] = "unknown money module '$money' (" . implode(', ', array_keys(self::MONEY)) . ')';

                    return $result;
                }

                return $this->economy($on, $money, $prefix, $result);
            case self::PROFILES:
                return $this->profiles($on, $result);
        }
        $result['error'] = "unknown feature '$feature' (" . implode(', ', self::NAMES) . ')';

        return $result;
    }

    /**
     * @param  array{error:?string,sims:list<string>,robust:bool,notes:list<string>} $result
     * @return array{error:?string,sims:list<string>,robust:bool,notes:list<string>}
     */
    private function search(bool $on, string $prefix, array $result): array
    {
        $name = SimConfig::dataServiceNameOf($this->grid->nick, $this->grid->name);
        $query = $prefix !== '' ? "$prefix/query.php" : $this->grid->helperUrl('query');
        $register = $prefix !== '' ? "$prefix/register.php" : $this->grid->helperUrl('register');
        if ($on && ($query === '' || $register === '')) {
            $result['error'] = "the grid {$this->grid->nick} has no helpers: give the address of its search service, "
                . "opensim enable {$this->grid->nick} search https://www.example.org/helpers";

            return $result;
        }

        $this->eachSimulator($result, function (Ini $ini) use ($on, $name, $query, $register): void {
            $service = "DATA_SRV_$name";
            if ($on) {
                $ini->set('Search', 'Module', 'OpenSimSearch');
                $ini->set('Search', 'SearchURL', $this->quote($query));
                $ini->set('DataSnapshot', 'index_sims', 'true');
                $ini->set('DataSnapshot', 'gridname', $this->quote($this->grid->name));
                $ini->set('DataSnapshot', $service, $this->quote($register));

                return;
            }
            $ini->remove('Search', 'Module');
            $ini->remove('Search', 'SearchURL');
            $ini->remove('DataSnapshot', $service);
            // A simulator registered to another directory (2do...) keeps telling its snapshot to it
            if ($ini->keys('DataSnapshot', '/^DATA_SRV_/i') === []) {
                $ini->remove('DataSnapshot', 'index_sims');
            }
        });
        if ($on) {
            array_push($result['notes'], ...$this->moduleNotes('opensimsearch'));
        }

        return $result;
    }

    /**
     * @param  array{error:?string,sims:list<string>,robust:bool,notes:list<string>} $result
     * @return array{error:?string,sims:list<string>,robust:bool,notes:list<string>}
     */
    private function economy(bool $on, string $money, string $prefix, array $result): array
    {
        $module = self::MONEY[$money] ?? self::MONEY['beta'];
        $this->eachSimulator($result, function (Ini $ini) use ($on, $money, $module): void {
            if (!$on) {
                // OpenSimulator has the beta module when it is not told another one
                $ini->remove('Economy', 'economymodule');
                if ($ini->get('Gloebit', 'Enabled') !== null) {
                    $ini->set('Gloebit', 'Enabled', 'false');
                }

                return;
            }
            $ini->set('Economy', 'economymodule', $module);
            if ($money === 'beta') {
                $ini->set('Economy', 'SellEnabled', 'true');
                if ($ini->get('Gloebit', 'Enabled') !== null) {
                    $ini->set('Gloebit', 'Enabled', 'false');
                }

                return;
            }
            $ini->set('Gloebit', 'Enabled', 'true');
            if ($ini->get('Gloebit', 'GLBEnvironment') === null) {
                $ini->set('Gloebit', 'GLBEnvironment', 'sandbox');
            }
        });

        if ($on && $money === 'gloebit') {
            // The currency is served by the helpers: the viewers are told where
            $economy = $prefix !== '' ? $prefix : $this->economyUrl();
            if ($economy !== '') {
                $result['robust'] = $this->robust(fn(Ini $ini) => $ini->set('GridInfoService', 'economy', $this->quote($economy)), $result);
            }
            array_push($result['notes'], ...$this->moduleNotes('gloebit'));
            $missing = [];
            foreach ($this->simulators() as $file) {
                $ini = Ini::load($file);
                foreach (['GLBKey', 'GLBSecret', 'GLBOwnerName'] as $key) {
                    if ($ini->get('Gloebit', $key) === null) {
                        $missing[$key] = true;
                    }
                }
            }
            if ($missing !== []) {
                $result['notes'][] = 'the keys of your Gloebit application are yours to give: ' . implode(', ', array_keys($missing))
                    . ' in [Gloebit] of the simulators (Gloebit.ini.example), the module does nothing without them';
            }
        }

        return $result;
    }

    /**
     * @param  array{error:?string,sims:list<string>,robust:bool,notes:list<string>} $result
     * @return array{error:?string,sims:list<string>,robust:bool,notes:list<string>}
     */
    private function profiles(bool $on, array $result): array
    {
        $this->eachSimulator($result, function (Ini $ini) use ($on): void {
            if ($on) {
                $ini->set('UserProfiles', 'ProfileServiceURL', '"${Const|BaseURL}:${Const|PublicPort}"');
            } else {
                $ini->remove('UserProfiles', 'ProfileServiceURL');
            }
        });
        // The service is Robust's: its connector and its switch
        $result['robust'] = $this->robust(function (Ini $ini) use ($on): void {
            if ($on) {
                $ini->uncomment('UserProfilesServiceConnector');
            }
            $ini->set('UserProfilesService', 'Enabled', $on ? 'true' : 'false');
        }, $result);

        return $result;
    }

    /**
     * Edit the ini file of each simulator of the grid, and keep which ones changed.
     *
     * @param array{error:?string,sims:list<string>,robust:bool,notes:list<string>} $result
     * @param callable(Ini):void $edit
     */
    private function eachSimulator(array &$result, callable $edit): void
    {
        foreach ($this->simulators() as $instance => $file) {
            if ($this->edit($file, $edit)) {
                $result['sims'][] = $instance;
            }
        }
    }

    /**
     * Edit the Robust config of the grid, when it is on this machine.
     *
     * @param array{error:?string,sims:list<string>,robust:bool,notes:list<string>} $result
     * @param callable(Ini):void $edit
     */
    private function robust(callable $edit, array &$result): bool
    {
        if ($this->grid->remote || $this->grid->robustIni === '' || !is_file($this->grid->robustIni)) {
            $result['notes'][] = "Robust of {$this->grid->nick} is not on this machine: the same change is to make in its config there";

            return false;
        }

        return $this->edit($this->grid->robustIni, $edit);
    }

    /** Run an edit on an ini file and save it when it changed. */
    private function edit(string $path, callable $edit): bool
    {
        $ini = Ini::load($path);
        $before = $ini->toString();
        $edit($ini);
        if ($ini->toString() === $before) {
            return false;
        }
        $ini->save($path);

        return true;
    }

    /** The address of the economy the grid gives its viewers, else where its helpers are. */
    private function economyUrl(): string
    {
        $current = $this->grid->robustIni !== '' && is_file($this->grid->robustIni) ? Ini::load($this->grid->robustIni)->get('GridInfoService', 'economy') : null;
        if ($current !== null && $current !== '') {
            return $current;
        }
        $currency = $this->grid->helperUrl('currency');

        return $currency === '' ? '' : dirname($currency);
    }

    /**
     * What the user must know of a module: that it is not enabled for the install, or not installed.
     *
     * @return list<string>
     */
    private function moduleNotes(string $module): array
    {
        $enabled = preg_split('/[\s,]+/', (string) ($this->profile['EnabledModules'] ?? ''), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $notes = [];
        if (!in_array($module, $enabled, true)) {
            $notes[] = "the module $module is not in EnabledModules of opensim.conf, the simulators do not load it";
        }
        $root = (string) ($this->profile['ModulesRoot'] ?? '/usr/share/opensim-modules');
        $core = basename((string) ($this->profile['CoreDirectory'] ?? ''));
        if ($core !== '' && !is_dir("$root/$core/$module")) {
            $notes[] = "the module $module is not installed for $core (apt install opensim-$core-$module)";
        }

        return $notes;
    }

    private function quote(string $value): string
    {
        return '"' . $value . '"';
    }
}
