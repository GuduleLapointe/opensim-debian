<?
if (!isset($_GET["coords"]) || !isset($_GET["size"]) || !isset($_GET["scopeid"]) || !isset($_GET["overlays"]) || !isset($_GET["user"]))
	exit;

$c = explode("x", $_GET["coords"]);
$scope_id = mysql_escape_string($_GET["scopeid"]);
$overlays = $_GET["overlays"] | 0;
$user = mysql_escape_string($_GET["user"]);
$xx = mysql_escape_string($c[1]);
$yy = mysql_escape_string($c[2]);
$x = mysql_escape_string($c[1]) * 256;
$y = mysql_escape_string($c[2]) * 256;
$size = $_GET["size"] + 0;

function get_config()
{
	$configfile = "configs/app.cfg";
	$x = parse_ini_file($configfile, true);
	return $x["Grid"];
}

$db = new Database(get_config());

$xmin = $x - 256;
$xmax = $x + 256;
$ymin = $y - 256;
$ymax = $y + 256;

if ($scope_id != "00000000-0000-0000-0000-000000000000")
	$res = $db->query("select * from regions where locX between $xmin and $xmax  and locY between $ymin and $ymax and ScopeID='$scope_id'");
else
	$res = $db->query("select * from regions where locX between $xmin and $xmax  and locY between $ymin and $ymax");

$row = false;
$has_owned_neighbors = false;
$has_managed_neighbors = false;
$has_other_neighbors = false;
$is_owned = false;
while($region = mysql_fetch_array($res))
{
	if ($region["locX"] == $x && $region["locY"] == $y)
		$row = $region;
	else
	{
        if ($region["owner_uuid"] == $user)
            $has_owned_neighbors = true;
        else
            $has_other_neighbors = true;
	}
}

if (!$row)
{
    echo "<span class=\"map-tooltip\">$xx,$yy</span>\n\n";
}
else
{
    echo "<span class=\"map-tooltip\">" . $row["regionName"] . " $xx,$yy</span>\n";
    echo "regionimg.php?size=$size&uuid=" . $row["regionMapTexture"] . "\n";

    if ($row["owner_uuid"] == $user)
        $is_owned = true;

}

$extrastyle = "";

#echo "<img class=\"data\" src=\"images/redgrid.png\" width=\"$size\"/>";
if ($row && $size > 64)
	echo "<input class=\"data\" type=\"hidden\" value=\"no\" />";
	echo "<p class=\"data map-regionname $extrastyle\">" . $row["regionName"] . "</p>";
