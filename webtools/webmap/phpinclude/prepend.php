<?
#
# Find out where we are 
# This is mucho hackish, but we need it to fix things up for Smarty
# so it won't demand "configs" dirs all over the place
#

if(!isset($auto_prepend_file))
	$auto_prepend_file = "phpinclude/prepend.php";

$incpath = dirname($_SERVER["SCRIPT_FILENAME"]) . "/" .
        dirname($auto_prepend_file);
$lastslash = strrpos($incpath, "/");

$configpath = substr($incpath, 0, $lastslash+1) . "configs";
ini_set("include_path", ini_get("include_path") . ":" . $incpath);

require("Database.class.php");

function GetDB($databasename)
{
    global $configpath;

    static $database = array();

    if (isset($database[$databasename]))
        return $database[$databasename];

    $parameters = parse_ini_file($configpath."/app.cfg", true);

    $database[$databasename] = new Database($parameters[$databasename]);

    return $database[$databasename];
}

#
# Remap a result set as an array of arrays
#

function RemapData($resultset)
{
    $data = array();

    while($row = mysql_fetch_assoc($resultset))
        $data[] = $row;

    return $data;
}

define('ROW_LIMIT', 1000);
