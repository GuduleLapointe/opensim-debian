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
# The tools, with the bash-tools they depend on
tools="$(deb opensim-tools) $(deb bash-tools)"
metas="$(deb opensim) $(deb opensim-kit)"
# The dotnet process only: the screen session running it has the same arguments
robust_pid() { pgrep -f "^dotnet .*Robust.dll" | head -1; }
robust_log() { ls /var/log/opensim/testgrid*.log 2>/dev/null | grep -v Stats | head -1; }
quits() { cat /var/log/opensim/testgrid*.log 2>/dev/null | grep -c '\[CONSOLE\] Quitting'; }
robust_state() { echo "   Robust pid: $(robust_pid || true), clean shutdowns: $(quits)"; }
profile() { grep -q '^\[opensim-0.9.3.0\]' /etc/opensim/opensim.conf 2>/dev/null; }

rm -f /usr/sbin/policy-rc.d # container images forbid service actions
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

ts "remove the tools, grid started by the service"
apt_q remove opensim-tools
robust_state
check "Robust stopped cleanly" "[ -z '$(robust_pid)' ] && [ '$(quits)' = 1 ]"
check "core kept" "[ -f /usr/share/opensim/0.9.3.0/bin/OpenSim.exe ]"

ts "reinstall the tools"
apt_q install $tools
check "service enabled again" "systemctl -q is-enabled opensim"

ts "remove the tools, grid started by hand"
opensim start testgrid >/dev/null 2>&1
check "Robust running" "[ -n '$(robust_pid)' ]"
apt_q remove opensim-tools
robust_state
check "Robust stopped cleanly" "[ -z '$(robust_pid)' ] && [ '$(quits)' = 2 ]"

ts "remove the core, tools installed"
apt_q install $tools
apt_q remove opensim-0.9.3.0
check "profile removed" "! profile"
check "no default profile" "! grep -q '^DefaultProfile' /etc/opensim/opensim.conf"
check "tools work without a core" "opensim status >/dev/null"

ts purge
apt_q purge opensim-tools
check "cache removed" "[ ! -e /var/cache/opensim ]"
check "config, data and logs kept" "[ -d /etc/opensim/grids/testgrid ] && [ -d /var/lib/opensim/data ] && [ -d /var/log/opensim ]"

ts "done: $([ $FAILED = 0 ] && echo 'all checks passed' || echo 'SOME CHECKS FAILED')"
exit $FAILED
