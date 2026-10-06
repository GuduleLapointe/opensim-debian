#!/usr/bin/env bash
# The site of a grid on real web servers, run by SCENARIO=web-scenario.sh tests/Packaging/run: nginx, Apache
# and Caddy, each with the file the setup writes for it (opensim web GRID snippet SERVER), PHP-FPM or
# mod_php behind. One server at a time, they all want the port 80 of the machine by default.

source /test/scenario-lib.sh
magiiic_repository

ts "packages, grid"
apt_q install "$(deb opensim-0.9.3.0)" "$(deb opensim-rest-php)" "$(deb opensim-engine)" "$(deb opensim-helpers)" "$(deb opensim-tools)" "$(deb opensim-web)"
systemctl start mariadb
mysql -e "CREATE DATABASE testgrid_robust; CREATE USER opensim@localhost IDENTIFIED BY 'testpass';
    GRANT ALL ON testgrid_robust.* TO opensim@localhost;"
(cd /var/lib/opensim && runuser -u opensim -- php /test/newgrid-driver.php | tail -1)
# The table of the offline messages, which Robust makes when it runs (it does not here)
mysql testgrid_robust -e "CREATE TABLE IF NOT EXISTS im_offline (ID mediumint(9) NOT NULL AUTO_INCREMENT, PrincipalID char(36) NOT NULL DEFAULT '',
    FromID char(36) NOT NULL DEFAULT '', Message text NOT NULL, TMStamp timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
    PRIMARY KEY (ID), KEY PrincipalID (PrincipalID), KEY FromID (FromID)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3"
check "the setup wrote the files for the three servers" "[ -s /etc/opensim/grids/testgrid/web/testgrid.caddyfile ] &&
    [ -s /etc/opensim/grids/testgrid/web/testgrid-nginx.conf ] && [ -s /etc/opensim/grids/testgrid/web/testgrid-apache.conf ]"
apt_q install php-fpm
systemctl start php8.2-fpm
check "PHP-FPM listens where the files say" "[ -S /run/php/php-fpm.sock ]"

# What the offline message module of a simulator asks, at the URL it is given (.../helpers/offline.php) plus the method
OFFLINE_IM='<?xml version="1.0"?><GridInstantMessage><fromAgentID>aaaaaaaa-0000-4000-8000-000000000001</fromAgentID><fromAgentName>Alice</fromAgentName><toAgentID>bbbbbbbb-0000-4000-8000-000000000001</toAgentID><dialog>0</dialog><fromGroup>false</fromGroup><message>Hello behind the server</message><imSessionID>00000000-0000-0000-0000-000000000000</imSessionID><offline>0</offline><Position><X>0</X><Y>0</Y><Z>0</Z></Position><binaryBucket>AA==</binaryBucket><ParentEstateID>0</ParentEstateID><RegionID>00000000-0000-0000-0000-000000000000</RegionID><timestamp>1</timestamp></GridInstantMessage>'
OFFLINE_GET='<?xml version="1.0"?><UUID><Guid>bbbbbbbb-0000-4000-8000-000000000001</Guid></UUID>'
offline_save() { curl -sk -d "$OFFLINE_IM" "$1/helpers/offline.php/SaveMessage/"; }
offline_get() { curl -sk -d "$OFFLINE_GET" "$1/helpers/offline.php/RetrieveMessages/"; }

# What a visitor and a viewer ask, at the site of the grid: the pages, a helper under its prefix, an unknown URL
site() { # server, url of the site
    local url=$2
    wait_check 30 "$1 answers" "curl -sk $url/ >/dev/null"
    check "$1: the home and the splash page show the grid" "curl -sk $url/ | grep -q Testgrid && curl -sk $url/welcome | grep -q Testgrid"
    check "$1: a helper answers under its prefix" "[ \"\$(curl -sk $url/helpers/motd.php)\" = 'Welcome to Testgrid, <USERNAME>!' ]"
    # The scripts that need the database, behind each server: they keep it in global variables, which the router must leave them
    check "$1: a search is answered, so the helpers reach their database" "curl -sk -d '<?xml version=\"1.0\"?><methodCall><methodName>dir_popular_query</methodName><params><param><value><struct><member><name>flags</name><value><string>117440512</string></value></member></struct></value></param></params></methodCall>' $url/helpers/query.php | grep -q '<name>success</name>'"
    check "$1: a simulator that registers without its address is refused by the script, not by an error of the server" "[ \"\$(curl -sk -o /dev/null -w '%{http_code}' $url/helpers/register.php)\" = 400 ]"
    check "$1: the offline messages of the module are stored at its URL, and given back" "offline_save $url | grep -q '<boolean>true</boolean>' && offline_get $url | grep -q 'Hello behind the server'"
    check "$1: an unknown URL is a 404 of the router" "[ \"\$(curl -sk -o /dev/null -w '%{http_code}' $url/nope)\" = 404 ] && curl -sk $url/nope | grep -q 'Not found'"
}

# The files of the setup are complete: nginx and Apache include theirs, Caddy takes its as the whole config
ts nginx
apt_q install nginx
rm -f /etc/nginx/sites-enabled/default
cp /etc/opensim/grids/testgrid/web/testgrid-nginx.conf /etc/nginx/conf.d/testgrid.conf
check "nginx accepts the file as it is" "nginx -t >/dev/null 2>&1"
systemctl restart nginx
site nginx http://localhost
systemctl stop nginx

ts apache
apt_q install apache2 libapache2-mod-php
cp /etc/opensim/grids/testgrid/web/testgrid-apache.conf /etc/apache2/sites-available/testgrid.conf
a2dissite 000-default >/dev/null
a2ensite testgrid >/dev/null
check "Apache accepts the file as it is" "apachectl configtest >/dev/null 2>&1"
systemctl restart apache2
site apache http://localhost
systemctl stop apache2

ts caddy
apt_q install caddy
cp /etc/opensim/grids/testgrid/web/testgrid.caddyfile /etc/caddy/Caddyfile
check "Caddy accepts the file as the whole config" "caddy validate --config /etc/caddy/Caddyfile >/dev/null 2>&1"
systemctl restart caddy
site caddy https://localhost
systemctl stop caddy

ts "done: $([ $FAILED = 0 ] && echo 'all checks passed' || echo 'SOME CHECKS FAILED')"
exit $FAILED
