# Installation

The kit is installed from the packages of the Magiiic apt repository, or from a clone of this repository. The steps in short are in the [README](README.md); the details are here.

## From packages (Debian, Ubuntu)

The kit and the OpenSimulator distributions come from the Magiiic apt repository. Add it once:

```bash
curl -fsSL https://apt.magiiic.com/magiiic-packaging.asc | sudo gpg --dearmor -o /usr/share/keyrings/magiiic-packaging.gpg
echo "deb [signed-by=/usr/share/keyrings/magiiic-packaging.gpg] https://apt.magiiic.com stable main" | sudo tee /etc/apt/sources.list.d/magiiic.list
sudo apt update
```

Then install the kit, the tools with the latest OpenSimulator:

```bash
sudo apt install opensim-kit
```

- `opensim-tools`: the tools, in `/usr/share/opensim-tools`, and the `opensim` command. They work with the packaged OpenSimulator releases or any other one.
- `opensim-<version>`, e.g. `opensim-0.9.3.0`: an OpenSimulator release, in `/usr/share/opensim/<version>`. Several releases can be installed side by side, with or without the tools.
- `opensim`: the latest release
- `opensim-<version>-<module>`, e.g. `opensim-0.9.3.0-gloebit`, `opensim-0.9.3.0-opensimsearch`: a module for a release. Installing it does not enable it. It works with any build of the same version.
- `opensim-unstable`: OpenSimulator built from the development branch, in `/usr/share/opensim/unstable`
- `opensim-helpers`: the helpers of a grid (economy, search, offline messages), in `/usr/share/opensim-helpers`, to be served by a web server. Their settings are read from the kit (`/etc/opensim/grids/<grid>/helpers.ini`, written by the setup), nothing to edit in the package. Any other implementation of the helpers (w4os, the fork of Kevin Cozens, wiredux) works with the grid too: the choice is yours.
- `opensim-web`: a placeholder site for the grid (its name, how to connect, where its services are), until it has its own.
- `opensim-kit`: the tools, the latest release, its essential modules and a database server. The other modules are in the repository, not installed automatically.

OpenSim and Robust cannot run without a database. The packages of OpenSimulator accept MySQL/MariaDB or PostgreSQL (and SQLite for regions), on this machine or another one; the setup of this kit works with MySQL/MariaDB (`opensim-kit` installs the server). The setup runs as the user who starts it, with that user's own rights on the database (or as root, `sudo opensim setup`): only the writing of the files and the start of the instances are done as the system user of the install, through `sudo -u`, as every other command. Before writing anything, it checks the account can log in, then that it can use the database. The account of the grid is always tried with its own credentials only, as OpenSimulator will, whatever is in the `~/.my.cnf` of the user. Only what is missing is created, one statement at a time, with an administrator account of the database server: the setup uses the access the user already has (root, or a database account of their own that may create accounts and databases, e.g. in their `~/.my.cnf`, or sudo without a password), and otherwise asks for the credentials of an administrator account, on this machine or another one, kept in memory for the session only. A problem of access can be tried again; a creation that fails ends the setup with the commands to run from an administrator account. A database prepared by someone else, with the credentials, needs none of it.

The files are at the usual places: config in `/etc/opensim`, data in `/var/lib/opensim`, logs in `/var/log/opensim`, cache in `/var/cache/opensim`. The instances run as the `opensim` account: the `opensim` command switches to it, through sudo.

OpenSimulator 0.9.3 needs the .NET 8 runtime and the native library `libgdiplus`, which the packages install (a region stops at once without it, the launcher says so). Ubuntu installs the runtime with the package; on Debian, install it (and `libgdiplus`, for an install that did not come from the packages) with:

```bash
sudo opensim install-dotnet
```

The setup asks whether the economy and the search of the grid are served by opensim-helpers, and asks for their path after the web URL (`/helpers` by default): the URL of the helpers is `{web URL}{path}` (the viewers add the name of the script). The economy of the grid is that URL, the in-world search is `query.php` under it (`SearchURL` of Robust). A path of its own for a script (`/search`, `/guide`...) is set in the `helpers.ini` of the grid.

```bash
opensim web                       # where the services are
opensim web check                 # which ones answer
opensim web snippet caddy         # what the web server needs: caddy, nginx or apache
```

The setup writes them for the grid, one file for each server, in `/etc/opensim/grids/<grid>/web/` (`<grid>.caddyfile`, `<grid>-nginx.conf`, `<grid>-apache.conf`); `opensim web snippet` writes them again after a change. Include the one of your server in the site of the grid (it names the grid to the helpers, aliases the paths you chose and denies what is not for the web); the config of your web server is not touched.

Create a grid with the setup wizard, then start it. The quick setup is what to choose first: one form (the name of the grid, its owner with a password and an optional email, the user and password of the database), the usual settings for everything else, which are shown before anything is written. The advanced setup asks every question.

```bash
opensim setup
sudo systemctl start opensim
```

The keys of a terminal work in the wizard: `Escape` gives up the screen and goes back to the menu it came from, `Ctrl-Q` leaves the setup from anywhere, and the editing keys of readline work in texts and passwords: `Ctrl-U`, `Ctrl-K`, `Ctrl-W` and `Alt-D` cut (a password too), `Ctrl-Y` pastes, `Ctrl-A`, `Ctrl-E`, `Alt-B`, `Alt-F` and the arrows move, `Ctrl-D` and `Ctrl-T` edit.

A setup can also be described in a file, JSON or YAML (the example is `share/examples/setup.yaml`, in `/usr/share/opensim-tools/share/examples/`): the grid, its owner, its simulators and regions, and the accounts to make, in the format of the bulk import. `opensim setup --file setup.yaml` makes it, with the questions of the wizard answered from the file (`--check` only reads it); `opensim users import setup.yaml` makes only its accounts. What the wizard does is kept in the folder of the grid, `setup.json` (readable by its owner only, it holds the passwords), to make the same grid again.

The wizard goes one level deeper at a time (the core, the grids, a grid, a simulator, a region), and every screen ends with `Back` and `Quit`. Once the grid is made it lands on its screen, ready to add a simulator (`Add simulator`): it has its own database and its first region, and the owner of its estate is an account of the grid, chosen or created on the way (in the console of Robust, which is started for that if needed). The wizard writes the config of the simulator in `/etc/opensim/grids/<grid>/sims/`, links it into `/etc/opensim/opensim.d`, starts it and checks that its region is online in the grid. The first simulator and its first region are called `Welcome` unless you choose another name, and the region is the one visitors arrive in (the default region of the grid): without one nobody can log in. More regions are added from the screen of the simulator (`Add region`), through its console when it runs; what has to restart (a new region, whose parcel is named after it, or a region moved, enabled or disabled) is listed, and the wizard offers to restart it when you quit. `opensim enable <instance>` and `opensim disable <instance>` do from the command line what the screens do.

On a machine that has no Robust of its own (a grid run elsewhere, on another host or in another container), `Add grid`, `Connect to an external grid`, needs no grid created first: it asks for the address of the grid and its public port, reads its name and nick from `get_grid_info`, asks for its private port (which only its simulators reach), and keeps that description under the nick of the grid for the next simulators (`/etc/opensim/grids/<nick>/<nick>.conf`). The estate of such a simulator is owned by an account of the grid that already exists, and the address the regions announce (the viewers connect to it) is asked, as `SYSTEMIP`, the first interface of the machine, is not the one of the world behind a NAT or in a container.

The ports of an instance are a block of ten, the same on any machine, so an instance can move to another one without changing any port. The REST console is always the port x4 of the block. Robust has a public port, ending with 2, and a private one, ending with 3 (the one its simulators use, which has no protection of its own): 8002, 8003, and 8004 for its console. A simulator has a single HTTP port, the first of its block, and its regions take the other ports: the first simulator is on 9000, its console on 9004, its regions on 9001, 9002, 9003 and 9005 to 9009, the next simulator on 9010... Simulators are looked for from 9000, where the users are used to find them: the thousand above their grid, in its hundred, so a grid whose Robust is on 8102 has its simulators from 9100. The next free block is chosen looking at the config files of the machine, the ports in use, and the regions the grid knows on its other machines. `opensim ports` tells what the enabled instances listen on and who has to reach it: the public ports (and the regions, in UDP, which are the ones the viewers send to) for everyone, the private port of Robust (which has no protection of its own) and the consoles only for the machines that need them. `opensim ports --publish` gives the `-p` options of a container, `--ufw` the rules of a firewall. `opensim next port [-e PORT] [MIN] [COUNT]` gives the next free ports, and `opensim next location [-e X,Y] GRID [X,Y] [COUNT]` the free places nearest to the one asked in a grid, by the same rules as the setup, for scripts.

The console of an instance is one of two kinds, asked by the wizard (remote by default). The remote one (REST) is reached through a port, x4 of the block of the instance, with a user and a password the wizard makes: it gives every right on the instance, so it works the same from this machine, from a container or from another machine, and its port is only published to the machines that need it (`opensim ports --publish` keeps it to the local one). The other is a screen session, attached on the machine that runs the instance.

`opensim console <instance>` (`screen` works too) attaches the screen session, or opens a prompt on the remote console, which `Ctrl-D` leaves. `opensim command <instance> <text>` sends a single command, or the lines of its standard input with `-`, from a script. For an instance of another machine or a container, by its address: `OPENSIM_REST_PASSWORD=<password> opensim rest --url http://host:8024 --user admin -- show info`. A remote console controls an instance that runs: it can stop it, but not start it again (that is for the service, or the container), nor change its files.

The `opensim` service starts the enabled instances at boot. Package upgrades never restart them.

An existing install, e.g. in `/opt/opensim`, keeps working with the packaged tools without moving anything: its `opensim.conf` gives the locations. To run it with the service, set its account with `sudo systemctl edit opensim` (`User=` and `Group=` in a `[Service]` section).

## From git

Requires PHP 8.2 or newer, with the `ctype`, `iconv`, `json` and `mbstring` extensions, and those of the engine: `curl`, `filter`, `gettext`, `intl`, `mysqli`, `pdo`, `session` and `simplexml`. `posix` is used when available. The packages install what they need.

On Debian and Ubuntu: `sudo apt install php-cli php-curl php-intl php-mbstring php-mysql php-xml`.

```shell
git clone --recursive https://github.com/GuduleLapointe/opensim-kit.git
cd opensim-kit
composer install   # PHP libraries and bash-tools
export PATH=$PATH:$PWD/bin
opensim setup
```

Answer the common setting questions.

Configuration should be working as is, but you will probably want to adjust

- ./etc/opensim/opensim.conf
  (main database configuration)
- ./etc/opensim/robust.d/*.ini (robust settings)
- ./etc/opensim/opensim.d/*.ini (simulators settings)

```shell
opensim start
```

To enable bash completion:

```shell
sudo apt update
sudo apt install bash-completion
sudo ln -s $PWD/share/bash_completion.d/opensim /etc/bash_completion.d/
```
