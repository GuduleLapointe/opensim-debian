## Changelog

### Unreleased

- new simulators and regions from `opensim setup`: a simulator has its own database (created with the administrator account when it does not exist) and an estate whose owner is an account of the grid, chosen or created through the console of Robust; the wizard writes its config, links it into `opensim.d`, starts it and checks its region is online in the grid. More regions are added to a running simulator from the same menu, without restarting it
- new the ports of an instance are a block of ten, the first free one, looking at the config files, the ports in use and the regions the grid knows on its other machines: Robust has its public port on x2 and its private one on x3, a simulator its HTTP port on x0 and its regions on the others, the console of any instance on x4; `opensim ports` tells what each instance listens on and who has to reach it, `--publish` gives the options of a container, `--ufw` the firewall rules
- new the console of an instance is asked by the wizard, remote (REST, the default: a user, a password and a port x4 of its block, reachable from another machine or a container) or a screen session; the launcher starts, stops and reaches an instance with a remote console as with a screen one, and `opensim rest --url http://host:port --user user` reaches one of another machine or a container
- new a simulator joins a grid whose Robust is on another machine or in another container from the same `Sim` menu, with no grid created first: its address and ports are asked (its name and nick are read from `get_grid_info`) and kept for the next simulators; the address the regions announce is asked too
- new `opensim console <instance>` (or `screen`) attaches to the console of an instance, screen or remote console; `opensim command <instance> <text>` sends a command from a script
- new package `opensim-manfredaabye-helpers`, the helpers of the OpenSimSearch module (the search service the viewers query), from the sources of the `manfredaabye` fork, which is another implementation than opensim-helpers; its settings are in `/etc/opensim/manfredaabye-helpers`
- new the regions of a grid leave free blocks between them (the rule, asked with the grid, `RegionSpacing` in its config, 0: side by side): the simulator wizard asks the place of the region (1000,1000 by default), then takes the free place nearest to it by the rule and asks to confirm it when it is not the one asked. A grid whose Robust is on another machine is asked over HTTP what its regions take around the place, and follows the default rule
- new the start of an instance shows how it loads: the services of a Robust are counted (`AssetServiceConnector 1/22`), a simulator shows the plugins of modules it loads (`OpenSim.Region.CoreModules 11/15`), the database it brings up to date on a first start (`Database RegionStore 52/66`), then each region configured, in the grid and ready (`Sim1: registered in the grid (1/2)`)
- new the default region of a grid (where visitors arrive and fall back to) is chosen in the setup of the grid, `Welcome` by default, and is the first region created on a grid run from this machine: without it a login ends with "destination not found"
- new `opensim next port [-e PORT] [MIN] [COUNT]` and `opensim next location [-e X,Y] GRID [X,Y] [COUNT]` give the next free ports and places by the rules of the setup (the same code); they replace the scripts `nextfreeports` and `nextlocation`, which had rules of their own
- fix the files made from the ones of OpenSim have Unix line endings: OpenSim 0.9.3.0 ships its ini files with Windows ones, which showed as `^M` in the config of a grid; the copies of a grid made before are cleaned when its setup runs again
- update the ports of a simulator are looked for from 9000, where the users are used to find simulators, not right after the ones of Robust: the first simulator is on 9000 with its regions on 9001, 9002..., the next one on 9010; the thousand above its grid, in its hundred (a grid on 8102 has its simulators from 9100)
- fix the copies of the files OpenSim ships (`OpenSimDefaults.ini` sets `http_listener_port = 9000`) are not read as instances when looking for free ports, they kept the block 9000 from being chosen
- update the setup goes one level deeper at a time: home (the core, each grid, Add grid), grid (configure, its simulators, add a simulator, enable or disable), simulator (reconfigure, enable or disable, its regions, add a region), region (reconfigure its place and port, enable or disable): the grid chosen is the one clicked, not the first. The console choice reads "Remote REST console (recommended)" or "Screen session (on this machine)"
- update the remote console commands (`opensim console`, `opensim command`, `opensim rest`) use `opensim-rest-cli` of the opensim-rest-php package, which has the options of the former client of the kit (`--ini`, `--host`, `--url`, `--wait`, standard input, `--repl`); `libexec/rest.php` is gone
- update the tests are all in `tests/` and `vendor/bin/pest` runs them: the PHP minimum and compatibility, the shell scripts (bashunit), and the packages and container image in containers when asked (`PACKAGING=1`)
- update the PHP libraries (engine, helpers, REST client) are composer packages, loaded from the start; PHP 8.2 is the minimum (Debian 12 and Ubuntu 24.04 have it), the packages depend on the PHP extensions the tools use, and the requirements are in the README
- update starting a region no longer waits two minutes for questions it does not ask: a region fully described is up in seconds, and a region that dies right after loading is reported as a failed start
- update regions run from a read-only core on .NET: their native libraries (physics, OpenJPEG) are found, and their stack is the one of `opensim.sh`
- update the packages of the OpenSimulator cores depend on `libgdiplus`, without which a region stops at once; the launcher says when it is missing, and `opensim install-dotnet` installs it
- update the password of the account made in the grid can hold spaces, and is typed in the console as it is given (no escape of screen is read in it, nor is it in the arguments of a process)
- fix `opensim start` launched the simulators with the assembly of the previous instance, i.e. as Robust, when it started several instances
- fix stopping or restarting a grid that was not running shut down a simulator whose name begins the same (`testgrid`, `testgrid_sim1`): a console is now reached only by the exact name of an instance
- fix stopping a simulator warns its users again, and only when it runs

### 3.0.0-beta.1

First release as packages. A grid can be created with the wizard, started and run; simulators and regions are still added by hand.

- new Debian and Ubuntu packages, from the Magiiic apt repository: `opensim-kit` (the tools, the latest OpenSimulator, its essential modules, a database server), `opensim-tools`, `opensim-<version>` (an OpenSimulator release, several side by side), `opensim`, `opensim-unstable`, and the modules `opensim-<version>-opensimsearch` and `-gloebit`, installed but not enabled
- new standard layout: releases in `/usr/share/opensim`, config in `/etc/opensim`, data in `/var/lib/opensim`, logs in `/var/log/opensim`, cache in `/var/cache/opensim`, instances run by the `opensim` account
- new `opensim` service, starts the enabled instances at boot, stopped with the package, never restarted by an upgrade
- new `opensim setup` wizard, creates a grid and its Robust configuration, checks then prepares its database step by step (account, database, rights), and can enable and start the grid, with an exit code that tells whether it runs
- new the setup uses the database rights of the user who starts it, no sudo needed; an administrator account is asked only when something has to be created and the user has no access to do it
- new `opensim install-dotnet`, installs the .NET 8 runtime OpenSimulator 0.9.3 needs where the distribution does not provide it
- new `opensim start` waits until Robust has loaded every service its configuration lists, names the ones that failed, and exits with an error when an instance does not start
- update the `opensim` command runs as the system user of the install, through sudo, for every command but `setup`
- update an existing install (e.g. `/opt/opensim`) keeps working with the packaged tools, its `opensim.conf` gives the locations
- update the tools use [bash-tools](https://github.com/magicoli/bash-tools) 1.0.4 or later, as a package or through composer
- update the version of the kit restarts at 3.0.0

Not covered yet: creating simulators and regions from the setup, several Robust instances, and running the modules with Robust, which is not tested.
