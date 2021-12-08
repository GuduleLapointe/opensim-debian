<?
ini_set("auto_prepend_file", "phpinclude/prepend.php");
require_once("phpinclude/prepend.php");

if (!isset($_GET["coords"]) || !isset($_GET["scopeid"]) || !isset($_GET["user"]))
	exit;

$c = explode("x", $_GET["coords"]);
$scope_id = mysql_escape_string($_GET["scopeid"]);
$user = mysql_escape_string($_GET["user"]);
$xx = mysql_escape_string($c[1]);
$yy = mysql_escape_string($c[2]);
$x = mysql_escape_string($c[1]) * 256;
$y = mysql_escape_string($c[2]) * 256;

function get_config()
{
	$configfile = "configs/app.cfg";
	$x = parse_ini_file($configfile, true);
	return $x["Grid"];
}

$db = new Database(get_config());

if ($scope_id != "00000000-0000-0000-0000-000000000000")
	$res = $db->query("select * from regions where locX = '$x' and locY = '$y' and ScopeID='$scope_id'");
else
	$res = $db->query("select * from regions where locX = '$x' and locY = '$y'");

$row = false;
$has_owned_neighbors = false;
$has_managed_neighbors = false;
$has_other_neighbors = false;
$is_owned = false;
$row = mysql_fetch_array($res);

if ($row)
{
	$user_row = $db->query_one("select * from UserAccounts where PrincipalID='" . mysql_escape_string($row["owner_uuid"]) . "'");

	echo "Region: " . $row["regionName"] . "<br>";
	echo "Owner: " . $user_row["FirstName"] . " " . $user_row["LastName"] . "<br>";
	echo "Location: " . $x / 256 . " x " . $y / 256 . "<br>";
	exit;
}
