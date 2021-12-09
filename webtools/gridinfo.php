<?php
# Authors:	Gudule Lapointe, Olivier van Helden (Speculoos.net)
# http://www.speculoos.world/opensim-toolbox
# Reditribution, modification and use are authorized, provided that 
# this disclaimer, the authors name and Speculoos web address stay in the redistributed code
#
# Display grid info statistics on a web page
# requires cacheusers table, and opensim-cacheusers scripts to fill this this table
# Complete code may be obtained from the above address
# config.php may be shared with other third-party modules like groups, Offline message or OpenSim Services Bundle.

require("config.php");

$dbConnect = mysql_connect("$dbHost:$dbPort", $dbUser, $dbPassword);
mysql_select_db($dbName, $dbConnect);
if (!$dbConnect) {
	die('Database connection failed');
	// mysql_error());
}

function singleQuery($query)
{
	global $dbConnect;
	list($string) = mysql_fetch_row(mysql_query($query));
	return $string;
}

function arrayToHtml($stats) {
	$html="<table class=gridinfo>";
	foreach($stats as $label => $value)
	{
		$html.="<tr><th align=left>$label</th><td align=right>$value</td></tr>";
	}
	$html.="</div>";
	return $html;
}

$lastmonth=time() - 30*86400;

$stats['Users in world'] = singleQuery("SELECT COUNT(*) FROM GridUser WHERE Online = 'true'");
$stats['Local active users (30d)'] = singleQuery("SELECT COUNT(*) FROM GridUser WHERE Login > $lastmonth");
$stats['Total active users (30d)'] = singleQuery("SELECT COUNT(*) FROM cacheusers WHERE Login > $lastmonth");
$stats['Total users'] = singleQuery("SELECT COUNT(*) FROM UserAccounts");
$stats['Total regions'] = singleQuery("SELECT COUNT(*) FROM Regions");

echo arrayToHtml($stats);
?>