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
metas="$(deb opensim) $(deb opensim-kit)"
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
apt_q install "$(deb opensim-unstable)" "$(deb opensim-0.9.3.0-opensimsearch)" "$(deb opensim-0.9.3.0-gloebit)"
check "profile of the development build" "grep -q '^\[opensim-unstable\]' /etc/opensim/opensim.conf"
check "modules in their own folders" "[ -f /usr/share/opensim-modules/0.9.3.0/opensimsearch/OpenSimSearch.Modules.dll ] &&
    [ -f /usr/share/opensim-modules/0.9.3.0/gloebit/Gloebit.dll ] &&
    [ ! -e /usr/share/opensim/0.9.3.0/bin/OpenSimSearch.Modules.dll ]"
check "log config of the wizard" "[ -f /var/log/opensim/testgrid_robust.log ]"
# The grid on the development build, then back on the release
runuser -u opensim -- crudini --inplace --set /etc/opensim/grids/testgrid/testgrid.conf Grid CoreDirectory /usr/share/opensim/unstable
opensim restart now testgrid >/dev/null 2>&1
check "grid on its own core" "grep 'Starting in' $(robust_log) | tail -1 | grep -q /usr/share/opensim/unstable/bin"
check "development build ready" "[ \$(grep -c 'UserAgentServerConnector loaded' $(robust_log)) = 2 ]"
runuser -u opensim -- crudini --inplace --set /etc/opensim/grids/testgrid/testgrid.conf Grid CoreDirectory /usr/share/opensim/0.9.3.0
opensim restart now testgrid >/dev/null 2>&1

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
apt_q remove opensim-0.9.3.0
check "profile removed" "! profile"
check "modules removed with their core" "[ ! -e /usr/share/opensim-modules/0.9.3.0 ]"
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
