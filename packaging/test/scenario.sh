#!/usr/bin/env bash
# Package test scenario, run by packaging/test/run inside the test container,
# with the packages in /dist.

FAILED=0
ts() { echo "[$(date +%T)] == $*"; }
check() {
    if eval "$2"; then
        echo "   ok: $1"
    else
        echo "   FAILED: $1"
        FAILED=1
    fi
}
apt_q() {
    DEBIAN_FRONTEND=noninteractive apt-get "$@" -y -qq 2>&1 |
        grep -E "opensim|needs|E:" | grep -vE "^(Selecting|Preparing|Unpacking)"
}
deb() { ls /dist/"$1"_*.deb | grep -E "_($(dpkg --print-architecture)|all)\.deb$" | tail -1; }
core=$(deb opensim-0.9.3.0)
tools=$(deb opensim-tools)
# The metapackages, with the local packages they depend on
metas="$(deb opensim) $(deb opensim-0.9.3.0-opensimsearch) $(deb opensim-0.9.3.0-gloebit) $(deb opensim-kit)"
# The dotnet process only: the screen session running it has the same arguments
robust_pid() { pgrep -f "^dotnet .*Robust.dll" | head -1; }
robust_log() { ls /var/log/opensim/testgrid*.log 2>/dev/null | grep -v Stats | head -1; }
quits() { cat /var/log/opensim/testgrid*.log 2>/dev/null | grep -c '\[CONSOLE\] Quitting'; }
robust_state() { echo "   Robust pid: $(robust_pid || true), clean shutdowns: $(quits)"; }
profile() { grep -q '^\[opensim-0.9.3.0\]' /etc/opensim/opensim.conf 2>/dev/null; }

rm -f /usr/sbin/policy-rc.d # container images forbid service actions
# The Magiiic repository, for the dependencies (bash-tools), as in the README
curl -fsSL https://apt.magiiic.com/magiiic-packaging.asc | gpg --dearmor -o /usr/share/keyrings/magiiic-packaging.gpg
echo "deb [signed-by=/usr/share/keyrings/magiiic-packaging.gpg] https://apt.magiiic.com stable main" >/etc/apt/sources.list.d/magiiic.list
apt-get update -qq

ts "core alone"
apt_q install "$core"
check "core installed" "[ -f /usr/share/opensim/0.9.3.0/bin/OpenSim.exe ]"
check "no tools" "! command -v opensim >/dev/null && [ ! -e /etc/opensim ]"

ts "tools, then the metapackages"
apt_q install $tools
check "opensim account" "getent passwd opensim >/dev/null"
check "profile of the core" "profile"
check "default profile" "grep -q '^DefaultProfile = opensim-0.9.3.0' /etc/opensim/opensim.conf"
check "service enabled" "systemctl -q is-enabled opensim"
apt_q install $metas
check "opensim-kit installed" "dpkg -s opensim-kit >/dev/null 2>&1"

ts dotnet
opensim install-dotnet -y 2>&1 | tail -1
check ".NET runtime" "runuser -u opensim -- dotnet --list-runtimes | grep -q NETCore.App"

ts "grid from the wizard"
systemctl start mariadb
mysql -e "CREATE DATABASE testgrid_robust; CREATE USER opensim@localhost IDENTIFIED BY 'testpass';
    GRANT ALL ON testgrid_robust.* TO opensim@localhost;"
(cd /var/lib/opensim && runuser -u opensim -- php /test/newgrid.php | tail -1)
check "grid enabled" "[ -L /etc/opensim/robust.d/testgrid.ini ]"

# A database that cannot be used stops the setup before anything is written:
# as opensim, who has no administrator access to the database server
wizard() { # grid, password, [user]
    (cd /var/lib/opensim && TEST_GRID=$1 TEST_DB_PASSWORD=$2 TEST_NO_ENABLE=1 TEST_REPEAT=${TEST_REPEAT:-1} runuser -u "${3:-opensim}" -- php /test/newgrid.php 2>&1)
}
wizard Badgrid wrong >/tmp/badgrid.out
check "wrong password: setup stops, nothing written" "grep -q 'cannot run without its database' /tmp/badgrid.out && [ ! -e /etc/opensim/grids/badgrid ]"
wizard Missinggrid testpass >/tmp/missing.out
check "existing account, missing database: says so, stops" "grep -q 'no access to database missinggrid_robust' /tmp/missing.out &&
    grep -q 'CREATE DATABASE' /tmp/missing.out && [ ! -e /etc/opensim/grids/missinggrid ]"

# The password of an account, generated once, is proposed again in the same session
TEST_REPEAT=2 wizard Repeatgrid "" >/tmp/repeat.out
check "the generated password is kept for the session" "[ \"\$(grep -a 'Database password ->' /tmp/repeat.out | sort -u | wc -l)\" = 1 ] &&
    [ \"\$(grep -ac 'Database password ->' /tmp/repeat.out)\" = 2 ]"

# As root, the administrator of the database: what is missing is created, and
# what the setup writes belongs to opensim
(cd /var/lib/opensim && TEST_GRID=Rootgrid TEST_DB_PASSWORD=testpass TEST_NO_ENABLE=1 php /test/newgrid.php >/tmp/rootgrid.out 2>&1)
check "as root: the missing database is created" "mysql -e 'SHOW DATABASES' | grep -q '^rootgrid_robust$'"
check "as root: the grid belongs to opensim" "[ \"\$(stat -c %U /etc/opensim/grids/rootgrid/Robust.HG.ini)\" = opensim ] &&
    [ \"\$(stat -c %U /var/lib/opensim/data/rootgrid)\" = opensim ]"
rm -rf /etc/opensim/grids/rootgrid /var/lib/opensim/data/rootgrid /var/cache/opensim/rootgrid
check "setup command" "opensim help | grep -q setup"

ts "service start"
systemctl start opensim
opensim status | tail -2
pid=$(robust_pid)
check "Robust running" "[ -n '$pid' ]"
check "grid core" "grep -q 'Starting in /usr/share/opensim/0.9.3.0/bin' $(robust_log)"
check "Robust ready" "grep -q 'UserAgentServerConnector loaded' $(robust_log)"
check "no write denied" "! grep -qiE 'denied|unauthorized' $(robust_log)"

ts upgrade
apt_q install --reinstall $tools "$core"
check "Robust not restarted" "[ '$(robust_pid)' = '$pid' ]"

ts "development build and modules"
check "modules installed by opensim-kit" "dpkg -s opensim-0.9.3.0-gloebit opensim-0.9.3.0-opensimsearch >/dev/null 2>&1"
unstable=$(deb opensim-unstable)
apt_q install "$unstable"
check "profile of the development build" "grep -q '^\[opensim-unstable\]' /etc/opensim/opensim.conf"
check "modules in their own folders" "[ -f /usr/share/opensim-modules/0.9.3.0/opensimsearch/OpenSimSearch.Modules.dll ] &&
    [ -f /usr/share/opensim-modules/0.9.3.0/gloebit/Gloebit.dll ] &&
    [ ! -e /usr/share/opensim/0.9.3.0/bin/OpenSimSearch.Modules.dll ]"
check "log config of the wizard" "[ -f /var/log/opensim/testgrid_robust.log ]"
grid_core() {
    runuser -u opensim -- crudini --inplace --set /etc/opensim/grids/testgrid/testgrid.conf Grid CoreDirectory "$1"
    opensim restart now testgrid >/dev/null 2>&1
}

# The grid on the development build
grid_core /usr/share/opensim/unstable
check "grid on its own core" "grep 'Starting in' $(robust_log) | tail -1 | grep -q /usr/share/opensim/unstable/bin"
check "development build ready" "[ \$(grep -c 'UserAgentServerConnector loaded' $(robust_log)) = 2 ]"

# Removing a build stops the instances running from it only
apt_q remove opensim-unstable
check "instances of a removed build stopped" "[ -z '$(robust_pid)' ]"
apt_q install "$unstable"
grid_core /usr/share/opensim/0.9.3.0
pid=$(robust_pid)
check "grid back on the release" "[ -n '$pid' ]"
apt_q remove opensim-unstable
check "instances of another core untouched" "[ '$(robust_pid)' = '$pid' ]"
apt_q install "$unstable"

ts "modules enabled for a region"
conf=/etc/opensim/opensim.conf
check "opensim-kit enabled the safe modules" "[ \"\$(crudini --get $conf Defaults EnabledModules)\" = 'opensimsearch gloebit' ]"
mkdir -p /etc/opensim/opensim.d /var/lib/opensim/data/testsim /var/cache/opensim/testsim
cat >/etc/opensim/opensim.d/testsim.ini <<'EOF'
[Const]
    DataDirectory = "/var/lib/opensim/data/testsim"
    LogsDirectory = "/var/log/opensim"
    CacheDirectory = "/var/cache/opensim/testsim"
[Startup]
    RegistryLocation = "${Const|DataDirectory}/registry"
EOF
chown -R opensim:opensim /etc/opensim/opensim.d /var/lib/opensim/data/testsim /var/cache/opensim/testsim
addins=/var/lib/opensim/data/testsim/registry/addins
opensim start testsim >/dev/null 2>&1; opensim stop now testsim >/dev/null 2>&1
check "modules linked for the region, from their packages" "[ \"\$(readlink $addins/Gloebit.dll)\" = /usr/share/opensim-modules/0.9.3.0/gloebit/Gloebit.dll ] &&
    [ -L $addins/OpenSimSearch.Modules.dll ]"
check "nothing written in the core" "[ -z \"\$(find /usr/share/opensim/0.9.3.0 -newer $conf -type f)\" ]"
crudini --inplace --set $conf Defaults EnabledModules opensimsearch
opensim start testsim >/dev/null 2>&1; opensim stop now testsim >/dev/null 2>&1
check "a module no longer enabled is unlinked" "[ ! -e $addins/Gloebit.dll ] && [ -L $addins/OpenSimSearch.Modules.dll ]"
crudini --inplace --set $conf Defaults EnabledModules "opensimsearch gloebit"
rm -f /etc/opensim/opensim.d/testsim.ini

ts "remove the tools, grid started by the service"
systemctl restart opensim
before=$(quits)
apt_q remove opensim-tools
robust_state
check "Robust stopped cleanly" "[ -z '$(robust_pid)' ] && [ '$(quits)' = $((before + 1)) ]"
check "core kept" "[ -f /usr/share/opensim/0.9.3.0/bin/OpenSim.exe ]"

ts "reinstall the tools"
apt_q install $tools
check "service enabled again" "systemctl -q is-enabled opensim"

ts "remove the tools, grid started by hand"
opensim start testgrid >/dev/null 2>&1
check "Robust running" "[ -n '$(robust_pid)' ]"
before=$(quits)
apt_q remove opensim-tools
robust_state
check "Robust stopped cleanly" "[ -z '$(robust_pid)' ] && [ '$(quits)' = $((before + 1)) ]"

ts "remove the core, tools installed"
apt_q install $tools
# A copy of the release elsewhere, running: the packaged one can go
cp -a /usr/share/opensim/0.9.3.0 /opt/custom-core
grid_core /opt/custom-core
pid=$(robust_pid)
check "grid on a core outside the packages" "[ -n '$pid' ]"
apt_q remove opensim-0.9.3.0
check "profile removed" "! profile"
check "instances of a custom core untouched" "[ '$(robust_pid)' = '$pid' ]"
opensim stop now >/dev/null 2>&1
check "modules kept without their core" "[ -f /usr/share/opensim-modules/0.9.3.0/gloebit/Gloebit.dll ]"
check "default falls back to the remaining build" "grep -q '^DefaultProfile = opensim-unstable' /etc/opensim/opensim.conf"
apt_q remove opensim-unstable
check "no default profile" "! grep -q '^DefaultProfile' /etc/opensim/opensim.conf"
check "tools work without a core" "opensim status >/dev/null"

ts purge
apt_q purge opensim-tools
check "cache removed" "[ ! -e /var/cache/opensim ]"
check "config, data and logs kept" "[ -d /etc/opensim/grids/testgrid ] && [ -d /var/lib/opensim/data ] && [ -d /var/log/opensim ]"

ts "done: $([ $FAILED = 0 ] && echo 'all checks passed' || echo 'SOME CHECKS FAILED')"
exit $FAILED
