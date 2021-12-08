<?php
//
// Modified by Fumi.Iseki for XoopenSim/Modlos
//

//require_once('./search_config.php');
if (defined('ENV_READED_INTERFACE')) require_once(ENV_HELPER_PATH.'/search_config.php');	// for command line
else 							  	 require_once('./search_config.php');


// for Debug
//$request_xml = $HTTP_RAW_POST_DATA;
//error_log("parser.php: ".$request_xml);


// xmlrpc_encode has not output options for UTF-8
$utf8_encoding = false;
if ($GLOBALS['xmlrpc_internalencoding']=='UTF-8')
{
	$utf8_encoding = true;
	mb_internal_encoding('utf-8');
}


global $now;
global $DbLink;

$now = time();

// MySQL DataBase
$DbLink = new DB($DB_HOST, $DB_NAME, $DB_USER, $DB_PASSWORD);



////////////////////////////////////////////////////////////////
//
//

function GetURL($host, $port, $url)
{
	$url = "http://$host:$port/$url";

	$ch = curl_init();
	curl_setopt($ch, CURLOPT_URL, $url);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
	curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
	curl_setopt($ch, CURLOPT_TIMEOUT, 30);

	$data = curl_exec($ch);
	if (curl_errno($ch) == 0)
	{
		curl_close($ch);
		return $data;
	}

	curl_close($ch);
	return "";
}



function CheckHost($hostname, $port)
{
	global $now;
	global $DbLink;

	$xml = GetURL($hostname, $port, "?method=collector");
	if ($xml == "")	$failcounter = "failcounter + 1"; 		//No data was retrieved? (CURL may have timed out)
	else 			$failcounter = "0";

	//Update nextcheck to be 10 minutes from now. The current OS instance
	//won't be checked again until at least this much time has gone by.
	$next = $now + 600;

	$query_str = "UPDATE ".SEARCH_HOSTSREGISTER_TBL." SET nextcheck=$next, checked=1, failcounter=".$failcounter.
						//" WHERE host='".$DbLink->escape($hostname)."' AND port='".$DbLink->escape($port)."'";
						" WHERE host='".$hostname."' AND port='".$port."'";
	$DbLink->query($query_str);

	if ($xml != "") {
		//error_log("parser.php: ".$xml);
		parse($hostname, $port, $xml);
	}
}




function parse($hostname, $port, $xml)
{
	global $now;
	global $DbLink;


	///////////////////////////////////////////////////////////////////////
	//
	// Search engine sim scanner
	//

	//
	// Load XML doc from URL
	//
	$objDOM = new DOMDocument();
	$objDOM->resolveExternals = false;

	//Don't try and parse if XML is invalid or we got an HTML 404 error.
	if ($objDOM->loadXML($xml) == False) return;

	//
	// Get the region data to update
	//
	$regiondata = $objDOM->getElementsByTagName("regiondata");

	//If returned length is 0, collector method may have returned an error
	if ($regiondata->length == 0) return;

	$regiondata = $regiondata->item(0);

	//
	// Update nextcheck so this host entry won't be checked again until after
	// the DataSnapshot module has generated a new set of data to be parsed.
	//
	$expire = $regiondata->getElementsByTagName("expire")->item(0)->nodeValue;
	$next = $now + $expire;

	$query_str = "UPDATE ".SEARCH_HOSTSREGISTER_TBL." SET nextcheck = $next ".
						"WHERE host='".$DbLink->escape($hostname)."' AND port='".$DbLink->escape($port)."'";
	$DbLink->query($query_str);

	//
	// Get the region data to be saved in the database
	//
	$regionlist = $regiondata->getElementsByTagName("region");

	foreach ($regionlist as $region)
	{
		$regioncategory = $region->getAttributeNode("category")->nodeValue;

		//
		// Start reading the Region info
		//
		$info 		  = $region->getElementsByTagName("info")->item(0);
		$regionuuid   = $info->getElementsByTagName("uuid")->item(0)->nodeValue;
		$regionname   = $info->getElementsByTagName("name")->item(0)->nodeValue;
		$regionhandle = $info->getElementsByTagName("handle")->item(0)->nodeValue;
		$url 		  = $info->getElementsByTagName("url")->item(0)->nodeValue;

		//
		// First, check if we already have a region that is the same
		//
		$itm = "regionname, regionuuid, regionhandle, url, owner, owneruuid";
		$sql = "SELECT ".$itm." FROM ".SEARCH_REGIONS_TBL." WHERE regionuuid='".$DbLink->escape($regionuuid)."'";

		$DbLink->query($sql);
		if ($DbLink->num_rows() > 0)
		{
			$DbLink->query("DELETE FROM ".SEARCH_REGIONS_TBL.	 " WHERE regionUUID = '".$DbLink->escape($regionuuid)."'");
			$DbLink->query("DELETE FROM ".SEARCH_PARCELS_TBL.	 " WHERE regionUUID = '".$DbLink->escape($regionuuid)."'");
			$DbLink->query("DELETE FROM ".SEARCH_ALLPARCELS_TBL. " WHERE regionUUID = '".$DbLink->escape($regionuuid)."'");
			$DbLink->query("DELETE FROM ".SEARCH_PARCELSALES_TBL." WHERE regionUUID = '".$DbLink->escape($regionuuid)."'");
			$DbLink->query("DELETE FROM ".SEARCH_OBJECTS_TBL.	 " WHERE regionUUID = '".$DbLink->escape($regionuuid)."'");
		}

		$data 	  = $region->getElementsByTagName("data")->item(0);
		$estate   = $data->getElementsByTagName("estate")->item(0);
		$username = $estate->getElementsByTagName("name")->item(0)->nodeValue;
		$useruuid = $estate->getElementsByTagName("uuid")->item(0)->nodeValue;
		$estateid = $estate->getElementsByTagName("id")->item(0)->nodeValue;

		//
		// Second, add the new info to the database
		//
		$itm = "regionname, regionuuid, regionhandle, url, owner, owneruuid".
		$sql = "INSERT INTO ".SEARCH_REGIONS_TBL." (".$itm.") VALUES('".
								$DbLink->escape($regionname)."','".
								$DbLink->escape($regionuuid)."','".
								$DbLink->escape($regionhandle)."','".
								$DbLink->escape($url)."','".
								$DbLink->escape($username)."','".
								$DbLink->escape($useruuid)."')";

		$DbLink->query($sql);

		//
		// Start reading the parcel info
		//
		$parcel = $data->getElementsByTagName("parcel");

		foreach ($parcel as $value)
		{
			$parcelname 		= $value->getElementsByTagName("name")->item(0)->nodeValue;
			$parceluuid 		= $value->getElementsByTagName("uuid")->item(0)->nodeValue;
			$infouuid 			= $value->getElementsByTagName("infouuid")->item(0)->nodeValue;
			$parcellanding 		= $value->getElementsByTagName("location")->item(0)->nodeValue;
			$parceldescription 	= $value->getElementsByTagName("description")->item(0)->nodeValue;
			$parcelarea 		= $value->getElementsByTagName("area")->item(0)->nodeValue;
			$parcelcategory 	= $value->getAttributeNode("category")->nodeValue;
			$parcelsaleprice 	= $value->getAttributeNode("salesprice")->nodeValue;
			$dwell 				= $value->getElementsByTagName("dwell")->item(0)->nodeValue;
			$owner 				= $value->getElementsByTagName("owner")->item(0);
			$owneruuid 			= $owner->getElementsByTagName("uuid")->item(0)->nodeValue;


			// Adding support for groups
			$group = $value->getElementsByTagName("group")->item(0);
			
			if ($group != "") $groupuuid = $group->getElementsByTagName("groupuuid")->item(0)->nodeValue;
			else 			  $groupuuid = "00000000-0000-0000-0000-000000000000";

			//
			// Check bits on Public, Build, Script
			//
			$parcelforsale   = $value->getAttributeNode("forsale")->nodeValue;
			$parceldirectory = $value->getAttributeNode("showinsearch")->nodeValue;
			$parcelbuild 	 = $value->getAttributeNode("build")->nodeValue;
			$parcelscript	 = $value->getAttributeNode("scripts")->nodeValue;
			$parcelpublic	 = $value->getAttributeNode("public")->nodeValue;

			//
			// Save
			//
			$itm = "regionUUID, parcelname, ownerUUID, groupUUID, landingpoint, parcelUUID, infoUUID, parcelarea";
			$sql = "INSERT INTO ".SEARCH_ALLPARCELS_TBL." (".$itm.") VALUES('".
								$DbLink->escape($regionuuid)."','".
								$DbLink->escape($parcelname)."','".
								$DbLink->escape($owneruuid)."','".
								$DbLink->escape($groupuuid)."','".
								$DbLink->escape($parcellanding)."','".
								$DbLink->escape($parceluuid)."','".
								$DbLink->escape($infouuid)."','".
								$DbLink->escape($parcelarea)."' )";
			//error_log("insert parcel region = ".$regionuuid."  parcel = ".$parceluuid."  show = ".$parceldirectory);
			$DbLink->query($sql);

			if ($parceldirectory == "true")
			{
				$itm = "regionUUID,parcelname,parcelUUID,landingpoint,description,searchcategory,build,script,public,dwell,infouuid,mature";
				$sql = "INSERT INTO ".SEARCH_PARCELS_TBL." (".$itm.") VALUES('".
									$DbLink->escape($regionuuid)."','".
									$DbLink->escape($parcelname)."','".
									$DbLink->escape($parceluuid)."','".
									$DbLink->escape($parcellanding)."','".
									$DbLink->escape($parceldescription)."','".
									$DbLink->escape($parcelcategory)."','".
									$DbLink->escape($parcelbuild)."','".
									$DbLink->escape($parcelscript)."','".
									$DbLink->escape($parcelpublic)."','".
									$DbLink->escape($dwell)."','".
									$DbLink->escape($infouuid)."','".
									$DbLink->escape($regioncategory)."')";
				//error_log("parser.php: parcels = ".$sql);
				$DbLink->query($sql);
			}

			if ($parcelforsale == "true")
			{
				$itm = "regionUUID,parcelname,parcelUUID,area,saleprice,landingpoint,infoUUID,dwell,parentestate,mature";
				$sql = "INSERT INTO ".SEARCH_PARCELSALES_TBL." (".$itm.") VALUES('" .
									$DbLink->escape($regionuuid)."','".
									$DbLink->escape($parcelname)."','".
									$DbLink->escape($parceluuid)."','".
									$DbLink->escape($parcelarea)."','".
									$DbLink->escape($parcelsaleprice)."','".
									$DbLink->escape($parcellanding)."','".
									$DbLink->escape($infouuid)."', '".
									$DbLink->escape($dwell)."', '".
									$DbLink->escape($estateid)."', '".
									$DbLink->escape($regioncategory)."')";

				//error_log("parser.php: parcelsales = ".$sql);
				$DbLink->query($sql);
			}
		}


		//
		// Handle objects
		//
		$objects = $data->getElementsByTagName("object");

		foreach ($objects as $value)
		{
			$uuid		 = $value->getElementsByTagName("uuid")->item(0)->nodeValue;
			$regionuuid  = $value->getElementsByTagName("regionuuid")->item(0)->nodeValue;
			$parceluuid  = $value->getElementsByTagName("parceluuid")->item(0)->nodeValue;
			$location 	 = $value->getElementsByTagName("location")->item(0)->nodeValue;
			$title 		 = $value->getElementsByTagName("title")->item(0)->nodeValue;
			$description = $value->getElementsByTagName("description")->item(0)->nodeValue;
			$flags 		 = $value->getElementsByTagName("flags")->item(0)->nodeValue;

			$itm = "objectuuid, parceluuid, location, name, description, regionuuid";
			$sql = "INSERT INTO ".SEARCH_OBJECTS_TBL." (".$itm.") VALUES('" .
								$DbLink->escape($uuid)."','".
								$DbLink->escape($parceluuid)."','".
								$DbLink->escape($location)."','".
								$DbLink->escape($title)."','".
								$DbLink->escape($description)."','".
								$DbLink->escape($regionuuid)."')";
			//error_log("parser.php: object = ".$sql);
			$DbLink->query($sql);
		}
	}
}



function delete_old_events($hour=24)
{
	global $now;
	global $DbLink;

	$expire = $now - $hour*3600;

    $query_str = "DELETE FROM ".SEARCH_EVENTS_TBL." WHERE dateUTC<'".$expire."'";
    $DbLink->query($query_str);
}




//
// If the sql query returns no rows, all entries in the hostsregister
// table have been checked. Reset the checked flag and re-run the
// query to select the next set of hosts to be checked.
//

$DbLink2 = new DB($DB_HOST, $DB_NAME, $DB_USER, $DB_PASSWORD);

$sql = "SELECT host,port FROM ".SEARCH_HOSTSREGISTER_TBL." WHERE nextcheck<$now AND checked=0 LIMIT 0,50";
//error_log("parser.php: ".$sql);
$DbLink2->query($sql);

if ($DbLink2->num_rows()>0)
{
	while (($jobs = $DbLink2->next_record()))
	{
		CheckHost($jobs[0], $jobs[1]);
	}
}
else
{
	$DbLink2->query("UPDATE ".SEARCH_HOSTSREGISTER_TBL." SET checked=0");
	$DbLink2->query($sql);
}


// for Expire of Events
delete_old_events(8760);		// 1 year

?>
