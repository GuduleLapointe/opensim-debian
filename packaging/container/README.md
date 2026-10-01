# OpenSimulator in a container

The tools, a release of the core and its modules, from the packages of the kit,
on Ubuntu LTS. **Beta**: the image is built on the machine that uses it, it is
not published anywhere.

```bash
packaging/container/build            # from the packages of dist/, image opensim-kit:dev
tests/Packaging/container-image             # checks it (podman, about ten minutes)
```

`ENGINE=docker` for docker, `BASE=<image>` for another Ubuntu or Debian base
(with the .NET runtime in its repositories).

## Run

There is no database server in the image: OpenSimulator needs a MySQL or
MariaDB, in another container or on the host.

```bash
podman network create opensim
podman run -d --name db --network opensim -e MARIADB_ROOT_PASSWORD=<root password> docker.io/library/mariadb:11

podman run -d --name opensim --network opensim --stop-timeout 120 \
    -v opensim-etc:/etc/opensim:z -v opensim-data:/var/lib/opensim:z -v opensim-logs:/var/log/opensim:z \
    $(opensim ports --publish) \
    opensim-kit:dev
```

The `:z` of the volumes matters on a host with SELinux (Fedora, RHEL, the machine of podman on a Mac): without it, a container made again with the same volumes (a new image, a new option) cannot read what the previous one wrote, as each container has its own label, and its instances stop with "Permission denied". Docker on a host without SELinux ignores it.

`opensim ports --publish`, run on the machine that holds the config, gives the
`-p` options for exactly the ports the instances use: the public ports, the
regions in UDP (the viewers send to them, and the number is the one they are
given, so it is the same outside), and the private and console ports only to the
local machine (`--private-address=` to change it). From inside the container:
`podman exec opensim opensim ports --publish`. A simulator of this container can
reach a Robust elsewhere, and a Robust here serves simulators elsewhere, only when
their ports are published to them.

The container starts the enabled instances, shows their logs (`podman logs -f
opensim`, each line with the name of its instance) and stops them cleanly when it
is stopped: give it time, a region takes a while to stop (`--stop-timeout`).
With nothing set up yet it waits.

Set up a grid and its simulators from inside, with the same wizard as anywhere
else (the host of the database is the name of its container, and the
administrator account is asked for what has to be created):

```bash
podman exec -it opensim opensim setup
podman exec -it opensim opensim console <instance>     # Ctrl-A D leaves it
podman exec opensim opensim status
```

## What is in it

- The instances run as `opensim`, ID 10001 (a folder of the host shared with the
  container is given to it with `chown 10001:10001`).
- `/etc/opensim`, `/var/lib/opensim` and `/var/log/opensim` are volumes; the
  cache can be a tmpfs.
- The console sessions (screen) are in `/var/cache/opensim/screen`
  (`SCREENDIR`), so nothing is written elsewhere than the volumes and `/tmp`.
- The modules `opensimsearch` and `gloebit` are installed and enabled, as with
  `opensim-kit`.

## Locked down

The same container, with a read-only system, no capability and no new privilege
(checked by `tests/Packaging/container-image`):

```bash
podman run -d --name opensim --network opensim --stop-timeout 120 \
    --read-only --tmpfs /tmp --tmpfs /var/cache/opensim:rw,mode=1777 \
    --cap-drop ALL --security-opt no-new-privileges --pids-limit 600 --memory 2g \
    -v opensim-etc:/etc/opensim:z -v opensim-data:/var/lib/opensim:z -v opensim-logs:/var/log/opensim:z \
    opensim-kit:dev
```

The container protects the host from OpenSimulator, which runs scripts written
by its users: it does not replace the settings of OpenSimulator that keep those
scripts away from your internal network.

## Not done yet

- Starting, stopping and restarting one instance of a container that holds several: for now
  one service by container, restarted with its container. The instances are reached through
  their remote console (`opensim rest`, see the README of the kit), which can stop them but
  not start them.
- The public address of the regions: their external host name is the one the
  container sees, not the one of the host.
- A compose file, and publication of the image.
