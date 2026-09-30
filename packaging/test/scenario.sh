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

# The database, as the setup checks it (see Grid/Database.php). As opensim,
# who has no administrator access to the database server: what cannot be
# checked or fixed can be tried again, and nothing is written
wizard() { # grid, password, [user]
    (cd /var/lib/opensim && TEST_GRID=$1 TEST_DB_PASSWORD=$2 TEST_NO_ENABLE=1 TEST_REPEAT=${TEST_REPEAT:-1} \
        TEST_DB_USER=${TEST_DB_USER:-} runuser -u "${3:-opensim}" -- php /test/newgrid.php 2>&1)
}
as_root() { # grid, password
    (cd /var/lib/opensim && TEST_GRID=$1 TEST_DB_PASSWORD=$2 TEST_NO_ENABLE=1 TEST_DB_USER=${TEST_DB_USER:-} php /test/newgrid.php 2>&1)
}
wizard Badgrid wrong >/tmp/badgrid.out
check "no administrator access, wrong password: can be tried again, nothing written" "grep -q 'Try again' /tmp/badgrid.out &&
    grep -q 'cannot run without its database' /tmp/badgrid.out && [ ! -e /etc/opensim/grids/badgrid ]"
wizard Missinggrid testpass >/tmp/missing.out
check "no administrator access, missing database: says so, gives the commands" "grep -q 'no access to database missinggrid_robust' /tmp/missing.out &&
    grep -q 'CREATE DATABASE' /tmp/missing.out && grep -q 'Try again' /tmp/missing.out && [ ! -e /etc/opensim/grids/missinggrid ]"

# The password of an account, generated once, is proposed again in the same session
TEST_REPEAT=2 wizard Repeatgrid "" >/tmp/repeat.out
check "the generated password is kept for the session" "[ \"\$(grep -a 'Database password ->' /tmp/repeat.out | sort -u | wc -l)\" = 1 ] &&
    [ \"\$(grep -ac 'Database password ->' /tmp/repeat.out)\" = 2 ]"

# As root, with the administrator access: only what is missing is created,
# one statement at a time
as_root Rootgrid testpass >/tmp/rootgrid.out
check "valid account, missing database: the database is created, not the account" "mysql -e 'SHOW DATABASES' | grep -q '^rootgrid_robust\$' &&
    ! grep -qi 'create user' /tmp/rootgrid.out"
check "what the setup writes belongs to opensim" "[ \"\$(stat -c %U /etc/opensim/grids/rootgrid/Robust.HG.ini)\" = opensim ] &&
    [ \"\$(stat -c %U /var/lib/opensim/data/rootgrid)\" = opensim ]"
TEST_DB_USER=grid2user as_root Grid2 "" >/tmp/grid2.out
pass=$(grep -a 'Database password ->' /tmp/grid2.out | head -1 | sed 's/.*-> //')
check "missing account and database: both created, the account logs in" "MYSQL_PWD='$pass' mysql -u grid2user -e 'SELECT 1' grid2_robust >/dev/null 2>&1"
as_root Rootgrid wrong >/tmp/rootwrong.out
check "existing account, wrong password: not recreated, can be tried again" "grep -q 'password is rejected' /tmp/rootwrong.out &&
    ! grep -qi 'create user' /tmp/rootwrong.out && grep -q 'Try again' /tmp/rootwrong.out"
# An account of the caller's own that can read the server's accounts but not
# create them: the administrator access is found, the creation fails
useradd -m -s /bin/bash dbhelper
mysql -e "CREATE USER dbhelper@localhost IDENTIFIED VIA unix_socket; GRANT SELECT ON mysql.* TO dbhelper@localhost"
TEST_DB_USER=deniedgrid_user wizard Deniedgrid "" dbhelper >/tmp/denied.out
check "a creation that fails ends the setup, with the commands and an error code" "grep -q 'Could not create the user' /tmp/denied.out &&
    grep -q 'CREATE USER' /tmp/denied.out && ! grep -q 'Try again' /tmp/denied.out && grep -q 'setup failed' /tmp/denied.out &&
    [ ! -e /etc/opensim/grids/deniedgrid ]"

# The setup started as root, as on the packaged install, starts the grid and
# sees it run (a screen session is per user, the setup is not the one running it)
(cd /var/lib/opensim && TEST_GRID=Startgrid TEST_DB_PASSWORD= TEST_START=1 TEST_DB_USER=startuser php /test/newgrid.php >/tmp/startgrid.out 2>&1
    echo "exit code: $?" >>/tmp/startgrid.out)
check "the setup reports the grid it started as running, exit code 0" "grep -q \"Grid 'startgrid' is running\" /tmp/startgrid.out &&
    grep -q 'exit code: 0' /tmp/startgrid.out && [ -n \"\$(robust_pid)\" ]"
opensim stop now startgrid >/dev/null 2>&1
check "the launcher fails for an instance that does not exist" "! opensim start nosuchinstance >/dev/null 2>&1"

# Robust is ready when every service its config lists has reported it loaded,
# whatever options it runs with (here without Hypergrid: none of its
# connectors listed), and the ones that failed to load are named
ini=/etc/opensim/grids/startgrid/Robust.HG.ini
cp $ini /tmp/Robust.startgrid.good
sed -i '/UserAgentServerConnector/d;/GatekeeperServiceInConnector/d' $ini
opensim start startgrid >/tmp/nohg.out 2>&1; nohg=$?
check "a grid without Hypergrid is ready, not waited for" "[ $nohg = 0 ] && grep -aq 'startgrid started' /tmp/nohg.out"
opensim stop now startgrid >/dev/null 2>&1
cp /tmp/Robust.startgrid.good $ini
sed -i 's/Password=[^;"]*;/Password=wrongpass;/' $ini
opensim start startgrid >/tmp/baddb.out 2>&1; baddb=$?
check "a grid whose database cannot be used is reported, with the failed services" "[ $baddb != 0 ] &&
    grep -aq 'services failed to load' /tmp/baddb.out && grep -aq 'AssetServiceConnector: failed to load' /tmp/baddb.out"
opensim stop now startgrid >/dev/null 2>&1
cp /tmp/Robust.startgrid.good $ini
rm -rf /etc/opensim/grids/startgrid /etc/opensim/robust.d/startgrid.ini /var/lib/opensim/data/startgrid /var/cache/opensim/startgrid
rm -rf /etc/opensim/grids/rootgrid /var/lib/opensim/data/rootgrid /var/cache/opensim/rootgrid \
    /etc/opensim/grids/grid2 /var/lib/opensim/data/grid2 /var/cache/opensim/grid2
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
