# Refactoring plan

Reference for where code and files go. Nothing here is done yet: new code is written directly at its final place, existing code moves later, one step at a time (see [Order](#order)).

## Principles

- Dependencies only go one way: `opensim-rest-php` ← `opensim-engine` ← `opensim-helpers` ← `opensim-kit`. `OpenSimKit` never appears in the other projects. The engine and the helpers are also used by w4os and 2do.directory: what they have stays compatible.
- Namespaces: `OpenSim\` is the engine (the core: grids, simulators, regions, avatars, services, settings). `OpenSimKit\` is the kit, `OpenSimKit\<Feature>` its features and services, `OpenSimKit\Setup` only what is specific to the wizard (screens, forms, plans, `NewGrid`, `NewSim`). What the setup uses but is needed elsewhere (port mapping, ports, writing config) is not in `Setup`.
- Code of the kit and of the engine follows PSR-12 and PSR-4 (composer autoload, one class per file). The helpers keep their tolerance for their community.
- Config files: the `.ini` files hold only what OpenSim reads, `Helpers.ini` what the helpers add, `<grid>/<grid>.conf` what is specific to the kit (core, version, preset, spacing, center, port mapping). All of them are read with the settings classes of the engine (`OpenSim_Config`, `Engine_Settings`), once, resolved (includes, constants), kept in one array: no `crudget` in new code.
- Bash is a wrapper: `bin/opensim` and `libexec/*` call the PHP CLI, the logic lives in `src/`.
- Files generated while running (state, caches) go in the var root of the install, never in the config.

## Class names

The code is prepared for Laravel, which the web app will be built with.

- A model is a class that has a counterpart in the database or in the files of the environment. It keeps the plain name (`Grid`, `Region`, `Avatar`) and lives in a `Models/` folder.
- Every other kind of class has a suffix that tells what it is: `Service`, `Trait`, `Interface`, `Enum`, `Exception`, `Controller`, and so on. The file has the name of the class.
- A capability shared by models is an interface named `<Capability>Interface` (`OnlineStatusInterface`), in `Interfaces/`; a trait is `<Name>Trait`, in `Traits/`. These are the standard names; the conventions of Laravel (`Has...`, `Concerns/`, `Contracts/`) are for when the code is really Laravel's.
- A class that was named before this rule gets its suffix when it moves (step 4 of the order), decided by reading it.

## Moving a class to the engine: reconcile, never replace

The engine comes from 15 years of helpers: its classes carry solutions to problems already met. A class of the kit that looks like one of the engine is not its replacement. For each one:

1. Read the engine class, its tests and its consumers (helpers, w4os, 2do). Read the kit class.
2. List what each does. What the engine does stays as it is.
3. What only the kit does is added to the engine in a compatible way (same names, same results for the current consumers), or stays in the kit when it is not general.
4. The kit class becomes a consumer of the engine class, or disappears.
5. Tests for both.

Same name does not mean same thing, and two names can be one thing: check in the OpenSim source. `RegionFlags` of the kit and `OpenSim_Region::match_flags()` both interpret `regions.flags` (see Flags below): `Region_<Name> = "DefaultRegion, ..."` in Robust is the text form of that column (`ParseFlags` in `GridService.cs`).

## Modelling notes for the engine

- Robust is a server (a service provider), not a grid. A grid can have several of them (login, users, inventory, assets), and `OpenSim_Robust` is today an alias of `OpenSim`, waiting for that.
- Ports belong to every service: simulators, Robust, MoneyServer, consoles, the web server. `InstancePorts` is not a simulator thing.
- `flags` is a column, not a concept with a class of its own: `Region->flags` is the property, `Region->isOnline()` is `flags & 4`, `Region->isDefaultHG()` is `flags & 1024`. Many tables have such a column (regions, avatars, parcels, events, classifieds...), sometimes with several concerns mixed in it, and some other columns are bitwise too. So: no `SomethingFlags` class, one unified `flags` property on every model that has the column, and methods that interpret it, named the same wherever the meaning is the same. The text form (`+Name`, `-Name`, comma separated, as `ParseFlags` reads it in the Robust config) is a method of the model that owns the column, not a class.
- A capability shared by several models is an interface the models implement, each in its own way: `Region->isOnline()` and `Avatar->isOnline()` both come from `OnlineStatusInterface`, and the caller does not know how it is stored.
- Whatever has a counterpart in the database is a model (as in Laravel): the same find, save and delete methods, one base. `OpenSim_Region` and `OpenSim_Avatar` already are close to it.
- Avatar, account, user and agent are confused in OpenSimulator itself. Define the words first (what each one is in the tables), then the classes.

## The kit, before and after

`←` says where something comes from, `+` is new. Generated or ignored folders (`build/`, `dist/`, `tmp/`, `vendor/`, `venv/`) do not change.

```
opensim-kit/
├── *.md
├── composer.json                    PSR-4: OpenSimKit\ => src/
├── composer.lock
├── phpcs.xml.dist
├── phpunit.xml
├── bin/
│   └── opensim                      wrapper
│
├── libexec/                         thin CLI entry points
│   ├── archive.php
│   ├── backup.php
│   ├── banuser
│   ├── complete-grids.php
│   │
│   ├── import-settings.php
│   ├── import-users.php
│   ├── import.php
│   │
│   ├── install-dotnet
│   ├── load-helpers
│   ├── newgrid
│   ├── newgrid.php
│   ├── newregion
│   ├── newsim
│   │
│   ├── next.php
│   ├── oar.php
│   ├── os-helpers
│   ├── ports.php
│   ├── profile.php
│   │
│   ├── sim-progress.awk
│   ├── tailgrep
│   ├── toggle.php
│   ├── venv-setup
│   ├── web.php
│   └── portmapping.php              + start|stop <instance>
│
├── src/                             OpenSimKit\ (PSR-4)
│   ├── engine.php                   ← install/engine.php
│   ├── I18n.php                     ← install/src/I18n.php
│   ├── PortMapping/                 +
│   │   ├── PortMappingService.php
│   │   ├── ModuleInterface.php      what a module does: prepare, open, close, address
│   │   └── UpnpcModule.php          PortMappingModule = upnpc
│   ├── Instance/                    running instances
│   │   ├── Actions.php              ← install/src
│   │   ├── PendingRestarts.php      ← install/src
│   │   └── Ports.php                ← install/src (blocks of ten)
│   ├── Archive/                     ← install/src/Archive
│   ├── Files/                       writing config files
│   │   ├── GridConf.php
│   │   ├── GridFiles.php
│   │   ├── LogConfig.php
│   │   ├── MoneyServerConfig.php
│   │   ├── RobustConfig.php         ← install/src/Grid
│   │   ├── SimConfig.php            ← install/src/Grid
│   │   ├── TextFile.php             ← install/src
│   │   └── Ini.php                  ← install/src
│   ├── Grid/                        what the kit adds to the engine's grid
│   │   ├── Database.php             ← install/src/Grid
│   │   ├── Features.php             ← install/src/Grid
│   │   ├── Presets.php              ← install/src/Grid
│   │   └── GridInfo.php             the <grid>.conf part only, see below
│   ├── Import/                      ← install/src/Import
│   ├── Web/                         ← install/src/Web
│   ├── System/                      this machine
│   │   ├── Config.php               ← install/src
│   │   ├── Distribution.php         ← install/src
│   │   ├── Elevated.php             ← install/src
│   │   ├── Layout.php               ← install/src
│   │   ├── Packages.php             ← install/src
│   │   ├── Releases.php             ← install/src
│   │   ├── Runtime.php              ← install/src
│   │   ├── System.php               ← install/src
│   │   └── Cores.php                ← install/src/Grid
│   └── Setup/                       OpenSimKit\Setup
│       ├── Hub.php
│       ├── Installer.php
│       ├── Plan.php
│       ├── SetupFailedException.php ← install/src/SetupFailed.php
│       ├── GridPlan.php
│       ├── SimPlan.php
│       ├── NewGrid.php
│       ├── NewSim.php    ← install/src/Grid
│       ├── SetupFile.php  ...                                    ← install/src/Setup
│       └── Ui/                      ← install/src/Ui, Console.php
│
├── install/                         installation only
│   ├── install.php
│   ├── install.sh
│   └── create-db-and-folders.sh
│
├── tests/                           same tree as src/
│   ├── Environment/
│   ├── Packaging/
│   ├── Unit/
│   ├── lib/
│   ├── Pest.php
│   └── Unit/PortMappingTest.php     +
│
├── core/                            every core, release or development
│   ├── README.md
│   ├── opensim-0.9.2.2/
│   ├── opensim-0.9.3.0/
│   └── opensim-dev/                 ← upstream/opensim (submodule)
│
├── lib/                             third-party code, as it is
│   ├── README.md                    ← contrib/README.md
│   ├── Gloebit/
│   ├── MoneyServer/       ← contrib
│   └── legacy/                      ← dev/contrib, contrib/OpenSimSearch
│
├── modules/                         sources we maintain
│   ├── README.md
│   ├── OpenSimSearch/
│
├── dev/                             development tools
│   ├── build.sh
│   ├── release.sh
│   ├── switch.sh
│   ├── build-version.sh
│   ├── transfer-remote-sim.sh   ← install/
│   ├── convert/                 ← install/ (iar2library, inv2library, experimental-*)
│   ├── deploy/                  ← deploy/, deploy.yaml
│   └── opensim-helpers/
│
├── packaging/                   as it is, but the paths its scripts use
├── share/
│   ├── bash_completion.d/
│   ├── cron.hourly/
│   ├── examples/
│   ├── mysql/
│   ├── ossl-scripts/
│   ├── presets/
│   ├── systemd/
│   ├── web/
│   └── tweaks/                      ← install/*.Tweaks.ini
├── docs/
├── locales/
├── etc/
├── var/      as they are
├── build/
│   ├── cache/                     ← src/ (downloaded DLL: Gloebit, OpenSimSearch)
├── core/
│   ├── *.tar.gz                    ← src/ (core tarballs)

Removed: src/ (downloads, now above), upstream/, contrib/, deploy/, install/src/, config/, testinstall/
```

`GridInfo` is read case by case: what it reads from `Robust.ini` (host, ports, database) is the engine's business, what it reads from `<grid>.conf` (core, preset, spacing, center, remote) stays in the kit.

## The engine, today and to review

What exists, read (names and role only, not yet the consumers):

```
opensim-engine/
├── class-config.php          OpenSim_Config: the ini files of an environment as a tree (includes, constants)
├── class-engine-settings.php Engine_Settings: settings and credentials of an application
├── class-grid.php            OpenSim_Grid: stub (singleton), nothing yet
├── class-robust.php          OpenSim_Robust: alias of OpenSim
├── class-service.php         OpenSim_Service: connections to a service (URI, database, console)
├── class-region.php          OpenSim_Region: model of the regions table (data, flags, URIs, teleport)
├── class-avatar.php          OpenSim_Avatar: avatar of the user tables
├── class-database.php        PDO, connection strings, small queries
├── class-opensim.php         OpenSim: utilities, grid_info
├── class-health.php
├── class-oar.php
├── class-form.php
├── class-search.php
├── class-currency.php
├── class-economy.php         stub
└── includes/
    └── functions*.php
```

What it gets, new code only (the `class-*.php` files stay where they are, and keep working under their names for the helpers, w4os and 2do). The names are a proposal, settled when each class is written:

```
opensim-engine/
└── src/                              OpenSim\ (PSR-4)
    ├── Models/
    │   ├── Grid.php
    │   ├── Region.php                has flags, isOnline(), isDefaultHG()...
    │   ├── Simulator.php
    │   └── Avatar.php
    ├── Interfaces/
    │   └── OnlineStatusInterface.php
    ├── Traits/
    │   └── RandomNameTrait.php       ->randomName(), for any model that proposes a name
    └── Support/                      equivalents of the case functions of bash-tools
        └── functions-strings.php     one function each, same names
            ├── words()
            ├── transliterate()
            ├── slug()
            ├── upper_case()
            ├── lower_case()
            ├── title_case()
            ├── camel_case()
            ├── pascal_case()         repaired, it is in includes/functions.php today
            ├── snake_case()
            ├── kebab_case()
            ├── screaming_snake_case()
            └── constant_case()
```

Kit classes that look like engine concerns, to reconcile one by one (rules above). The right column is a question, not a decision:

| Kit class                                                         | Concern                                | Looks like                                                  |
| ----------------------------------------------------------------- | -------------------------------------- | ----------------------------------------------------------- |
| `GridInfo` (Robust part), `GridState`, `GridRegistry`             | what a grid is, the regions it knows   | `OpenSim_Grid` (empty), `OpenSim_Config`                    |
| `RobustGrid`, `LocationFinder`, `Places`                          | asking Robust, free places             | `OpenSim_Service`, `OpenSim_Robust`                         |
| `SimState`, `RegionState`                                         | simulators and regions read from files | `OpenSim_Region` (database side), `OpenSim_Config`          |
| `RegionFlags` | text form of `regions.flags` | methods of `Region` (`OpenSim_Region::match_flags()` today) |
| `InstancePorts`, `Ports`                                          | ports of a service                     | none yet (every service has ports)                          |
| `AccountList`, `GridAccounts`, `AccountWriter`, `AccountImporter` | accounts of a grid                     | `OpenSim_Avatar` (after the words are defined)              |
| `Ini`, `TextFile`                                                 | writing ini files in layers            | `OpenSim_Config` reads, nothing writes yet                  |

The naming classes do not become classes of the engine either:

| Kit class                                   | What it does                                          | Becomes                                                                     |
| ------------------------------------------- | ----------------------------------------------------- | --------------------------------------------------------------------------- |
| `RandomName`                                | a name from an adjective and a noun that begin alike  | `RandomNameTrait`, `->randomName()`, used by any model                      |
| `RegionName` (`problem`, `configKey`, `isIn`) | what a region name accepts, how Robust compares names | methods of `Region`                                                         |
| `SimName`                                   | the name proposed for a simulator                     | a method of `Simulator`, using the trait                                    |
| `Slug` (`slug`, `nick`, `ascii`)            | a name made into a snake_case identifier              | `slug()` and `transliterate()` of the case functions, no class              |

The engine has a `pascal_case()` in `includes/functions.php`. It returns an empty string (`$$key`, and `$pascal_words` never filled), and nothing calls it: repair it in place, where it is, and write the others beside it with the behaviour of those of bash-tools (`words` splits on anything that is not a letter or a digit and on a lower to upper change, the case functions transliterate first). The apostrophe follows bash-tools: `transliterate` removes it ("Olivier's grid" is `oliviers_grid`), which is also what the slug of the kit does today.

Everything else (`Setup`, `Ui`, `Archive`, `Import`, `Web`, `System`, `Files`) is the kit's.

## Order

1. Write the port mapping at its final place (`src/PortMapping/`, `libexec/portmapping.php`), reading `<grid>.conf` with the engine's settings.
2. Move the downloads out of `src/`, then the core to `core/`, `contrib/` to `lib/`: paths of the build scripts.
3. Rename `OpenSim\Installer` to `OpenSimKit`, mechanically, tests passing.
4. Move the classes by domain as in the tree.
5. Engine, one class at a time, by the reconciliation above, each with a release of the engine before the kit uses it.
