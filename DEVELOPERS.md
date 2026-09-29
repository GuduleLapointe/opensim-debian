Developers
==========

Packaging
---------

The packages are built with [nfpm](https://nfpm.goreleaser.com) and published to the Magiiic apt repository with the tools of apt-repo (`git@git.magiiic.com:magic/apt-repo.git`, cloned in `/opt/apt-repo`), whose README documents the whole chain. Everything lives in `packaging/`.

### opensim-kit

Built by `apt-package` from `packaging/opensim-kit.yaml`, with the version of the git tag (bare number, e.g. `3.0.0`):

```bash
/opt/apt-repo/bin/apt-package             # build into dist/
/opt/apt-repo/bin/apt-package --publish   # on a version tag: publish, and attach to the GitHub release
```

`packaging/build` runs first: it prepares `build/opensim-kit` from the committed tree (`git archive HEAD`), with the `libexec/bash-helpers` submodule and the PHP dependencies (`composer install --no-dev`), without the paths listed in `packaging/distignore`. Uncommitted changes are not packaged.

The maintainer scripts (`packaging/opensim-kit.*`) create the `opensim` account and the folders of the system layout, write the default `/etc/opensim/opensim.conf` when missing, and handle the `opensim` service like `dh_installsystemd`: enabled on first install, stopped on removal, never restarted by upgrades.

The PHP dependencies are locked for PHP 8.1 (`config.platform.php` in `composer.json`), the oldest PHP of the supported systems (Ubuntu 22.04).

### OpenSimulator distributions

`opensim-<version>` and the `opensim` metapackage have their own version, the OpenSimulator one plus a revision, so `apt-package` doesn't build them:

```bash
packaging/core/build 0.9.3.0      # dist/opensim-0.9.3.0_0.9.3.0-1_{amd64,arm64}.deb, dist/opensim_0.9.3.0-1_all.deb
packaging/core/build 0.9.3.0 2    # revision 2, after a packaging fix
/opt/apt-repo/bin/apt-publish dist/opensim-0.9.3.0_0.9.3.0-1_*.deb dist/opensim_0.9.3.0-1_all.deb
```

A new OpenSimulator version is added to `packaging/core/SHA256SUMS`, which the build checks the downloaded tarball against. The metapackage follows the latest version of that file. A published package is never changed: a fix gets the next revision.

The core is installed read-only, so nothing may be written in its `bin/` folder at run time. OpenSim writes its platform `System.Drawing.Common.dll` there on start when it differs: the build puts it in place beforehand.

### Tests

`packaging/test/run` installs the packages of `dist/` in a container with systemd (podman), makes a grid with the wizard and runs it, then checks upgrade, removal and purge:

```bash
packaging/test/run                                   # Debian 12
packaging/test/run docker.io/library/ubuntu:24.04    # Ubuntu 24.04
```
