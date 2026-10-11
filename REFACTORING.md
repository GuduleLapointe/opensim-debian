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
