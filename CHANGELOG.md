## Changelog

### Unreleased

- new simulators and regions from `opensim setup`: a simulator has its own database (created with the administrator account when it does not exist) and an estate whose owner is an account of the grid, chosen or created through the console of Robust; the wizard writes its config, links it into `opensim.d`, starts it and checks its region is online in the grid. More regions are added to a running simulator from the same menu, without restarting it
- new `opensim console <instance>` (or `screen`) attaches to the console of an instance, screen or remote console; `opensim command <instance> <text>` sends a command from a script
- new package `opensim-helpers-search`, the web part of the OpenSimSearch module (the search service the viewers query), from the sources of the `manfredaabye` fork; its settings are in `/etc/opensim/helpers/search`
- update starting a region no longer waits two minutes for questions it does not ask: a region fully described is up in seconds, and a region that dies right after loading is reported as a failed start
- update regions run from a read-only core on .NET: their native libraries (physics, OpenJPEG) are found, and their stack is the one of `opensim.sh`
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
