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
robust_log() { ls /var/log/opensim/testgrid_robust*.log 2>/dev/null | grep -v Stats | head -1; }
quits() { cat /var/log/opensim/testgrid_robust*.log 2>/dev/null | grep -c '\[CONSOLE\] Quitting'; }
# The simulator of the grid made by the wizard, and its region
sim_pid() { pgrep -f "^dotnet .*OpenSim.dll -inifile=/etc/opensim/opensim.d/testgrid_sim1.ini" | head -1; }
sim_registered() { grep -c 'Region Sim1 .* registered at 1000,1000' /var/log/opensim/testgrid_sim1.log 2>/dev/null; }
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

# A user who is neither root nor the system user, with a database account of
# their own that may create accounts and databases, and the right to run
# things as opensim (what `opensim start` needs anyway). The database is
# handled with their own rights, no sudo to root; only the writing of the
# files goes to opensim.
useradd -m -s /bin/bash dbadmin
echo 'dbadmin ALL=(opensim) NOPASSWD: ALL' >/etc/sudoers.d/dbadmin && chmod 440 /etc/sudoers.d/dbadmin
mysql -e "CREATE USER dbadmin@localhost IDENTIFIED VIA unix_socket; GRANT ALL ON *.* TO dbadmin@localhost WITH GRANT OPTION"
(cd /var/lib/opensim && TEST_GRID=Adminsgrid TEST_DB_PASSWORD= TEST_NO_ENABLE=1 TEST_DB_USER=adminsuser runuser -u dbadmin -- php /test/newgrid.php >/tmp/adminsgrid.out 2>&1
    echo "exit code: $?" >>/tmp/adminsgrid.out)
pass=$(grep -a 'Database password ->' /tmp/adminsgrid.out | head -1 | sed 's/.*-> //')
check "own database account, no sudo to root: account and database created" "MYSQL_PWD='$pass' mysql -u adminsuser -e 'SELECT 1' adminsgrid_robust >/dev/null 2>&1"
check "the files are written by the system user, the setup ends well" "grep -q 'exit code: 0' /tmp/adminsgrid.out &&
    [ \"\$(stat -c %U /etc/opensim/grids/adminsgrid/Robust.HG.ini)\" = opensim ] && [ \"\$(stat -c %U /var/lib/opensim/data/adminsgrid)\" = opensim ]"
rm -rf /etc/opensim/grids/adminsgrid /var/lib/opensim/data/adminsgrid /var/cache/opensim/adminsgrid

# A user with no administrator access of their own, and a ~/.my.cnf with an
# outdated password. The grid's account is checked with its own credentials
# only, not the ones of the file; the administrator account is asked, its
# password is not shown, and it works from another machine's point of view
# (credentials given, nothing implicit)
useradd -m -s /bin/bash plainuser
echo 'plainuser ALL=(opensim) NOPASSWD: ALL' >/etc/sudoers.d/plainuser && chmod 440 /etc/sudoers.d/plainuser
printf '[client]\nuser=plainuser\npassword=outdated\n' >/home/plainuser/.my.cnf
chown plainuser: /home/plainuser/.my.cnf && chmod 600 /home/plainuser/.my.cnf
mysql -e "CREATE USER dbroot@localhost IDENTIFIED BY 'adminpw'; GRANT ALL ON *.* TO dbroot@localhost WITH GRANT OPTION"
mysql -e "CREATE USER cnfuser@localhost IDENTIFIED BY 'cnfpass'; CREATE DATABASE cnfgrid_robust; GRANT ALL ON cnfgrid_robust.* TO cnfuser@localhost"
TEST_DB_USER=cnfuser wizard Cnfgrid cnfpass plainuser >/tmp/cnfgrid.out
check "an outdated ~/.my.cnf does not disturb the account of the grid" "grep -q 'cnfgrid_robust on localhost: connection OK' /tmp/cnfgrid.out"
rm -rf /etc/opensim/grids/cnfgrid /var/lib/opensim/data/cnfgrid /var/cache/opensim/cnfgrid
TEST_ADMIN_USER=dbroot TEST_ADMIN_PASSWORD=wrongpw TEST_DB_USER=askedwrong_user wizard Askedwrong "" plainuser >/tmp/askedwrong.out
check "a wrong administrator password: refused, nothing created, the commands are shown" "grep -q 'Login refused for dbroot' /tmp/askedwrong.out &&
    grep -q 'CREATE USER' /tmp/askedwrong.out && [ -z \"\$(mysql -BN -e \"SELECT 1 FROM mysql.user WHERE User='askedwrong_user'\")\" ]"
TEST_ADMIN_USER=dbroot TEST_ADMIN_PASSWORD=adminpw TEST_DB_USER=asked_user wizard Asked "" plainuser >/tmp/asked.out
pass=$(grep -a 'Database password ->' /tmp/asked.out | head -1 | sed 's/.*-> //')
check "no administrator access: the credentials are asked, account and database created" "MYSQL_PWD='$pass' mysql -u asked_user -e 'SELECT 1' asked_robust >/dev/null 2>&1"
check "the administrator password is neither shown nor written" "! grep -rqa adminpw /tmp/asked.out /etc/opensim/grids/asked"
rm -rf /etc/opensim/grids/asked /var/lib/opensim/data/asked /var/cache/opensim/asked

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

# A simulator joins the grid, with a new account of the grid as owner of its
# estate (made through the console of Robust), a database of its own (the
# administrator account is asked), and starts with its region
ts "simulator in the grid"
(cd /var/lib/opensim && TEST_GRID=testgrid TEST_SIM=Sim1 TEST_START=1 TEST_NEW_OWNER="Test Owner" \
    TEST_ADMIN_USER=dbroot TEST_ADMIN_PASSWORD=adminpw runuser -u opensim -- php /test/newsim.php >/tmp/sim1.out 2>&1
    echo "exit code: $?" >>/tmp/sim1.out)
check "the simulator wizard ends well, the region is online" "grep -q 'exit code: 0' /tmp/sim1.out &&
    grep -q 'region Sim1 is online' /tmp/sim1.out && [ -n '$(sim_pid)' ]"
check "the region is registered in the grid" "[ \"\$(mysql -BN -e \"SELECT CONCAT(locX DIV 256, ',', locY DIV 256) FROM testgrid_robust.regions WHERE regionName='Sim1'\")\" = 1000,1000 ]"
check "the simulator has its own database, its config is enabled" "mysql -e 'SELECT 1' testgrid_sim1 >/dev/null 2>&1 &&
    [ -L /etc/opensim/opensim.d/testgrid_sim1.ini ] && [ -f /etc/opensim/grids/testgrid/sims/testgrid_sim1/regions/Sim1.ini ]"
check "the estate belongs to the account made in the grid" "[ -n \"\$(mysql -BN -e \"SELECT PrincipalID FROM testgrid_robust.UserAccounts WHERE FirstName='Test' AND LastName='Owner'\")\" ] &&
    [ \"\$(mysql -BN -e 'SELECT EstateOwner FROM testgrid_sim1.estate_settings LIMIT 1')\" = \"\$(mysql -BN -e \"SELECT PrincipalID FROM testgrid_robust.UserAccounts WHERE FirstName='Test' AND LastName='Owner'\")\" ]"
check "the region loads the enabled modules" "grep -q 'Plugin Loaded: OpenSimSearch' /var/log/opensim/testgrid_sim1.log"
check "nothing written in the read-only core" "! grep -iE 'unauthorized|denied' /var/log/opensim/testgrid_sim1.log | grep -viE 'gloebit'"
check "the native libraries are found" "[ -L /var/lib/opensim/native/0.9.3.0/libBulletSim.so ] && ! grep -q 'DllNotFound' /var/log/opensim/testgrid_sim1.log"
sim=$(sim_pid)

ts upgrade
apt_q install --reinstall $tools "$core"
check "Robust not restarted" "[ '$(robust_pid)' = '$pid' ]"
check "the simulator not restarted" "[ '$(sim_pid)' = '$sim' ]"

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
check "instances of another core untouched" "[ '$(robust_pid)' = '$pid' ] && [ '$(sim_pid)' = '$sim' ]"
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
registered=$(sim_registered)
systemctl restart opensim
robust_state
check "the service starts the grid, then its simulator" "[ -n '$(robust_pid)' ] && [ -n '$(sim_pid)' ] && [ '$(sim_registered)' -gt '$registered' ]"
before=$(quits)
apt_q remove opensim-tools
robust_state
check "Robust stopped cleanly" "[ -z '$(robust_pid)' ] && [ '$(quits)' = $((before + 1)) ]"
check "the simulator stopped too" "[ -z '$(sim_pid)' ]"
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
