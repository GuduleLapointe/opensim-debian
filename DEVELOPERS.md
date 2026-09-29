# Developers

## Packaging

The packages are built with [nfpm](https://nfpm.goreleaser.com) and published to the Magiiic apt repository with the tools of apt-repo (`git@git.magiiic.com:magic/apt-repo.git`, cloned in `/opt/apt-repo`), whose README documents the whole chain. Everything lives in `packaging/`, one definition per package:

| Package                      | Definition              | Version                          | Content                                                                                              |
| ---------------------------- | ----------------------- | -------------------------------- | ---------------------------------------------------------------------------------------------------- |
| `opensim-tools`              | `opensim-tools.yaml`    | git tag of this repository       | the tools, in `/usr/share/opensim-tools`, the `opensim` command, the service                         |
| `opensim-<version>`          | `opensim-core.yaml`     | OpenSimulator release + revision | an OpenSimulator release, in `/usr/share/opensim/<version>`, for amd64 and arm64                     |
| `opensim`                    | `opensim.yaml`          | same as the latest release       | metapackage, the latest release                                                                      |
| `opensim-kit`                | `opensim-kit.yaml`      | git tag of this repository       | metapackage, the tools and the latest release                                                        |
| `opensim-unstable`           | `opensim-unstable.yaml` | source version, date and commit  | OpenSimulator built from the `upstream/opensim` submodule, in `/usr/share/opensim/unstable`          |
| `opensim-<core>-<module>`    | `opensim-<module>.yaml` | module version + revision        | a module for a release or `unstable`, in `/usr/share/opensim-modules/<core>/<module>`, not loaded until enabled |

The tools and the releases install without each other. `opensim-tools` adds an install profile for each release in `/usr/share/opensim`, and removes it with the release, through a dpkg trigger.

The modules are installed in their own folders, as some fail when loaded without their config: enabling them is up to the admin.

## Building and publishing

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

- `opensim-unstable`: OpenSimulator built from the commit of the `upstream/opensim` submodule (a shallow clone of the upstream repository, `master`), in a .NET SDK container (podman, or `CONTAINER=docker`). Each commit is built once, into `build/cache/`. Update it with `git submodule update --remote upstream/opensim`.
- `opensim-<module>`: the files listed for the module in `packaging/opensim-modules.versions`, for the release of `opensim-core`, or the core of `MODULES_CORE` (a release or `unstable`), e.g. `MODULES_CORE=unstable /opt/apt-repo/bin/apt-package opensim-gloebit`. The files downloaded go to `src/`, checked against their SHA256. The packages are named after the modules.

A new OpenSimulator release is added at the end of `packaging/opensim-core.versions`, with its checksum. A published package is never changed: a packaging change of a published release gets the next revision in that file, as for the modules in `packaging/opensim-modules.versions`.

The maintainer scripts of `opensim-tools` create the `opensim` account and the folders of the system layout, write the default `/etc/opensim/opensim.conf` when missing, and handle the `opensim` service like `dh_installsystemd`: enabled on first install, stopped with the instances on removal, never restarted by upgrades.

The scripts use the helpers of [bash-tools](https://github.com/magicoli/bash-tools): the `bash-tools` package, a dependency of `opensim-tools`, or composer's copy in a git checkout (`composer install`). It is a development dependency of composer, so the packages don't bundle it. During development, composer links the local `../bash-tools` checkout when there is one (path repository, `dev-dev`): switch to a released version before a release.

The PHP dependencies are locked for PHP 8.1 (`config.platform.php` in `composer.json`), the oldest PHP of the supported systems (Ubuntu 22.04).

The releases are installed read-only, so nothing may be written in their `bin/` folder at run time. OpenSim writes its platform `System.Drawing.Common.dll` there on start when it differs: the build puts it in place beforehand.

## Tests

`packaging/test/run` installs the packages of `dist/` in a container with systemd (podman): a release alone then the tools, a grid made by the wizard and run by the service, then upgrade, removal of the tools and of the release, and purge:

```bash
packaging/test/run                                   # Debian 12
packaging/test/run docker.io/library/ubuntu:24.04    # Ubuntu 24.04
```

## References

- http://opensimulator.org
- https://github.com/opensim/opensim
- http://opensimulator.org/wiki/UnofficialDebPackages
- http://opensimulator.org/wiki/Related_Software

**Main modules**

- **Search**: https://github.com/kcozens/OpenSimSearch (very old, but it still seems to be the source for builds)

**Currency modules**

Active:

- **Gloebit**: https://github.com/gloebit/opensim-moneymodule-gloebit
  - Compiled dlls https://github.com/gloebit/opensim-moneymodule-gloebit/releases
- **PayPal**: https://github.com/Outworldz/DTL-PayPal
- **DTL/NSL**: https://github.com/MTSGJ/opensim.currency
- **Podex Exchange**: https://www.podex.info/p/setting-up-money-server.html (service active, but see below for dll module)

Outdated:

- **Generic MoneyServer**: used by Podex Exchange and other money implementations as well as for fictional currencies. The Original svn repo vanished (http://www.nsl.tuis.ac.jp/svn/opensim/opensim.currency/trunk). Podex website mentions https://github.com/MTSGJ/opensim.currency, which is active, but has a disclaimer to investicate: "Web Monitor function (ASP.NET) is removed from original DTL Currency. So, this version is less secure than original version!! Please use this at Your Own Risk!!". Many other forks were made by various grid operators, a comparison of them could be useful to determine which is the best option to include or if we should create our own.
- **Virwox** (abandonned), as well as OMEconomy-Modules, make sure to remove any remaining references

**Additional tools**

- https://github.com/GuduleLapointe/opensim-helpers/
- https://github.com/GuduleLapointe/w4os/
- http://opensimulator.org/wiki/Webinterface
- https://github.com/Outworldz/os-webrtc-janus (webrtc voice)

**Alternative distributions**

- https://www.osgrid.org/download (most popular)
- https://github.com/Outworldz/DreamGrid-Opensim (dist 7.2 but based on opensim 0.9.3.1)
- https://metaverseink.com/Downloads.html (outdated 0.9.2.0), https://github.com/diva (outdated)
