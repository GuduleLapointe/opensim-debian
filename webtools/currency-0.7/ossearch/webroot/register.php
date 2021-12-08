<?php
//////////////////////////////////////////////////////////////////////////////
// register.php															 //
// (C) 2008, Fly-man-													   //
// This file contains the registration of a simulator to the database	   //
// and checks if the simulator is new in the database or a reconnected one  //
//																		  //
// If the simulator is old, check if the nextcheck date > registration	  //
// When the date is older, make a request to the Parser to grab new data	//
//////////////////////////////////////////////////////////////////////////////

//
// Modified by Fumi.Iseki for XoopenSim/Modlos
//

require_once('./search_config.php');

//
/*
if (!opensim_is_access_from_region_server()) {
	$remote_addr = $_SERVER["REMOTE_ADDR"];
	error_log("register.php: Illegal access from ".$remote_addr);
	exit;
}
*/


// MySQL DataBase
$DbLink = new DB($DB_HOST, $DB_NAME, $DB_USER, $DB_PASSWORD);



////////////////////////////////////////////////////////////////////////////////////
//
//

$host = $_GET['host'];
$port = $_GET['port'];
if (!isAlphabetNumericSpecial($host)) exit;
if (!isNumeric($port)) exit;

$timestamp = $_SERVER['REQUEST_TIME'];
if (!isNumeric($timestamp)) exit;

$service = $_GET['service'];
$host = $DbLink->escape($host);


if ($service == "online")
{
	// Check if there is already a database row for this host
	$query = "SELECT register FROM ".SEARCH_HOSTSREGISTER_TBL." WHERE host='".$host."' AND port='".$port."'";
	//error_log("register.php: query = ".$query);
	$DbLink->query($query);

	// if greater than 1, check the nextcheck date
	if ($DbLink->num_rows() > 0)
	{
		$update = "UPDATE ".SEARCH_HOSTSREGISTER_TBL." SET register = '".$timestamp."',nextcheck='0',checked='0',failcounter='0' ".  
													 " WHERE host = '".$host."' AND port = '".$port."'";
		//error_log("register.php: update = ".$update);
		$DbLink->query($update);
	}
	else
	{
		$register = "INSERT INTO ".SEARCH_HOSTSREGISTER_TBL." (host,port,register,nextcheck,checked,failcounter) ".
														" VALUES ('".$host."','".$port."','".$timestamp."', 0, 0, 0)";
		//error_log("register.php: regist = ".$register);
		$DbLink->query($register);
	}
}

elseif ($service = "offline")
{
	$delete = "DELETE FROM ".SEARCH_HOSTSREGISTER_TBL." WHERE host='".$host."' AND port='".$port."'";
	//error_log("register.php: delete = ".$delete);
	$DbLink->query($delete);
}

?>

