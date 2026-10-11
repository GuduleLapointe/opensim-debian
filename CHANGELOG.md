## Changelog

### 3.0.0-beta.6

Freeze current state before refactor

- refactoring plan draft
- fix(search): a registering simulator is read after 30 s, when its regions are loaded: the snapshot it makes while starting is the one it keeps
- fix(profile): only the show in search flag of the account is written, the other flags are not the user's choice
- docs(changelog): show in search is the flags of the user account
- fix(profile): show in search is the first flag of the user account, which is what OpenSimulator shows and keeps, not a column of the profile
- fix(search): a simulator that could not be read keeps its backoff, it is not read again in 90 s
- fix(search): a simulator that registers is read again 90 s later, its regions are not all loaded when it registers
- fix(search): avatar_publish_update needs the secure session of the avatar, not the address of the caller
- fix(search): avatar_publish_update answers as the other calls do, a struct with success and errorMessage
- feat(search): avatar_publish_update, the show in search box of a profile that Robust ignores, written for the simulators of the grid only
- fix(search): a registering simulator is let go at once, not held while its snapshot is read (it was read empty); the search reads the hosts that are due once a minute
- docs(changelog): the regions table of the search, the search requests without flags
- fix(search): the table of the regions of the search is never the regions table of Robust, whether Robust has made it or not yet
- fix(search): a search request without flags or without the day|category pieces is answered, not a 500
- feat(pages): the web search, the page of an avatar, the popular places; fix: the search registry is read in process, and by the search every ten minutes
- docs(changelog): the script an address names answers it
- fix(routes): the script an address names answers it, a registration that points to query.php no longer takes the place of the search
- feat(health): an address is shown as the config says it and linked as it was asked; docs: the web search settings as tested with Firestorm
- feat(config): search_url, search of the grid info first, SearchURL of the login service when the grid has only that
- feat(pages): public pages grid_info and grid_status (ONLINE, OFFLINE) under the helpers, GridInfoURL and GridStatusURL in [Helpers]; fix(health): the profile with an avatar name is in the config section
- refactor(config): the ini files are read by the engine's OpenSim_Config; feat(health): what the grid says first, compared with the config, each service called without data and with valid data
- feat(health): one aligned table by config file, cert, time and result on the right, franker colors
- feat(health): the health page asks the engine's checks; wrong calls get their code (418, 405, 400, 404, 503), never a 500 on purpose
- refactor(functions): the helpers use the engine's functions (destinations, teleport links, regions, XML answers, currency, XML-RPC polyfill) and keep only what is theirs
- fix(health): the avatar the profile is asked for is the first account that is not the services of the grid
- fix(currency)!: no default key, without MoneyScriptAccessKey the currency is not served (bad configuration); fix(health): 404 and any error in red, orange for an untrusted certificate, the key row, the profile without a name expects a bad request
- fix(health): curl ignores the certificate and the page reports it, the profile is asked with the name of the owner and without; /helpers is a public page and health keeps its own address; the script key is only the MoneyServer's
- fix(config): the ini files are read with their includes and constants, MoneyScriptAccessKey only in [MoneyServer] of MoneyServer.ini; fix(health): sections and settings as in the ini files, links, final HTTP code or error code, every address of [GridInfoService]; the address of the helpers answers
- feat(pages): the menu lists the pages the site serves, health also under the helpers path; fix(currency): each grid has its own script key (MoneyServer's, Helpers.ini's, or made of its database password)
- docs(economy): what currency.php and landtool.php do with a module, and who sends to the page of Gloebit
- fix(economy): currency.php and landtool.php serve any module but the beta one, Gloebit adds through its region and sends to its page when it cannot; default rate 4 for 1000, UseUTCTime back
- feat(economy): the currency is what the economy module says (Gloebit, BetaGridLike), the settings of Gloebit are read, a currency sold elsewhere is never added; web_profile_url routes the profiles
- fix(routes): an address of the grid is served when its host (and its port, if it gives one) are the ones of the page served
- fix(guide): the block is destination_guide, as the setting is called: the home page shows it
- fix(config): a key given twice in a section is read as OpenSimulator reads it, the first one
- feat(config)!: the helpers read the official settings of OpenSim.ini and Robust.ini, Helpers.ini holds what has no place there; routes from the addresses of the grid on this site; each query on the database of its service
- fix(guide): the title of a category is its name
- feat(pages): templates in layouts, pages and components with suffixes, a theme folder that replaces them and the assets, the guide and directory_info as blocks of the layout (3-6-3), styles in the head
- feat(guide): an empty guide tells the one who runs the grid why, in the log and in the page source; docs(layout): the templates, the blocks, the assets
- feat(health): the addresses of the grid itself are asked at /get_grid_info
- feat(pages): one layout with a left and a right sidebar, blocks as variables of the templates, a health page, the routes of the profiles; assets in assets/
- feat(pages): blocks (grid info, grid status) and a layout that adapts to viewers and browsers; static files in static/, page scripts in pages/
- feat(guide): a guide_source that is a file name is looked for in the folder of the grid
- fix(search): the cache gives back what was stored, an empty list included
- test(helpers): the destinations, the teleport links, UUIDs, the price of the currency and the guide, with no OpenSim at all
- fix(events): opensim_latin1_only, which eventsparser calls, exists
- fix(scripts): guide through the router and from helpers.ini, textgen size and download, directory_info without grids
- docs(changelog): the currency fixes, shorter
- fix(currency): the balances table is locked with a valid query
- fix(currency): the region of a transaction is looked up in the database of the grid
- fix(currency): the transactions of a money server get an id, the purchase for a land debits nothing
- fix(currency): a default rate, the Gloebit pack, the balance for who has the session, the answers of landtool
- docs(changelog): the offline messages behind the router
- fix(router): a script called with what OpenSimulator adds to it (offline.php/SaveMessage/) is served, and gets it as PATH_INFO
- docs(changelog): the delay of the unreachable simulators
- fix(search): a simulator that cannot be reached is asked again later, as the code says, not at every run
- docs(changelog): the fixes of the search
- fix(search): a parcel without picture or group does not get the ones of the parcel before it
- fix(search): the second page and the sort by traffic: the page is a number for the SQL, the order is not a parameter
- fix(search): an answer without a result still has its list of data, which the module reads
- fix(xmlrpc): the polyfill leaves the printing to the methods, as the extension does: one answer, not two
- fix(router): a script runs in the global scope, as with a web server, the entry includes it
- refactor(search): remove SEARCH_REGISTRARS, dead code: a simulator registers to several search services with its DATA_SRV_*
- dev: bump work version to 3.0.0-beta.6, the family linked again
- v3.0.0-beta.5
- refactor(build): build-tools replaces the copies of the scripts of dev/ and packaging/
- fix(release): a signature that times out is detected and offered again, the export of the apt repository is redone alone
- fix(release): the passphrase is asked first, a failed publication says what to do, the checksums are made when missing; the note of build.sh skips the links

### 3.0.0-beta.4

- new: `dev/release.sh` makes the whole release, `dev/switch.sh` the composer part
- fix: web server files are complete, for the site of the web URL: to include, or to use alone
- new: `--keep N` on `opensim backup` and `opensim save iar|oar` removes the older ones
- new: `dev/build.sh` makes the Debian packages and the zip of the tools
- new: `BUILD_HOST` builds OpenSimulator on another machine
- update: helpers, engine and rest-php are packages of their projects, the tools depend on them
- update: the helpers read the config of the grid, no `config.php` from the kit
- new: tests of the packages run on the podman of another machine (`CONTAINER_CONNECTION`)
- fix: nginx hands a script that is not a file to the router
- fix: `opensim save|load iar|oar [instance]`, the instance is a grid, a simulator or a region
- fix: the public port sets the first center of a grid only, its regions give it afterwards
- fix: `opensim stop` skips the NPCs on a screen console
- fix: unreadable `helpers.ini` is reported by the helpers instead of a 500
- fix: tests run on macOS (temporary folder, tar)

### 3.0.0-beta.3

- new: `opensim save|load iar|oar`
- new: `opensim backup [GRID [SIM]] [--logs] [--archives] [--output DIR]`
- new: quick setup, one screen, other settings by default
- new: `opensim setup --file FILE`, taking full config from a JSON or YAML file
- new: web server examples (Caddy, nginx, Apache) in `/etc/opensim/grids/<grid>/web/`
- new `opensim oar pack|info|check|unpack` from sources in `share/ossl-scripts/fix-parcel-name-src`

- update: `opensim import [grid] FILE` handles quick setup, full install and bulk import
- refactor: commands share one form, `opensim <command> <instance> [action] [options]`
- feat: terminal shortcuts in the setup (Escape, Ctrl-Q, Ctrl-U, Ctrl-K, Ctrl-W...)
- feat: import accounts can be given with their password hash and salt
- update(setup): password read from config if db user matches one already known
- update(setup): advanced setup groups related questions
- fix: name slugs as snake_case do avoid possible conflicts
- fix: two grids no longer share a map position (first location from the public port)
- fix: a second simulator reuses the database password of the same user
- fix: the search index of a simulator carries its grid name, not OSGrid
- fix: the owner account is made when it has no email
- fix: economy url passed to the viewer (pass base path, not currency.php)
- fix: disable query.php API endpoint was passed for search URL instead of proper search url
- fix: disable offline.php was passed as message of the day url
- fix: first account is optional (OpenSimulator does not require one)
- fix: adding a region no longer asks for a restart, its parcel is no longer renamed by one
- fix: `opensim stop` counts down to the shutdown, not to the next warning
- update `opensim stop` does not wait when no real user is in the regions
- add OSSL is in the standard config
- new `opensim profile list|add|default|remove`
- new: gettext localization of the setup, catalogue in `locales/opensim-kit.pot`
- new `locales/update-pot` script to refresh translations from `.po` files
- new: `opensim import robust|sim FILE` brings the config of an existing setup
- new `opensim users import FILE [--grid NICK] [--apply]`
- new(build): package `opensim-helpers`
- new(build): package `opensim-web` (minimalistic setup for helpers)
- new `INSTALLATION.md` with detailed instructions (packages, git)
- new `opensim enable <instance>` and `opensim disable <instance>`
- fix: scripts use the bash-tools of the kit, not another one in the `PATH`
- fix the owner of the first estate gets the default region as home
- remove package `opensim-manfredaabye-helpers`
- update `opensim` bash completion

### 3.0.0-beta.2

- new: simulators and regions from `opensim setup`, one database per simulator
- new: ports by block of ten, `opensim ports [--publish|--ufw]` tells what to open
- new: remote (REST) console by default, `opensim rest` reaches another machine
- new: a simulator can join a grid run on another machine or in another container
- new: `opensim console <instance>` (or `screen`), `opensim command <instance> <text>`
- new: package `opensim-manfredaabye-helpers`
- new: free blocks between regions (`RegionSpacing`), nearest free place proposed
- new: progress of a start (services, plugins, database, regions) on one line, countdown on stop
- new: first simulator and region `Welcome`; roles (default, default HG, fallback) as a checklist
- new: random name proposed for a simulator or a region (`Gentle Gnat`), accents transliterated
- new: `opensim next port` and `opensim next location` replace `nextfreeports` and `nextlocation`
- update: the setup lands on what was just made, "Add region" proposed, Quit everywhere
- update: the setup restarts at once; a running simulator asks whether to warn its users
- update: a region is added through the console of a running simulator, no restart
- update: simulator ports start at 9000 (HTTP 9000, regions 9001...)
- update: the place proposed for a region is the first free one from the first place of the grid
- update: the setup goes one level deeper at a time (home, grid, simulator, region)
- update: `opensim-rest-cli` is the remote console client, `libexec/rest.php` is gone
- update: tests are in `tests/`, `vendor/bin/pest` runs them (`PACKAGING=1` for the packages)
- update: PHP libraries are composer packages loaded from the start, PHP 8.2 minimum
- update: a region starts without waiting for questions, one that dies is reported
- update: regions run from a read-only core on .NET, native libraries and stack found
- update: core packages depend on `libgdiplus`, `opensim install-dotnet` installs it
- update: the password of the account made in the grid can hold spaces
- fix: Unix line endings in the files made from OpenSim's (0.9.3.0 ships Windows ones)
- fix: OpenSim's default files are not read as instances when looking for free ports
- fix: enabling or disabling from the setup is done by the system user
- fix: `opensim start` launched simulators with the assembly of the previous instance
- fix: stopping a grid that was not running stopped a simulator with a similar name
- fix: stopping a simulator warns its users again, and only when it runs

### 3.0.0-beta.1

First release as packages: a grid from the wizard; simulators and regions still by hand.

- new: Debian and Ubuntu packages from the Magiiic apt repository
- new: standard layout under `/usr/share`, `/etc`, `/var/lib`, `/var/log` (`opensim`)
- new: `opensim` service starts the enabled instances at boot
- new: `opensim setup` wizard creates a grid, prepares its database, starts it
- new: the setup uses the database rights of its user, an admin account only when needed
- new: `opensim install-dotnet` installs the .NET 8 runtime
- new: `opensim start` waits for Robust's services, names those that failed
- update: `opensim` runs as the system user of the install, except `setup`
- update: an existing install (e.g. `/opt/opensim`) works with the packaged tools
- update: tools use [bash-tools](https://github.com/magicoli/bash-tools) 1.0.4 or later
- update the version of the kit restarts at 3.0.0

Not covered yet: simulators and regions from the setup, several Robust instances, modules with Robust.
