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
pkgs() { ls /dist/opensim_*.deb /dist/opensim-kit_*.deb /dist/opensim-0.9.3.0_*_"$(dpkg --print-architecture)".deb; }
# The dotnet process only: the screen session running it has the same arguments
robust_pid() { pgrep -f "^dotnet .*Robust.dll" | head -1; }
quits() { grep -c '\[CONSOLE\] Quitting' /var/log/opensim/testgrid.log 2>/dev/null; }
robust_state() { echo "   Robust pid: $(robust_pid || true), clean shutdowns: $(quits)"; }

rm -f /usr/sbin/policy-rc.d # container images forbid service actions
apt-get update -qq

ts install
apt_q install $(pkgs)
check "opensim account" "getent passwd opensim >/dev/null"
check "core profile" "grep -q '^\[opensim-0.9.3.0\]' /etc/opensim/opensim.conf"
check "service enabled" "systemctl -q is-enabled opensim"

ts dotnet
/usr/share/opensim-kit/libexec/install-dotnet -y 2>&1 | tail -1
check ".NET runtime" "runuser -u opensim -- dotnet --list-runtimes | grep -q NETCore.App"

ts "grid from the wizard"
systemctl start mariadb
mysql -e "CREATE DATABASE testgrid_robust; CREATE USER opensim@localhost IDENTIFIED BY 'testpass';
    GRANT ALL ON testgrid_robust.* TO opensim@localhost;"
(cd /var/lib/opensim && runuser -u opensim -- php /test/newgrid.php | tail -1)
check "grid enabled" "[ -L /etc/opensim/robust.d/testgrid.ini ]"

ts "service start"
systemctl start opensim
opensim status | tail -2
pid=$(robust_pid)
check "Robust running" "[ -n '$pid' ]"
check "Robust ready" "grep -q 'UserAgentServerConnector loaded' /var/log/opensim/testgrid.log"
check "no write denied" "! grep -qiE 'denied|unauthorized' /var/log/opensim/testgrid.log"

ts upgrade
apt_q install --reinstall $(pkgs)
check "Robust not restarted" "[ '$(robust_pid)' = '$pid' ]"

ts "remove, grid started by the service"
apt_q remove opensim-kit
robust_state
check "Robust stopped cleanly" "[ -z '$(robust_pid)' ] && [ '$(quits)' = 1 ]"

ts reinstall
apt_q install $(pkgs)
check "service enabled again" "systemctl -q is-enabled opensim"

ts "remove, grid started by hand"
opensim start testgrid >/dev/null 2>&1
check "Robust running" "[ -n '$(robust_pid)' ]"
apt_q remove opensim-kit
robust_state
check "Robust stopped cleanly" "[ -z '$(robust_pid)' ] && [ '$(quits)' = 2 ]"

ts purge
apt_q purge opensim-kit opensim-0.9.3.0 opensim
check "cache removed" "[ ! -e /var/cache/opensim ]"
check "config, data and logs kept" "[ -d /etc/opensim/grids/testgrid ] && [ -d /var/lib/opensim/data ] && [ -d /var/log/opensim ]"
check "core profile removed" "! grep -q '^\[opensim-0.9.3.0\]' /etc/opensim/opensim.conf"

ts "done: $([ $FAILED = 0 ] && echo 'all checks passed' || echo 'SOME CHECKS FAILED')"
exit $FAILED
