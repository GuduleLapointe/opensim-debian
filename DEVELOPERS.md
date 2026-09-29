Developers
==========

Packaging
---------

The packages are built with [nfpm](https://nfpm.goreleaser.com) and published to the Magiiic apt repository with the tools of apt-repo (`git@git.magiiic.com:magic/apt-repo.git`, cloned in `/opt/apt-repo`), whose README documents the whole chain. Everything lives in `packaging/`, one definition per package:

| Package | Definition | Version | Content |
| --- | --- | --- | --- |
| `opensim-tools` | `opensim-tools.yaml` | git tag of this repository | the tools, in `/usr/share/opensim-tools`, the `opensim` command, the service |
| `opensim-<version>` | `opensim-core.yaml` | OpenSimulator release + revision | an OpenSimulator release, in `/usr/share/opensim/<version>`, for amd64 and arm64 |
| `opensim` | `opensim.yaml` | same as the latest release | metapackage, the latest release |
| `opensim-kit` | `opensim-kit.yaml` | git tag of this repository | metapackage, the tools and the latest release |

The tools and the releases install without each other. `opensim-tools` adds an install profile for each release in `/usr/share/opensim`, and removes it with the release, through a dpkg trigger.

### Building and publishing

```bash
/opt/apt-repo/bin/apt-package                          # every package, into dist/
/opt/apt-repo/bin/apt-package opensim-tools            # only this one
/opt/apt-repo/bin/apt-package --publish                # publish, on a version tag of this repository
/opt/apt-repo/bin/apt-package --publish opensim-core opensim   # a new release, without a tag
```

Publishing skips the packages whose version is already in the repository, so the tools and the releases keep their own pace. The packages with the version of this repository need a version tag (bare number, e.g. `3.0.0`), and are attached to its GitHub release.

`packaging/build` runs first and prepares only the packages asked for:

- `opensim-tools`: `build/opensim-tools` from the committed tree (`git archive HEAD`), with the PHP dependencies (`composer install --no-dev`), without the paths listed in `packaging/distignore`. Uncommitted changes are not packaged.
- `opensim-core`, `opensim`: the release of `packaging/opensim-core.versions`, the last one or `OPENSIM_VERSION`, e.g. `OPENSIM_VERSION=0.9.3.0 /opt/apt-repo/bin/apt-package opensim-core`. Its tarball is downloaded into `src/` and checked against its SHA256.

A new OpenSimulator release is added at the end of `packaging/opensim-core.versions`, with its checksum. A published package is never changed: a packaging change of a published release gets the next revision in that file.

The maintainer scripts of `opensim-tools` create the `opensim` account and the folders of the system layout, write the default `/etc/opensim/opensim.conf` when missing, and handle the `opensim` service like `dh_installsystemd`: enabled on first install, stopped with the instances on removal, never restarted by upgrades.

The scripts use the helpers of [bash-tools](https://github.com/magicoli/bash-tools): the `bash-tools` package, a dependency of `opensim-tools`, or composer's copy in a git checkout (`composer install`). It is a development dependency of composer, so the packages don't bundle it. During development, composer links the local `../bash-tools` checkout when there is one (path repository, `dev-dev`): switch to a released version before a release.

The PHP dependencies are locked for PHP 8.1 (`config.platform.php` in `composer.json`), the oldest PHP of the supported systems (Ubuntu 22.04).

The releases are installed read-only, so nothing may be written in their `bin/` folder at run time. OpenSim writes its platform `System.Drawing.Common.dll` there on start when it differs: the build puts it in place beforehand.

### Tests

`packaging/test/run` installs the packages of `dist/` in a container with systemd (podman): a release alone then the tools, a grid made by the wizard and run by the service, then upgrade, removal of the tools and of the release, and purge:

```bash
packaging/test/run                                   # Debian 12
packaging/test/run docker.io/library/ubuntu:24.04    # Ubuntu 24.04
```
