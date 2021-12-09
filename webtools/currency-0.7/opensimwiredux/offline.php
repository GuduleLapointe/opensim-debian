<?php

require_once('../include/env_interface.php');


//$request_xml = $HTTP_RAW_POST_DATA;
//error_log("offline.php: ".$request_xml);


//
if (!opensim_is_access_from_region_server()) {
	$remote_addr = $_SERVER["REMOTE_ADDR"];
	error_log("offline.php: Illegal access from ".$remote_addr);
	exit;
}


$DbLink = new DB(OFFLINE_DB_HOST, OFFLINE_DB_NAME, OFFLINE_DB_USER, OFFLINE_DB_PASS);

$method = $_SERVER["PATH_INFO"];


if ($method == "/SaveMessage/") {
	$msg = $HTTP_RAW_POST_DATA;
	$start = strpos($msg, "?>");

	if ($start != -1) {
		$start+=2;
		$msg   = substr($msg, $start);
		//$parts = split("[<>]", $msg);
		$parts = preg_split("/[<>]/", $msg);
		$from_agent = $parts[4];
		$to_agent   = $parts[12];

		if (isGUID($from_agent) and isGUID($to_agent)) {
			$query_str = "INSERT INTO ".OFFLINE_MESSAGE_TBL." (to_uuid,from_uuid,message) VALUES ('".$to_agent."','".$from_agent."','".mysql_escape_string($msg)."')";
			$DbLink->query($query_str);

			if ($DbLink->Errno==0) {
				echo '<?xml version="1.0" encoding="utf-8"?><boolean>true</boolean>';
				exit;
			}
		}
	}

	echo '<?xml version="1.0" encoding="utf-8"?><boolean>false</boolean>';
	exit;
}


if ($method == "/RetrieveMessages/") {
	$parms = $HTTP_RAW_POST_DATA;
	//$parts = split("[<>]", $parms);
	$parts = preg_split("/[<>]/", $parms);
	$agent_id = $parts[6];
	$errno = -1;

	if (isGUID($agent_id)) {
		$DbLink->query("SELECT message FROM ".OFFLINE_MESSAGE_TBL." WHERE to_uuid='".$agent_id."'");
		$errno = $DbLink->Errno;
	}

	echo '<?xml version="1.0" encoding="utf-8"?>';
	echo '<ArrayOfGridInstantMessage xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema">';

	if ($errno==0) {
		while(list($message) = $DbLink->next_record()) {
			echo $message;
		}
	}
	echo '</ArrayOfGridInstantMessage>';
	   
	if ($errno==0) {
		$DbLink->query("DELETE FROM ".OFFLINE_MESSAGE_TBL." WHERE to_uuid='".$agent_id."'");
	}
	exit;
}


?>
