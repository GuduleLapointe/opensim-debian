# Roadmap

## Fixes

- [x] Separation of concern is not respected in child libraries. Each library must remain strictly independent and never rely on data, classes or concept defined in the apps/projects/libraries consuming it.
- [x] The port-based location must only be used to set the initial center of the grild. Once it has been set, it is not overridable by the rule.
- [x] Wrong syntax for `opensim load iar|oar` and `opensim save iar|oar`: action and save must stay together, `opensim save iar [instance]`, not `opensim save [instance] iar`
- [x] Could not get load/save oar/iar to work
- [ ] Could not get helpers to work (web returns 500) (the helpers answer with the new `config.php` on a test grid, and say when `helpers.ini` is unreadable by the web user instead of a 500; to confirm on the real site: what does the error log say? The packaged helpers answer behind real nginx, Apache and Caddy in `web-scenario.sh`)
- [ ] setup: working from grid/sims was a design flaw: (done: the grid screen lists regions, "Add region" asks the simulator of the pool, `opensim save|load oar` and `opensim backup` take a sim or a region; to do: the other commands)
    - from user's point of view, there is a grid, with regions
    - and simulators are just a pool of servers providing the regions
    - setup must propose to create regions first, with the choice to create a new simulator or add them to use one from the sims pool.
    - command-line tools must allow both sims and regions as second argument
- [x] Changelog is not a user guide, keep it as a SHORT list of changes, no explanations!
- [x] Tests output is truncated, KEEP descriptions and notifications SHORT
- [x] (Test fail) opensim save and load → refuse a file that exists a simulator that does not run a missing password
- [x] (Test fail) opensim backup → holds the configuration the data and a dump of each database of a grid and nothing the users backed up
- [x] (Test fail) opensim backup → takes the archives of the users and the logs when asked
- [x] (Test fail) opensim backup → is the one of a simulator without the other ones
- [x] (Test fail) opensim import the config of a grid → tells the plan and the settings it keeps and writes nothing without apply
- [x] Architectural misconception: opensim-engine include codes referencing the class `OpenSim_Kit`, which is a nonsense: Engine is the a standalone library, the Kit consumes it, not the other way around
- [ ] check `opensim stop` on a simulator with an NPC in it: the real users are counted from `show users`, the NPCs (`NPC Root`) are skipped, but only the format was checked on a real core, not an NPC -> does not seem to properly skip the NPCs (the rows of `show users` are now read on a screen console too, the NPCs are left out; still to confirm with a real NPC)
- [ ] confirm on ubuntu 24.04 (ursull), with the next package and an outdated bash-tools left in composer global, that `opensim setup` no longer ends with `debug: command not found` (the scripts now load the bash-tools of the kit before the one of the `PATH`, see `libexec/load-helpers`)
- [x] dependencies packages must be added to the release assets in their own repositories (done: build-tools, `dev/release.sh` in each project)
- [x] opensim status "down" count should not be displayed when none of the instances are down
- [x] a new region is named after itself without a restart of the simulator (the object of `share/ossl-scripts`, loaded through the console)
- [x] firewall: `opensim ports --ufw` tells the rules, `INSTALLATION.md` has a Firewall section, the setup does not apply them
- [x] the simulators of a grid with helpers know where `offline.php` (`[Messaging]`) and `register.php` (`DATA_SRV_<Grid>` of `[DataSnapshot]`) are
- [x] the message of the day: `[LoginService] MessageUrl` is a URL whose text is shown at login (read by Robust when it starts, `WelcomeMessage` when it cannot be), found in the code of the core (`LLLoginService`); `motd.php` of opensim-helpers serves the `motd` of `helpers.ini`
- [x] the destination guide is `guide.php` of the helpers, `DestinationGuide` of the Robust config points to it
- [ ] the search page of the web site: not in opensim-helpers (no branch has a page, only the backend behind `query.php`), it is probably in the WordPress plugin; find out where it reads from, so that it finds what `parser.php` indexes, and write the `search` URL of `[GridInfoService]` when it exists (found: the `web-search` block of w4os has no source of its own, it asks `query.php` of the helpers: `dir_places_query` over XML-RPC with `websearch`, `gk` and the flags)
- [ ] run again the packaging scenario beyond the upgrade step (the development build, removal, purge) and with the real `apt-package`: only the packages built by hand with nfpm were tested; run it with docker as well as podman (done with podman: full scenario on Debian 12 and Ubuntu 24.04, packages from `apt-package`; docker left)
- [x] try the web server examples written by the setup (`<grid>.caddyfile`, `<grid>-nginx.conf`, `<grid>-apache.conf`) on a real Caddy, nginx and Apache (`tests/Packaging/web-scenario.sh`; nginx needed a fix)

## Critical improvements

- [x] `opensim save|load iar|oar` and `opensim backup`: the archives of the users in `<data of the grid>/backups`, the backup of the administrator (configuration, data, databases)
- [ ] `opensim restore` of a backup of the administrator on a new machine (the install, the databases, the data), and a check of what the archive holds
- [ ] the archives of the users: `opensim save oar` for every region of a grid (a rotation, `--keep N`), and the OAR of a region that belongs to a user who is not the owner of the grid (what the owners of regions can ask for themselves)
- [x] backup: the rotation of the old backups (`--keep N`), and the same for the archives
- [x] bulk avatar creation (from a list provided by a third-party source: CSV, JSON or YAML), directly in the database, as `opensim import [grid] FILE --users`; relies on the install the profile gives, with or without the kit
- [ ] try the accounts made in the database with a viewer (login, inventory, default outfit) on a test grid, and check the numbers of the system folders (`Settings` 56 and `Material` 57) against the core
- [ ] test `opensim import` on real grids (the config of a grid or of a simulator, a setup file, a list of accounts: what works, what is missing)
- [x] add opensim-helpers package and examples of configuration as alias/subfolder in caddy, nginx and apache2
- [x] an `unstable` suite in the apt repository for the pre-releases, `stable` for the releases (done: the suite is in `conf/distributions`, `APT_REPO_PRERELEASE_DIST`, `apt-package` chooses the suite of each package: a `~` or `-dev|alpha|beta|rc` makes a pre-release, a Debian revision or a `0.4+git…` snapshot does not)
- [ ] `build-tools release` refuses a pre-release when the apt repository has no suite for it; the betas published in `stable` are to move to `unstable` (cleanup, later)
- [ ] add docker/podman installation support in setup
- [ ] SSL on the OpenSim servers, which 0.9.3 supports: a simulator has `http_listener_ssl` and a PKCS12 certificate, and `EnableCertConverter` makes it from the PEM files of the CA (`fullchain.pem`, `privkey.pem`) at each start; Robust has `[Network] https_*` and `cert_path` but no converter (a `.p12` made with openssl). Base it on certbot, not on the certificate of Caddy: the `ExternalHostName` of a region is not always a site. A deploy hook copies the PEM where `opensim` reads them (`/etc/letsencrypt` is root's) and restarts the instances, the conversion only happens at start. The `SSL/` folders are made in the working directory of the process and the cores are read-only: check it. To try first on a public host with podman, then a section in the setup of a grid and an `opensim ssl` command
- [ ] rename composer packages to match github account GuduleLapointe
- [ ] parcel naming object: make it part of the library of the grid too (the original stays available to the users, a copy is rezzed in each new region), and let the operator choose what a new region gets at its start (name of the parcel, music, media, flags: the script is the place for it)
- [ ] OSSL: the standard config enables the functions with the defaults of the core; let the operator customize them (which functions, for whom)

## Next steps

- [ ] add full import procedure for existing grids/simulators: the config is imported (`opensim import`), the database, the assets and the other data are not
- [x] add/update bash completion
- [ ] build ready to use zip packages for simple download, added to the release assets alongside the source code and apt package
- [ ] add simplified installation script, detecting the platform and installing with the appropriate method (zip package or apt package), with a one-line command like `curl -sSL https://raw.githubusercontent.com/GuduleLapointe/opensim-kit/refs/heads/master/install.sh | bash`
- [x] add basic ready to use website (the placeholder site of the `opensim-web` package)
- [ ] add ready to use default avatars
- [ ] add default Inventory package
- [ ] add nat/port forwarding instructions or presets (with standard tools or third-party provides like ngrok, cloudflare...)
- [ ] make most opensim-tools features installation-agnostic (support both opensim-kit and custom/standard opensim installations): `/etc/opensim/opensim.conf` becomes only a reference pointing to the user's actual core installation and configuration files location (e.g. `/opt/osgrid`, `~/diva`, `~/opensim/opensim-0.9.3.0/bin`...)
    - [x] `opensim profile list|add|default|remove` registers an install made by hand (core, config and data directories)
    - [ ] a grid follows the profile of its own install, the default profile is only the default of the new grids
    - [ ] the system user to run the instances as is per profile, not only `SystemUser` of `[Defaults]`
    - [ ] the setup offers to register an install (the core menu), and no script assumes `/etc/opensim`, `/var/lib/opensim` or `/usr/share/opensim` (review, one fix per file, with a test)
- [x] the search is tested on the grid of the package scenario (`STOP_AFTER=search tests/Packaging/run`): registering, the snapshot, places, popular places, land, events, classifieds, people, with the keys the module sends (`tests/Packaging/search-scenario.sh`)
- [x] the real simulator registers by itself when it starts and tells a parcel shown in search (`ShowDirectory`, read at start), which the places and the popular places give (`tests/Packaging/search-scenario.sh`)
- [x] search: a simulator that cannot be reached, or answers "try later" (503: one request a minute per address), is asked again later, more and more late, and forgotten after ten failures (it was asked at every run)
- [x] the offline messages, the economy (currency, land tool) and the pages of the helpers (message of the day, guide, images of text, events, directory) are tested on the grid of the package scenario (`offline-scenario.sh`, `economy-scenario.sh`, `pages-scenario.sh`)
- [ ] offline messages: a grid without helpers has none (the setup writes no `[Messaging]`, and the `OfflineIMService` of Robust that the V2 module needs is not enabled): helpers (mails, no limit), V2 module with the Robust service (no mails, 25 messages), or both
- [x] a simulator without a region hangs at its first start (OpenSim asks the questions of a first region on a console nobody reads): the setup does not start it, `opensim start` refuses it and says to add a region; seen on vonda with `vonda_back_office`, and `opensim stop` forces what does not stop
- [ ] delete a region, later: it needs several checks (users in it, the neighbours, the estate, its parcels and objects), a backup first, and the registry of the grid and of the search cleaned; today it takes the console (`delete-region`), its file and a row of the grid database by hand
- [ ] helpers: the guide reads `source` from the request when the grid sets none, so any file or URL the server can read: ignore it, or accept only URLs
- [ ] helpers: the style, the script and the picture of the guide and the style of the directory page are linked with the path of the files (`/usr/share/opensim-helpers/...`), the router should serve them
- [ ] helpers: an empty calendar keeps the old events (`eventsparser.php` takes `[]` for an invalid answer)
- [x] helpers: the Gloebit sandbox is `[Gloebit] GLBEnvironment`, read by `addons/gloebit.php` in the ini files of the grid (including `config-include/`)
- [ ] helpers: without a money server they write `transactions` with other columns (`sourceId`, `destId`...) than with one (`sender`, `receiver`...): check which module makes which (Gloebit makes neither: its tables are `GloebitUsers`, `GloebitTransactions` and `GloebitSubscriptions`, so this is for the MoneyServer review)
- [ ] the engine has its own `OpenSim_HypEvents` and `functions-economy.php`, with the faults the helpers had (`make_random_guid`, `opensim_get_region_info`): which one is the source?
- [x] the helpers have one layout for every page (left sidebar, main content, right sidebar), blocks (grid info, grid status, message of the day) that templates use as variables, the content alone for a viewer except on the splash page, and a `/health` page listing the addresses of the grid with their status (`pages-scenario.sh`)
- [ ] helpers: the `news` block of the right sidebar (what does it show?), the shortcode form `[grid_info]` for texts, Bootstrap or Tailwind for the looks
- [ ] helpers, next blocks and pages: popular places, web search (`?q=`, `/<topic>/`, `/<category>/`), avatar profile with its stream (the term of Firestorm, no longer "flux"; a table meant to be compatible with ActivityPub, read only first), the web assets (`<prefix>/assets/<uuid>.<format>`, the textures of the grid converted for the web, read from the asset service of Robust)
- [x] helpers, URLs: each service is where the official settings put it (addresses on this site only), `<path>/<script>.php` is the default, a route answers its sub-paths; still to do: the guide topics and the search categories as sub-paths (`/guide/<topic>/`), the web search page (`SearchURL` of Robust)
- [ ] helpers, `GridStatus` and `GridStatusRSS` of `[GridInfoService]` (found: Firestorm reads them, in the formats of Second Life; nothing in the code of OpenSim but the examples of the config): `GridStatus` is a light HTML or plain text page the viewer takes one line of global status from, by keyword (ONLINE green, OFFLINE red, DEGRADED or MAINTENANCE orange); done: `/helpers/grid_status` (ONLINE, OFFLINE) and `/helpers/grid_info` are public pages, `GridStatus = "${Const|WebURL}/helpers/grid_status"` is all a grid needs; to do: DEGRADED and MAINTENANCE; `GridStatusRSS` is a standard RSS 2.0 feed (dates RFC 822: title, link, guid, pubDate, description in CDATA, everything in English), first only with the moments the grid changes state (the parsers have part of it), later real messages (another RSS source, a social feed); examples: status.secondlifegrid.net (`history.rss`, `history.atom`, and the page, like health but for the public)
- [ ] engine, `OpenSim_Config` (reads an environment into a tree that follows the ini files, with includes and constants): the machine's grids (`opensim.conf`, `/etc/opensim/grids`, `OPENSIM_GRID`) are still found by the helpers; and `OpenSim_Ini` (one file, used by the wizard of the plugin) is a second reader: make it one
- [ ] helpers, lists for LSL scripts: the guide and the search give the list as text (title and URL separated by a pipe, a text alone is a title, a dash alone a new column, a pipe alone an empty row), which a guide source can be too
- [x] helpers, currency: the key that ties a quote to its purchase is `MoneyScriptAccessKey` of `[MoneyServer]` in `MoneyServer.ini` beside Robust.ini, the original file of the MoneyServer whole (contrib/MoneyServer), completed by the setup with a random key; no default key anywhere, without it the currency helpers answer a bad configuration; installing the package adds the file to a grid that lacks it
- [ ] the setup merges the distribution into the files of a grid (new settings are taken); installing a package adds missing files only (MoneyServer.ini for now), a new release of the distribution does not reach the files of existing grids until `opensim setup` configures them: decide whether the package should merge too, and what to do of the comments an operator wrote in a file that is merged
- [ ] read the configuration loader of OpenSimulator (the code of the core) to know the real order in which an ini file, the files it includes and OpenSimDefaults.ini are merged: the helpers read a file, then what it includes, the included values replacing the earlier ones, which is a guess that does not matter to them
- [x] helpers, currency, Gloebit (read in `contrib/Gloebit/src`): the module keeps its balance and its transfers at Gloebit and its own tables in `[Gloebit] GLBSpecificConnectionString`, else the `[DatabaseService]` of the simulator (not Robust's); it makes no `balances` or `transactions`, the helpers never need them with it. It answers the quote, buy and land calls itself when `economy` points at the simulator, and sends the rest to the grid: currency.php and landtool.php
- [ ] currency modules, later: MoneyServer (Podex needs it, so does a game currency without real value) is an old version, unmaintained, its site is gone: compare the forks (DEVELOPERS.md), keep a stable one or make one that reads its settings like the other modules (its ini is looked for next to its executable, its credentials are not dotNet connection strings); PayPal: the original module is outdated, compare the recent forks. Until then the helpers keep the Podex and PayPal code they have, with no addon and no setting
- [ ] helpers, still to port from the references (speculoos, 2do, w4os.org), nothing of the economy or of the scripts of the root is missing: `TROUBLESHOOTING.md` and `INSTALLATION.md` (the text, for the README and the kit), `class-helpers-migration-2to3.php` (the move of the old `config.php`, or `opensim import`), the HYPEvents client class of speculoos (`class-helpers-hypevents.php`) against `events.php`, the checks of the install wizard (`is_robust_ini_file`, `robust_test_config`, `getTestCurrencyQuoteParams`) in `opensim import` and `opensim web check`; the WordPress glue (`wp-load.php`, `config.wp.php`) stays in the w4os plugin
- [ ] add to the README of opensim-helpers (and of the engine) a short section on how to use them with the OpenSim kit
- [ ] install instructions in the README of `lsl-ossl-zed` (rust, clone, `zed: install dev extension`, the prebuilt LSP binaries only cover Linux x86_64 and macOS)

## Less urgent

- [x] localize the setup with gettext (a requirement of the project): the messages of the PHP setup are translatable (`locales/`, `locales/update-pot`)
- [ ] localize the launcher and the bash scripts with gettext
- [ ] a first translation of the setup (`locales/fr.po`)
- [ ] localize helpers
- [ ] `opensim online`: show who's online

## Could be great

- [ ] Memory/CPU usage monitoring. Notify admin and/or restart Sim above given thresholds. Thinking twice about previous notify thing: maybe it's better to suggest a dedicated monitoring tool handle that.
