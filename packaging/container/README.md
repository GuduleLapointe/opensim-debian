# OpenSimulator in a container

The tools, a release of the core and its modules, from the packages of the kit,
on Ubuntu LTS. **Beta**: the image is built on the machine that uses it, it is
not published anywhere.

```bash
packaging/container/build            # from the packages of dist/, image opensim-kit:dev
packaging/container/test             # checks it (podman, about ten minutes)
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
    -v opensim-etc:/etc/opensim -v opensim-data:/var/lib/opensim -v opensim-logs:/var/log/opensim \
    -p 8002:8002 -p 9000-9099:9000-9099 -p 9000-9099:9000-9099/udp \
    opensim-kit:dev
```

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
(checked by `packaging/container/test`):

```bash
podman run -d --name opensim --network opensim --stop-timeout 120 \
    --read-only --tmpfs /tmp --tmpfs /var/cache/opensim:rw,mode=1777 \
    --cap-drop ALL --security-opt no-new-privileges --pids-limit 600 --memory 2g \
    -v opensim-etc:/etc/opensim -v opensim-data:/var/lib/opensim -v opensim-logs:/var/log/opensim \
    opensim-kit:dev
```

The container protects the host from OpenSimulator, which runs scripts written
by its users: it does not replace the settings of OpenSimulator that keep those
scripts away from your internal network.

## Not done yet

- Driving the server of a container from the tools installed on the host.
- The public address of the regions: their external host name is the one the
  container sees, not the one of the host.
- A compose file, and publication of the image.
