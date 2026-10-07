#!/usr/bin/env bash
# The pages and the small services of the helpers that are not the search, the offline messages or the economy, on the
# grid of the scenario: the message of the day (motd.php), the destination guide (guide.php), the images of text
# (textgen.php), the events of a calendar (eventsparser.php) and the page of the directory (directory_info.php). Sourced by
# package-scenario.sh (STOP_AFTER=pages stops the scenario after it), in the test container.

[[ $(type -t check) == function ]] || source /test/scenario-lib.sh

db=testgrid_robust
helpers=http://localhost:8090/helpers
site=http://localhost:8090
sql() { mysql -BN "$db" -e "$1"; }
get() { curl -s -m 60 "$@"; }
status() { curl -s -o /dev/null -m 60 -w '%{http_code}' "$@"; }
# What the Robust config of the grid gives for a setting that is an address of the web site: its path
robust_path() {
    local value
    value=$(grep -m1 "^[[:space:]]*$1[[:space:]]*=" /etc/opensim/grids/testgrid/Robust.HG.ini | cut -d'"' -f2)
    echo "${value#'${Const|WebURL}'}"
}
start_helpers() {
    pkill -f 'php .*-S 127.0.0.1:809[0]' 2>/dev/null || true
    (cd /var/www/html && OPENSIM_GRID=testgrid PHP_CLI_SERVER_WORKERS=4 setsid -f php -S 127.0.0.1:8090 index.php >/tmp/php-pages.log 2>&1)
    wait_check 20 "the site of the helpers answers" "[ \"\$(status $helpers/motd.php)\" = 200 ]"
}

cp -p $helpers_ini /tmp/Helpers.ini.pages
cp -p $robust_ini /tmp/Robust.HG.ini.pages
ts "pages: the site of the helpers"
start_helpers

ts "pages: the message of the day"
body=$(get -D /tmp/motd.headers $helpers/motd.php)
check "motd: a welcome to the grid, by default, with the name of the avatar left to Robust" "[ \"\$body\" = 'Welcome to Testgrid, <USERNAME>!' ]"
check "motd: plain text, in UTF-8" "grep -qi '^content-type: text/plain; charset=utf-8' /tmp/motd.headers"
ini_set $robust_ini LoginService WelcomeMessage 'Bienvenue à <USERNAME>\nSecond line'
check "motd: the text of the grid, as it is (Robust makes the new lines and the name)" "[ \"\$(get $helpers/motd.php)\" = 'Bienvenue à <USERNAME>\\nSecond line' ]"
check "motd: the address Robust reads it at is served" "[ \"\$(status http://localhost:8090\$(robust_path MessageUrl))\" = 200 ]"
cp -p /tmp/Robust.HG.ini.pages $robust_ini

ts "pages: the layout and the blocks"
rm -rf /tmp/opensim-helpers-cache
firefox='Mozilla/5.0 (X11; Linux x86_64; rv:130.0) Gecko/20100101 Firefox/130.0'
firestorm='Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) SecondLife/7.1.9 (Firestorm-Releasex64; default skin)'
home=$(get -A "$firefox" $site/)
check "home: a browser gets the top bar, the three columns and the footer" "echo \"\$home\" | grep -q '<body class=\"browser page-home\">' && echo \"\$home\" | grep -q 'class=\"topbar\"' && echo \"\$home\" | grep -q 'sidebar-left' && echo \"\$home\" | grep -q 'sidebar-right' && echo \"\$home\" | grep -q '<footer>'"
check "home: what the grid is on the left (info, status), the message of the day on the right, its name in the middle" "echo \"\$home\" | grep -q 'block-grid-info' && echo \"\$home\" | grep -q 'block-grid-status' && echo \"\$home\" | grep -q 'block-motd' && echo \"\$home\" | grep -q '<h1 class=\"grid-name\">Testgrid</h1>'"
check "home: a viewer gets the content alone" "get -A '$firestorm' $site/ | grep -q 'content-only' && ! get -A '$firestorm' $site/ | grep -q 'sidebar-left' && ! get -A '$firestorm' $site/ | grep -q 'class=\"topbar\"'"
splash=$(get -A "$firestorm" $site/welcome)
check "welcome: the splash page is the one a viewer shows with the sidebars, and no top bar, no footer" "echo \"\$splash\" | grep -q '<body class=\"viewer page-splash\">' && echo \"\$splash\" | grep -q 'sidebar-left' && echo \"\$splash\" | grep -q 'block-grid-status' && ! echo \"\$splash\" | grep -q 'class=\"topbar\"' && ! echo \"\$splash\" | grep -q '<footer>'"
check "welcome: a browser can ask to see what a viewer sees (?viewer=1), and the other way round" "get -A '$firefox' '$site/welcome?viewer=1' | grep -q '<body class=\"viewer page-splash\">' && get -A '$firestorm' '$site/welcome?viewer=0' | grep -q '<body class=\"browser page-splash\">'"
check "assets: the style of the pages is served where the page says it is" "href=\$(echo \"\$home\" | grep -o 'href=\"[^\"]*page.css[^\"]*\"' | cut -d'\"' -f2) && [ \"\$(status http://localhost:8090\$href)\" = 200 ] && get -D - -o /dev/null http://localhost:8090\$href | grep -qi '^content-type: text/css'"
check "assets: nothing outside them" "[ \"\$(status --path-as-is \"$helpers/assets/../includes/bootstrap.php\")\" = 404 ] && [ \"\$(status \"$helpers/assets/css/nothing.css\")\" = 404 ]"
members=$(sql "SELECT COUNT(*) FROM UserAccounts WHERE active = 1")
regions=$(sql "SELECT COUNT(*) FROM regions")
check "grid-status block: the numbers of the database, as a fragment to embed" "block=\$(get '$helpers/blocks/grid-status?title=Status' | tr -d '\n'); echo \"\$block\" | grep -q '<h3 class=\"block-title\">Status</h3>' && echo \"\$block\" | grep -qE '<th scope=\"row\">Members</th>[[:space:]]*<td>$members</td>' && echo \"\$block\" | grep -qE '<th scope=\"row\">Regions</th>[[:space:]]*<td>$regions</td>'"
check "grid-status block: online when Robust answers" "get '$helpers/blocks/grid-status' | tr -d '\n' | grep -qE '<th scope=\"row\">Status</th>[[:space:]]*<td>ONLINE</td>'"
check "grid_status: the state of the grid, as a page for the viewers (ONLINE, the keyword they read), at its default address" "[ \"\$(status $helpers/grid_status)\" = 200 ] && get -A '$firestorm' $helpers/grid_status | grep -q 'ONLINE' && ! get -A '$firestorm' $helpers/grid_status | grep -q 'class=\"topbar\"'"
check "grid_info: what the grid is, a page of the same kind" "[ \"\$(status $helpers/grid_info)\" = 200 ] && get -A '$firestorm' $helpers/grid_info | grep -q 'Testgrid'"
cfg GridStatusURL "$site/status"
check "grid_status: where Helpers.ini puts it, and no longer at the default address" "[ \"\$(status $site/status)\" = 200 ] && [ \"\$(status $helpers/grid_status)\" = 404 ]"
cp -p /tmp/Helpers.ini.pages $helpers_ini
check "grid-status block: its data, for an application that makes its own page" "[ \"\$(get '$helpers/blocks/grid-status?format=json&title=' | php -r 'echo json_decode(stream_get_contents(STDIN), true)[\"rows\"][1][\"value\"];')\" = \"$members\" ]"
check "grid-status block: without the visitors of other grids when asked" "! get '$helpers/blocks/grid-status?hypergrid=0' | grep -q 'Active users' && get '$helpers/blocks/grid-status' | grep -q 'Active users'"
check "grid-info block: the name of the grid and the link to log in" "get '$helpers/blocks/grid-info' | grep -q 'Testgrid' && get '$helpers/blocks/grid-info' | grep -q 'href=\"hop://localhost:8002\"'"
check "motd block: the message of the grid, for a page that does not know who reads it" "get '$helpers/blocks/motd' | grep -q 'Welcome to Testgrid, traveler!'"
check "blocks: one that does not exist is a 404" "[ \"\$(status '$helpers/blocks/nothing')\" = 404 ]"
check "profile: a profile has no page yet, and it is a 404, not an error" "[ \"\$(status '$site/@jane.doe')\" = 404 ]"

ts "pages: the theme of the grid"
theme=/etc/opensim/grids/testgrid/theme
mkdir -p $theme/templates/pages $theme/assets/css
echo '<p class="ours">Our home page</p>' >$theme/templates/pages/home-page.twig
echo 'body { margin: 0; }' >$theme/assets/css/mine.css
chmod -R a+rX $theme
check "theme: a page of the theme replaces the one of the helpers, in the layout of the helpers" "home=\$(get -A '$firefox' $site/); echo \"\$home\" | grep -q 'Our home page' && ! echo \"\$home\" | grep -q 'grid-name' && echo \"\$home\" | grep -q 'class=\"topbar\"'"
check "theme: an asset of the theme is served with the ones of the helpers" "[ \"\$(status $helpers/assets/css/mine.css)\" = 200 ] && [ \"\$(status $helpers/assets/css/page.css)\" = 200 ]"
rm -rf $theme

ts "pages: the destination guide"
mkdir -p /tmp/guide
cat >/tmp/guide/destinations.txt <<'TXT'
# a comment
// another comment
Places to see
Welcome Plaza|testgrid.example:8002:Welcome Plaza/128/128/22
Old Format|ignored|ignored|http://other.example:8002|Old Region/10/20/30
<b>Bold</b> & "quotes"|http://x.example:8002:Odd Place/1/2/3

Shops
Mall|http://shop.example:8002:Mall/1/2/3
TXT
printf 'Only category\nSingle|http://one.example:8002:One/1/2/3\nTwo|http://one.example:8002:Two/4/5/6\n' >/tmp/guide/single.txt
guide=$helpers/guide.php
check "guide: the viewers are sent to it, by the config of Robust" "[ \"\$(robust_path DestinationGuide)\" = /helpers/guide.php ]"
check "guide: no destinations is said, as a page, not an error" "[ \"\$(status $guide)\" = 200 ] && get $guide | grep -q 'realm of destinations'"
cfg GuideSource /tmp/guide/destinations.txt
page=$(get $guide)
check "guide: a page of the layout, with the categories of the file" "echo \"\$page\" | grep -qi '<!doctype html>' && echo \"\$page\" | grep -q 'Places to see' && echo \"\$page\" | grep -q 'Shops'"
check "guide: each category says how many destinations it has" "echo \"\$page\" | grep -q '3 destinations' && echo \"\$page\" | grep -q '1 destinations'"
check "guide: the comments are not destinations" "! echo \"\$page\" | grep -q 'a comment'"
places=$(get "$guide?category=Places%20to%20see")
check "guide: a category lists its destinations, with the way back" "echo \"\$places\" | grep -q 'Welcome Plaza' && echo \"\$places\" | grep -q 'Old Format' && echo \"\$places\" | grep -q 'Back to categories' && ! echo \"\$places\" | grep -q 'Mall'"
check "guide: a destination is a link the viewer follows (name of the grid, port, region)" "echo \"\$places\" | grep -q 'href=\"secondlife://testgrid.example:8002%20Welcome%20Plaza/128/128/22\"'"
check "guide: the older format of the file (the grid and the region in the 4th and 5th columns) is read too" "echo \"\$places\" | grep -q 'href=\"secondlife://other.example:8002%20Old%20Region/10/20/30\"'"
check "guide: what the file holds is text, not markup" "echo \"\$places\" | grep -q '&lt;b&gt;Bold&lt;/b&gt; &amp; &quot;quotes&quot;' && ! echo \"\$places\" | grep -q '<b>Bold</b>'"
check "guide: another category has its own destinations" "get \"$guide?category=Shops\" | grep -q 'Mall' && ! get \"$guide?category=Shops\" | grep -q 'Welcome Plaza'"
check "guide: a category that does not exist gives the list of the categories" "get \"$guide?category=Nowhere\" | grep -q '3 destinations'"
check "guide: the file of the grid is the one used, not one the caller names" "get \"$guide?source=/etc/hostname\" | grep -q 'Places to see'"
check "guide: in a browser it has the top bar, the sidebars and its style in the head (versioned, so it is not loaded again at each visit)" "echo \"\$page\" | grep -q 'class=\"topbar\"' && echo \"\$page\" | grep -q 'sidebar-left' && echo \"\$page\" | tr -d '\n' | grep -qE '<head>.*guide.css\\?v=[0-9]+.*</head>'"
check "guide: in a viewer it is the content alone" "viewer_page=\$(get -A '$firestorm' $guide); echo \"\$viewer_page\" | grep -q 'id=\"guide\"' && ! echo \"\$viewer_page\" | grep -q 'class=\"topbar\"' && ! echo \"\$viewer_page\" | grep -q 'sidebar-left'"
cfg GuideSource /tmp/guide/single.txt
single=$(get $guide)
check "guide: a file of one category opens it, with no way back to the list" "echo \"\$single\" | grep -q 'Only category' && echo \"\$single\" | grep -q 'Single' && ! echo \"\$single\" | grep -q 'Back to categories'"
(cd /tmp/guide && PHP_CLI_SERVER_WORKERS=2 setsid -f php -S 127.0.0.1:8097 -t /tmp/guide >/tmp/php-guide-source.log 2>&1)
wait_check 20 "the file of a destination guide is on a web site" "[ \"\$(status http://127.0.0.1:8097/destinations.txt)\" = 200 ]"
cfg GuideSource http://127.0.0.1:8097/destinations.txt
check "guide: the destinations can be a URL, as a site keeps them" "get $guide | grep -q 'Places to see'"
cfg GuideSource /tmp/guide/does-not-exist.txt
check "guide: a file that is not there is no destinations, not an error" "[ \"\$(status $guide)\" = 200 ] && get $guide | grep -q 'realm of destinations'"
pkill -f 'php .*-S 127.0.0.1:809[7]' 2>/dev/null || true
cp -p /tmp/Helpers.ini.pages $helpers_ini

ts "pages: the images of text"
# Imagick is only suggested by the package: a server without it says so, then it is installed (once, in a container kept)
php -m | grep -qi imagick || check "textgen: a server without Imagick says it (bad configuration, 503), it does not fail" "[ \"\$(status \"$helpers/textgen.php?string=Hello\")\" = 503 ]"
apt_q install php-imagick
check "php-imagick is installed" "php -m | grep -qi imagick"
start_helpers
text=$helpers/textgen.php
check "textgen: the fonts, as a list" "get '$text?list=1' | grep -qx 'DejaVu-Sans'"
check "textgen: the fonts, as JSON" "get '$text?json=1' | php -r 'echo in_array(\"DejaVu-Sans\", json_decode(stream_get_contents(STDIN), true)) ? \"yes\" : \"no\";' | grep -q yes"
check "textgen: the list is plain text" "get -D - -o /dev/null '$text?list=1' | grep -qi '^content-type: text/plain'"
img() { php /test/pages/image-probe.php "$1"; }
get "$text?string=Hello" -D /tmp/text.headers -o /tmp/text.png
check "textgen: a PNG of 1024 x 1024" "grep -qi '^content-type: image/png' /tmp/text.headers && img /tmp/text.png | grep -q '^1024x1024 PNG'"
check "textgen: white on black, by default, and there is a text" "img /tmp/text.png | grep -q 'background=0,0,0 text=255,255,255' && ! img /tmp/text.png | grep -q 'ink=0.00'"
check "textgen: it is shown in the page, not downloaded" "! grep -qi '^content-disposition' /tmp/text.headers"
get "$text?string=Hello&color=red&background=blue" -o /tmp/text2.png
check "textgen: the colours of the text and of the background" "img /tmp/text2.png | grep -q 'background=0,0,255 text=255,0,0'"
get "$text?string=Hello&size=30" -o /tmp/text-small.png
get "$text?string=Hello&size=300" -o /tmp/text-big.png
small=$(img /tmp/text-small.png | sed 's/.*ink=//')
big=$(img /tmp/text-big.png | sed 's/.*ink=//')
check "textgen: the size makes the text bigger ($small% of the picture for 30, $big% for 300)" "awk 'BEGIN { exit !($big > $small * 4) }'"
get "$text?string=Hello&fontsize=300" -o /tmp/text-big2.png
check "textgen: fontsize is read as well" "[ \"\$(img /tmp/text-big2.png | sed 's/.*ink=//')\" = \"$big\" ]"
get "$text?string=Hello&font=NoSuchFont" -D /tmp/text.headers -o /tmp/text3.png
check "textgen: a font the server does not have is replaced, there is still a text" "img /tmp/text3.png | grep -q '^1024x1024 PNG' && ! img /tmp/text3.png | grep -q 'ink=0.00'"
get "$text?string=Hello%20World&download=1" -D /tmp/text.headers -o /dev/null
check "textgen: to download, the file is named after the text" "grep -qi '^content-disposition: attachment; filename=\"Hello World.png\"' /tmp/text.headers"
get "$text?string=Hello&download=1&inpage=1" -D /tmp/text.headers -o /dev/null
check "textgen: inpage keeps it in the page" "! grep -qi '^content-disposition' /tmp/text.headers"
get "$text?string=%22%3E%3Cb%3Ex%2F%5C%0D%0AX-Evil:1&download=1" -D /tmp/text.headers -o /dev/null
check "textgen: what the text holds is no harm to the name of the file" "grep -qi '^content-disposition: attachment; filename=\"[A-Za-z0-9 .-]*\.png\"' /tmp/text.headers && ! grep -qi '^X-Evil' /tmp/text.headers"
get "$text" -o /tmp/text4.png
check "textgen: without a text, the font name is written" "img /tmp/text4.png | grep -q '^1024x1024 PNG' && ! img /tmp/text4.png | grep -q 'ink=0.00'"

ts "pages: the events of a calendar"
# A calendar (HYPEvents) with what a grid publishes: events to come, one over, a region nobody answers for, markup, categories
mkdir -p /tmp/hypevents
day() { date -u -d "$1" +%Y-%m-%dT%H:%M:%SZ; }
cat >/tmp/hypevents/events.json <<JSON
[
  {"title": "Concert in the park", "description": "<p>Bring a <b>friend</b> &amp; dance</p>", "start": "$(day '+1 day')", "end": "$(day '+1 day 2 hours')", "hgurl": "http://127.0.0.1:8002:Sim1/50/60/70", "categories": ["Music"]},
  {"title": "Last week", "description": "Over", "start": "$(day '-3 days')", "end": "$(day '-3 days 1 hour')", "hgurl": "http://127.0.0.1:8002:Sim1/10/10/10", "categories": ["Art"]},
  {"title": "Nobody there", "description": "Region not found", "start": "$(day '+2 days')", "end": "$(day '+2 days 1 hour')", "hgurl": "http://127.0.0.1:9:Nowhere/1/2/3", "categories": ["Social"]},
  {"title": "Odd one", "description": "Unknown kind, no end", "start": "$(day '+3 days')", "end": "$(day '+3 days')", "hgurl": "http://127.0.0.1:8002:Sim1", "categories": ["something new"]},
  {"title": "Class", "description": "A lecture", "start": "$(day '+4 days')", "end": "$(day '+4 days 90 minutes')", "hgurl": "http://127.0.0.1:8002:Sim1/1/2/3", "categories": ["lecture"]}
]
JSON
(cd /tmp/hypevents && PHP_CLI_SERVER_WORKERS=2 setsid -f php -S 127.0.0.1:8098 /test/pages/hypevents-fake.php >/tmp/php-hypevents.log 2>&1)
wait_check 20 "the calendar answers" "[ \"\$(status http://127.0.0.1:8098/events.json)\" = 200 ]"
cfg HypEventsURL http://127.0.0.1:8098
sql "DELETE FROM events"
get $helpers/eventsparser.php >/dev/null
wait_check 60 "the events are in the search tables" "[ \"\$(sql 'SELECT COUNT(*) FROM events')\" -gt 0 ]"
sleep 3
event() { sql "SELECT $1 FROM events WHERE name = '$2'"; }
check "events: the events to come in a region that answers are kept, the others are not" "[ \"\$(sql 'SELECT COUNT(*) FROM events')\" = 3 ] && [ -z \"\$(event name 'Last week')\" ] && [ -z \"\$(event name 'Nobody there')\" ]"
check "events: the title and the description are text" "event description 'Concert in the park' | grep -q 'Bring a friend & dance' && ! event description 'Concert in the park' | grep -q '<b>'"
check "events: the description starts with the links to the region" "event description 'Concert in the park' | grep -q 'hop://127.0.0.1:8002/Sim1/50/60/70'"
check "events: the duration, in minutes, from the end" "[ \"\$(event duration 'Concert in the park')\" = 120 ] && [ \"\$(event duration 'Class')\" = 90 ]"
check "events: no end after the start is an hour" "[ \"\$(event duration 'Odd one')\" = 60 ]"
start=$(php -r 'echo strtotime(json_decode(file_get_contents("/tmp/hypevents/events.json"), true)[0]["start"]);')
check "events: the date is the start of the event, as a timestamp" "[ \"\$(event dateUTC 'Concert in the park')\" = \"\$start\" ]"
check "events: the category of the viewer for the one of the calendar (music, lecture, unknown)" "[ \"\$(event category 'Concert in the park')\" = 20 ] && [ \"\$(event category 'Class')\" = 27 ] && [ \"\$(event category 'Odd one')\" = 29 ]"
check "events: the grid of the event is its gatekeeper" "[ \"\$(event gatekeeperURL 'Concert in the park')\" = http://127.0.0.1:8002 ]"
x=$(sql "SELECT locX FROM regions WHERE regionName = 'Sim1'")
y=$(sql "SELECT locY FROM regions WHERE regionName = 'Sim1'")
check "events: the position is in the grid (the place in the region added to the place of the region)" "[ \"\$(event globalPos 'Concert in the park')\" = \"\$((x + 50)),\$((y + 60)),70\" ]" 
check "events: no place means the middle of the region" "[ \"\$(event globalPos 'Odd one')\" = \"\$((x + 128)),\$((y + 128)),25\" ]"
pkill -f 'php .*-S 127.0.0.1:809[8]' 2>/dev/null || true
cp -p /tmp/Helpers.ini.pages $helpers_ini

ts "pages: the routes follow the official settings"
cp -p /etc/opensim/grids/testgrid/OpenSim.ini /tmp/OpenSim.ini.pages
ini_set /etc/opensim/grids/testgrid/OpenSim.ini Search SearchURL https://2do.directory/helpers/query.php
check "routes: a service the grid sends to another site has no ghost on this one" "[ \"\$(status $helpers/query.php)\" = 404 ]"
ini_set /etc/opensim/grids/testgrid/OpenSim.ini Search SearchURL https://localhost/v3/query
check "routes: a service the grid puts elsewhere on this site is there, and only there" "[ \"\$(status $site/v3/query)\" != 404 ] && [ \"\$(status $helpers/query.php)\" = 404 ]"
cp -p /tmp/OpenSim.ini.pages /etc/opensim/grids/testgrid/OpenSim.ini
ini_set /etc/opensim/grids/testgrid/OpenSim.ini DataSnapshot DATA_SRV_Mistake "$helpers/query.php"
check "routes: a registration that points to query.php does not take the place of the search, which is still there, and the registration is served at its default address" "[ \"\$(status $helpers/query.php)\" = 405 ] && [ \"\$(status $helpers/register.php)\" = 400 ]"
cp -p /tmp/OpenSim.ini.pages /etc/opensim/grids/testgrid/OpenSim.ini
check "routes: without a word of the grid a service is at its default address" "[ \"\$(status $helpers/query.php)\" != 404 ]"

ts "pages: the health page"
rm -rf /tmp/opensim-helpers-cache
health_json() { get "$site/health?format=json" | php -r '$d = json_decode(stream_get_contents(STDIN), true); foreach ($d["groups"] as $g) { foreach ($g["rows"] as $r) { echo $g["title"], "|", $r["label"], "|", $r["url"], "|", $r["status"], "|", $r["level"], "\n"; } }'; }
health_files() { get "$site/health?format=json" | php -r '$d = json_decode(stream_get_contents(STDIN), true); foreach ($d["groups"] as $g) { foreach ($g["rows"] as $r) { echo $g["file"], "|", $g["title"], "|", $r["label"], "|", $r["tag"] ?? "", "\n"; } }'; }
health_json >/tmp/health.txt
check "health: a page for the addresses of the grid, in a browser" "get -A '$firefox' $site/health | grep -q 'health-table'"
check "health: at its own address only, it is for who manages the grid" "[ \"\$(status $site/health)\" = 200 ] && [ \"\$(status $helpers/health)\" = 404 ]"
check "the menu of the top bar has the pages the site serves, the current one marked" "get -A '$firefox' $site/health | tr -d '\\n' | grep -q '<a href=\"/\">Home</a>.*href=\"/helpers/guide.php\">Destinations</a>.*<a href=\"/health\" aria-current=\"page\">Health</a>'"
check "health: the sections are named as in the ini files, and the settings by their key" "grep -q '^\\[GridInfoService\\]|login|' /tmp/health.txt && grep -q '^\\[LoginService\\]|DestinationGuide|' /tmp/health.txt &&
    grep -q '^\\[Search\\]|SearchURL|' /tmp/health.txt && grep -q '^\\[DataSnapshot\\]|DATA_SRV_' /tmp/health.txt && grep -q '^\\[Messaging\\]|OfflineMessageURL|' /tmp/health.txt"
check "health: it starts with what the grid says, the login address is asked and answers with its code" "grep -qE '^\\|login\\|http://localhost:8002/?\\|200\\|ok' /tmp/health.txt && grep -qE '^\\|gridname\\|\\|\\|none' /tmp/health.txt"
check "health: the addresses are links" "get -A '$firefox' $site/health | grep -q '<a href=\"http://localhost:8002'"
check "health: an address that nothing serves gives the code of the error, not a word" "grep -qE '^\\|economy\\|https://localhost[^|]*\\|cURL error [0-9]+\\|error' /tmp/health.txt"
check "health: what the config says is compared with what the grid says: the same is fine" "grep -qE '^\\[GridInfoService\\]\\|login\\|[^|]*\\|OK\\|ok' /tmp/health.txt && grep -qE '^\\[GridInfoService\\]\\|economy\\|[^|]*\\|OK\\|ok' /tmp/health.txt"
check "health: the key of the currency is there, said defined, never shown" "grep -q '^\\[MoneyServer\\]|MoneyScriptAccessKey||OK|ok$' /tmp/health.txt && ! grep -qE '[0-9a-f]{32}' /tmp/health.txt"
check "the profile without a name is not a page: a teapot" "[ \"\$(status $site/profile)\" = 418 ]"
check "health: an address that is not defined is there too, empty" "grep -E '\\|\\|\\|none$' /tmp/health.txt | grep -q ."
check "health: the services of the helpers are there, with their address" "grep -qE 'query.php\\|https://localhost/helpers/query.php\\||SearchURL\\|https://localhost/helpers/query.php' /tmp/health.txt && grep -q '|currency.php|https://localhost/' /tmp/health.txt"
check "health: the database of Robust, named as in its section" "grep -q '^\\[DatabaseService\\]|ConnectionString||OK|ok$' /tmp/health.txt"
check "health: the guide has no source yet, and says so" "grep -q '^\\[Helpers\\]|GuideSource|||none$' /tmp/health.txt"
check "health: the public pages of the grid are there, as the settings of Helpers.ini name them" "grep -q '^\\[Helpers\\]|GridInfoURL|https://localhost/helpers/grid_info|' /tmp/health.txt && grep -q '^\\[Helpers\\]|GridStatusURL|https://localhost/helpers/grid_status|' /tmp/health.txt"
# Every address of [GridInfoService] is there, the ones the helpers do not serve too, with the answer where it leads
cp -p /etc/opensim/grids/testgrid/Robust.HG.ini /tmp/Robust.HG.ini.pages
ini_set /etc/opensim/grids/testgrid/Robust.HG.ini GridInfoService partner "$site/helpers/guide.php"
ini_set /etc/opensim/grids/testgrid/Robust.HG.ini GridInfoService elsewhere "$site/nothing-here"
rm -rf /tmp/opensim-helpers-cache
health_json >/tmp/health-more.txt
check "health: a setting of the config the grid does not say yet (a restart not done) is an error, with what the grid says" "grep -q '^\\[GridInfoService\\]|partner|$site/helpers/guide.php|differs|error' /tmp/health-more.txt && grep -q '^\\[GridInfoService\\]|elsewhere|$site/nothing-here|differs|error' /tmp/health-more.txt"
check "health: what the grid says has the profile without a name only, the one with the name of the owner of the grid (not of its services) is in the config" "grep -qE '^\\|web_profile_url\\|[^|]*/profile\\|' /tmp/health-more.txt && ! grep -qE '^\\|web_profile_url\\|[^|]*/profile/' /tmp/health-more.txt && grep -qE '^\\[GridInfoService\\]\\|web_profile_url\\|[^|]*/profile/Test\\.Owner\\|' /tmp/health-more.txt && ! grep -q 'GRID.SERVICES' /tmp/health-more.txt"
check "the address of the helpers itself is not a page, a teapot, and what is under it that is not known is not found" "[ \"\$(status $site/helpers)\" = 418 ] && [ \"\$(status $site/helpers/)\" = 418 ] && [ \"\$(status $site/helpers/nothing)\" = 404 ]"
check "a page called with another method than GET is refused, with the methods it answers" "[ \"\$(status -X POST $site/welcome)\" = 405 ] && curl -s -o /dev/null -D - -X POST $site/welcome | grep -qi '^allow: GET, HEAD'"
cp -p /tmp/Robust.HG.ini.pages /etc/opensim/grids/testgrid/Robust.HG.ini
cfg GuideSource /tmp/guide/destinations.txt
rm -rf /tmp/opensim-helpers-cache
check "health: the guide source, once the grid gives one" "health_json | grep -q '^\\[Helpers\\]|GuideSource||OK|ok$'"
cp -p /tmp/Helpers.ini.pages $helpers_ini
rm -rf /tmp/opensim-helpers-cache

ts "pages: the services refuse what is not a call, with the code that says why"
: >/tmp/php-pages.log
post() { curl -s -o /dev/null -m 60 -w '%{http_code}' -X POST "$@"; }
check "services: the ones called by XML-RPC refuse the wrong method, and say which one is right" "[ \"\$(status $helpers/query.php)\" = 405 ] && [ \"\$(status $helpers/offline.php)\" = 405 ] && [ \"\$(status $helpers/currency.php)\" = 405 ] && [ \"\$(status $helpers/landtool.php)\" = 405 ] && curl -s -o /dev/null -D - $helpers/query.php | grep -qi '^allow: POST'"
check "services: and an empty request is a bad request" "[ \"\$(post $helpers/query.php)\" = 400 ] && [ \"\$(post $helpers/offline.php/SaveMessage/)\" = 400 ] && [ \"\$(post $helpers/currency.php)\" = 400 ] && [ \"\$(post $helpers/landtool.php)\" = 400 ]"
check "services: the registration of a simulator without its host and port is a bad request, with an unknown service too" "[ \"\$(status $helpers/register.php)\" = 400 ] && [ \"\$(status '$helpers/register.php?host=127.0.0.1&port=9000&service=nothing')\" = 400 ]"
check "services: an unknown method of the offline messages is not found" "[ \"\$(echo '<a/>' | curl -s -o /dev/null -m 60 -w '%{http_code}' -X POST --data-binary @- $helpers/offline.php/Nothing/)\" = 404 ]"
check "services: nothing answers with a 500 for what is only a wrong call" "! grep -q '\\[500\\]' /tmp/php-pages.log"
# The same, as the health page tells it: a refusal is what is expected of a service called the wrong way, in green
cp -p /etc/opensim/grids/testgrid/OpenSim.ini /tmp/OpenSim.ini.pages
ini_set /etc/opensim/grids/testgrid/OpenSim.ini Search SearchURL "$helpers/query.php"
ini_set /etc/opensim/grids/testgrid/OpenSim.ini Messaging OfflineMessageURL "$helpers/offline.php"
ini_set /etc/opensim/grids/testgrid/OpenSim.ini DataSnapshot DATA_SRV_test "$helpers/register.php"
rm -rf /tmp/opensim-helpers-cache
health_json >/tmp/health-api.txt
check "health: a service is called with nothing, and must refuse, then with what it expects, and must answer" "[ \"\$(grep -c '^\\[Search\\]|SearchURL|$helpers/query.php|400|ok' /tmp/health-api.txt)\" = 1 ] && [ \"\$(grep -c '^\\[Search\\]|SearchURL|$helpers/query.php|200|ok' /tmp/health-api.txt)\" = 1 ]"
check "health: the offline messages and the registration the same, each in its section" "[ \"\$(grep -c '^\\[Messaging\\]|OfflineMessageURL|$helpers/offline.php|400|ok' /tmp/health-api.txt)\" = 1 ] && [ \"\$(grep -c '^\\[Messaging\\]|OfflineMessageURL|$helpers/offline.php|200|ok' /tmp/health-api.txt)\" = 1 ] && [ \"\$(grep -c '^\\[DataSnapshot\\]|DATA_SRV_test|$helpers/register.php|400|ok' /tmp/health-api.txt)\" = 1 ] && [ \"\$(grep -c '^\\[DataSnapshot\\]|DATA_SRV_test|$helpers/register.php|200|ok' /tmp/health-api.txt)\" = 1 ]"
check "health: the currency and the land services are checked twice each" "[ \"\$(grep -c '|currency.php|' /tmp/health-api.txt)\" = 2 ] && [ \"\$(grep -c '|landtool.php|' /tmp/health-api.txt)\" = 2 ]"
health_files >/tmp/health-files.txt
check "health: what the grid says first, then the files of the config" "head -1 /tmp/health-files.txt | grep -q '^get_grid_info||login|' && grep -q '^Robust.HG.ini|\\[GridInfoService\\]|' /tmp/health-files.txt && grep -q '^OpenSim.ini|\\[Search\\]|' /tmp/health-files.txt && grep -q '^MoneyServer.ini|\\[MoneyServer\\]|' /tmp/health-files.txt && grep -q '^Robust.HG.ini|\\[DatabaseService\\]|' /tmp/health-files.txt"
check "health: the web search where each setting says it (search of the grid info, SearchURL of the login service), apart from the calls of the API of OpenSim" "grep -q '^Robust.HG.ini|\\[GridInfoService\\]|search|Web search$' /tmp/health-files.txt && grep -q '^Robust.HG.ini|\\[LoginService\\]|SearchURL|Web search$' /tmp/health-files.txt && grep -q '^OpenSim.ini|\\[Search\\]|SearchURL|without data$' /tmp/health-files.txt && grep -q '^OpenSim.ini|\\[Search\\]|SearchURL|with valid XMLRPC request$' /tmp/health-files.txt"
cp -p /tmp/OpenSim.ini.pages /etc/opensim/grids/testgrid/OpenSim.ini
rm -rf /tmp/opensim-helpers-cache

ts "pages: the web search and the profiles"
owner=$(sql "SELECT PrincipalID FROM UserAccounts WHERE FirstName = 'Test' AND LastName = 'Owner'")
gk=http://play.example.org:8002
null=00000000-0000-0000-0000-000000000000
# What the tables of the search hold now is kept, the page is tried with its own
for table in parcels parcelsales regionsregister events classifieds; do sql "DROP TABLE IF EXISTS ${table}_bak2; CREATE TABLE ${table}_bak2 AS SELECT * FROM $table; DELETE FROM $table"; done
sql "INSERT INTO regionsregister (regionname, regionUUID, regionhandle, url, owner, owneruuid, gatekeeperURL) VALUES ('Alpha', 'aaaaaaaa-0000-4000-8000-0000000000a1', '1', 'http://127.0.0.1:9990/', 'Test Owner', '$owner', '$gk')"
sql "INSERT INTO parcels (parcelUUID, regionUUID, parcelname, landingpoint, description, searchcategory, build, script, public, dwell, infouuid, mature, gatekeeperURL, imageUUID) VALUES
    ('bbbbbbbb-0000-4000-8000-0000000000b1', 'aaaaaaaa-0000-4000-8000-0000000000a1', 'Garden of Alpha', '128/128/25', 'A quiet garden', '0', 'true', 'true', 'true', 12, 'cccccccc-0000-4000-8000-0000000000c1', 'PG', '$gk', '$null'),
    ('bbbbbbbb-0000-4000-8000-0000000000b3', 'aaaaaaaa-0000-4000-8000-0000000000a1', '<script>alert(1)</script>', '1/1/1', '', '0', 'true', 'true', 'true', 0, 'cccccccc-0000-4000-8000-0000000000c3', 'PG', '$gk', '$null')"
sql "INSERT INTO parcelsales (regionUUID, parcelname, parcelUUID, area, saleprice, landingpoint, infoUUID, dwell, parentestate, mature, gatekeeperURL) VALUES ('aaaaaaaa-0000-4000-8000-0000000000a1', 'Sale Meadow', 'bbbbbbbb-0000-4000-8000-0000000000b2', 2048, 500, '10/20/30', '$null', 0, 1, 'PG', '$gk')"
sql "INSERT INTO events (owneruuid, name, eventid, creatoruuid, category, description, dateUTC, duration, covercharge, coveramount, simname, parcelUUID, globalPos, eventflags, gatekeeperURL, landingpoint, parcelName, mature) VALUES ('$owner', 'Concert in the park', 1, '$owner', 20, 'Live music', UNIX_TIMESTAMP() + 86400, 60, 0, 0, 'Alpha', 'bbbbbbbb-0000-4000-8000-0000000000b1', '<1,2,3>', 0, '$gk', '1/2/3', 'Garden of Alpha', 'false')"
price=$(sql "SHOW COLUMNS FROM classifieds LIKE 'priceforlisting'")
sql "INSERT INTO classifieds (classifieduuid, creatoruuid, creationdate, expirationdate, category, name, description, parceluuid, parentestate, snapshotuuid, simname, posglobal, parcelname, classifiedflags${price:+, priceforlisting}) VALUES ('eeeeeeee-0000-4000-8000-0000000000e1', '$owner', UNIX_TIMESTAMP(), UNIX_TIMESTAMP() + 86400, '1', 'Land to rent', 'Cheap and quiet', '$null', 1, '$null', 'Alpha', '<1,2,3>', 'Meadow', 0${price:+, 0})"
search() { get -A "$firefox" "$site/search$1"; }
check "web search: the page of the grid, with its form, is served where Robust says it" "[ \"\$(status $site/search)\" = 200 ] && search '' | grep -q 'type=\"search\"' && [ \"\$(robust_path search)\" = /search ]"
check "web search: a word finds the places, with the link a viewer follows" "search '?q=garden' | grep -q 'href=\"hop://play.example.org:8002/Alpha/128/128/25\">Garden of Alpha</a>'"
check "web search: the land for sale, with its area and its price, under its own path" "search '/land/' | grep -q 'Sale Meadow' && search '/land/' | grep -q '2,048 m' && search '/land/' | grep -q '500' && ! search '/land/' | grep -q 'Garden of Alpha'"
check "web search: the events to come, the classifieds, the people (by the name asked)" "search '?q=concert' | grep -q 'Concert in the park' && search '?q=rent' | grep -q 'Land to rent' && search '?q=Test' | grep -q 'href=\"/profile/Test.Owner\"'"
check "web search: nothing found is said, and what has a meaning in a query is only a word" "search '?q=nothing-like-this' | grep -q 'Nothing found' && search '?q=%25' | grep -q 'Nothing found'"
check "web search: what the tables hold is text, not markup" "! search '?q=script' | grep -q '<script>alert' && search '?q=script' | grep -q '&lt;script&gt;alert(1)'"
check "web search: the people of a grid are not listed when nobody is asked for" "! search '' | grep -q 'Test Owner'"
check "web search: a viewer gets the content alone" "get -A '$firestorm' '$site/search?q=garden' | grep -q 'content-only' && ! get -A '$firestorm' '$site/search?q=garden' | grep -q 'class=\"topbar\"'"
check "web search: the home page has the form" "get -A '$firefox' $site/ | grep -q 'type=\"search\"'"
check "profile: the page of an avatar of the grid, its name and since when it is a member" "[ \"\$(status $site/profile/Test.Owner)\" = 200 ] && get -A '$firefox' $site/profile/Test.Owner | grep -q 'Test Owner' && get -A '$firefox' $site/profile/Test.Owner | grep -q 'Member since'"
check "profile: without a published profile, nothing more than that" "get -A '$firefox' $site/profile/Test.Owner | grep -q 'not published'"
if [ -n "$(sql "SHOW TABLES LIKE 'userprofile'")" ]; then
    sql "DELETE FROM userprofile WHERE useruuid = '$owner'; INSERT INTO userprofile (useruuid, profilePartner, profileAllowPublish, profileMaturePublish, profileURL, profileWantToMask, profileWantToText, profileSkillsMask, profileSkillsText, profileLanguages, profileImage, profileAboutText, profileFirstImage, profileFirstText) VALUES ('$owner', '$null', UNHEX('01'), UNHEX('00'), 'https://owner.example.org/', 0, 'Make friends', 0, 'Scripting', 'English', '$null', 'About <b>me</b>', '$null', 'By the sea')"
    check "profile: what the avatar published is there, as text, and its web page is a link" "get -A '$firefox' $site/profile/Test.Owner | grep -q 'About &lt;b&gt;me&lt;/b&gt;' && get -A '$firefox' $site/profile/Test.Owner | grep -q 'href=\"https://owner.example.org/\"' && get -A '$firefox' $site/profile/Test.Owner | grep -q 'By the sea'"
    sql "UPDATE userprofile SET profileAllowPublish = UNHEX('00') WHERE useruuid = '$owner'"
    check "profile: what is not published is not shown" "! get -A '$firefox' $site/profile/Test.Owner | grep -q 'About' && get -A '$firefox' $site/profile/Test.Owner | grep -q 'not published'"
    sql "DELETE FROM userprofile WHERE useruuid = '$owner'"
fi
check "profile: an avatar that is not there is a page that says so, with a 404, and the account of the services has no profile" "[ \"\$(status $site/profile/Nobody.Here)\" = 404 ] && get -A '$firefox' $site/profile/Nobody.Here | grep -q 'no such avatar' && [ \"\$(status $site/profile/GRID.SERVICES)\" = 404 ]"
for table in parcels parcelsales regionsregister events classifieds; do sql "DELETE FROM $table; INSERT INTO $table SELECT * FROM ${table}_bak2; DROP TABLE ${table}_bak2"; done

ts "pages: the directory"
# The directory of the search, with what the test puts in it: a grid that answers, two regions of one that does not, an
# address with markup (anybody can register one)
sql "DROP TABLE IF EXISTS parcels_bak, regionsregister_bak; CREATE TABLE parcels_bak AS SELECT * FROM parcels; CREATE TABLE regionsregister_bak AS SELECT * FROM regionsregister; DELETE FROM parcels; DELETE FROM regionsregister"
directory=$helpers/directory_info.php
: >/tmp/php-pages.log
empty=$(get $directory)
check "directory: an empty directory is a page with nothing in it" "echo \"\$empty\" | grep -q '<strong>0 grids</strong>, 0 regions, 0 parcels' && ! grep -q 'Undefined' /tmp/php-pages.log"
region() { # uuid, name, gatekeeper, parcels
    sql "INSERT INTO regionsregister (regionname, regionUUID, regionhandle, url, owner, owneruuid, gatekeeperURL) VALUES ('$2', '$1', '1', '$3', 'Owner', '$1', '$3')"
    local i
    for i in $(seq "$4"); do
        sql "INSERT INTO parcels (parcelUUID, regionUUID, parcelname, landingpoint, description, searchcategory, build, script, public, gatekeeperURL) VALUES (UUID(), '$1', 'Parcel $i', '128/128/25', '', 'Any', 'true', 'true', 'true', '$3')"
    done
}
region aaaaaaaa-0000-4000-8000-000000000001 'Home' http://127.0.0.1:8002 2
region bbbbbbbb-0000-4000-8000-000000000001 'Far one' http://127.0.0.1:9 1
region bbbbbbbb-0000-4000-8000-000000000002 'Far two' http://127.0.0.1:9 1
region cccccccc-0000-4000-8000-000000000001 'Odd' 'http://x"><script>alert(1)</script>' 1
page=$(get $directory)
check "directory: the totals of the grids that registered" "echo \"\$page\" | grep -q '<strong>3 grids</strong>, 4 regions, 5 parcels'"
check "directory: a grid is shown by its name, which its gatekeeper tells, with its numbers" "echo \"\$page\" | grep -q '<a href=\"?gatekeeperURL=http%3A%2F%2F127.0.0.1%3A8002\">Testgrid</a> (1 region, 2 parcels)'"
check "directory: a grid that does not answer is shown by its address" "echo \"\$page\" | grep -q '<a href=\"?gatekeeperURL=http%3A%2F%2F127.0.0.1%3A9\">127.0.0.1:9</a> (2 regions, 2 parcels)'"
check "directory: what a grid registered is text, not markup" "! echo \"\$page\" | grep -q '<script>alert' && echo \"\$page\" | grep -q '&lt;script&gt;alert(1)'"
check "directory: no warning in the log" "! grep -q 'Undefined\|Warning' /tmp/php-pages.log"
sql "DELETE FROM parcels; DELETE FROM regionsregister; INSERT INTO parcels SELECT * FROM parcels_bak; INSERT INTO regionsregister SELECT * FROM regionsregister_bak; DROP TABLE parcels_bak, regionsregister_bak"

cp -p /tmp/Helpers.ini.pages $helpers_ini
pkill -f 'php .*-S 127.0.0.1:809[0]' 2>/dev/null || true
