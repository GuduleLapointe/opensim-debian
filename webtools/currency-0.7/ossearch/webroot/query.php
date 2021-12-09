<?php
//The description of the flags used in this file are being based on the
//DirFindFlags enum which is defined in OpenMetaverse/DirectoryManager.cs
//of the libopenmetaverse library.

//
// Modified by Fumi.Iseki for XoopenSim/Modlos
//

require_once('./search_config.php');


// for Debug
//$request_xml = $HTTP_RAW_POST_DATA;
//error_log("query.php: ".$request_xml);


//
if (!opensim_is_access_from_region_server()) {
	$remote_addr = $_SERVER["REMOTE_ADDR"];
	error_log("query.php: Illegal access from ".$remote_addr);
	exit;
}


// xmlrpc_encode has not output options for UTF-8
$utf8_encoding = false;
if ($GLOBALS['xmlrpc_internalencoding']=='UTF-8')
{
	$utf8_encoding = true;
	mb_internal_encoding('utf-8');
}


// MySQL DataBase
$DbLink = new DB($DB_HOST, $DB_NAME, $DB_USER, $DB_PASSWORD);



#
#  Copyright (c)Melanie Thielker (http://opensimulator.org/)
#

###################### No user serviceable parts below #####################

//Join a series of terms together with optional parentheses around the result.
//This function is used in place of the simpler join to handle the cases where
//one or more of the supplied terms are an empty string. The parentheses can
//be added when mixing AND and OR clauses in a SQL query.

function join_terms($glue, $terms, $add_paren)
{
	if (count($terms) > 1)
	{
		$type = join($glue, $terms);
		if ($add_paren == True)	$type = "(" . $type . ")";
	}
	else
	{
		if (count($terms) > 0)	$type = $terms[0];
		else				 	$type = "";
	}

	return $type;
}


function process_region_type_flags($flags)
{
	$terms = array();

	if (OPENSIM_PG_ONLY) {
		$terms[] = "mature = 'PG'";
	}
	else {
		if ($flags & 16777216) $terms[] = "mature = 'PG'"; 		//IncludePG (1 << 24)
		if ($flags & 33554432) $terms[] = "mature = 'Mature'"; 	//IncludeMature (1 << 25)
		if ($flags & 67108864) $terms[] = "mature = 'Adult'";	//IncludeAdult (1 << 26)
	}

	return join_terms(" OR ", $terms, True);
}



#
# The XMLRPC server object
#

$xmlrpc_server = xmlrpc_server_create();



#
# Places Query
#

xmlrpc_server_register_method($xmlrpc_server, "dir_places_query", "dir_places_query");


function dir_places_query($method_name, $params, $app_data)
{
	global $DbLink;

	$req		 = $params[0];
	$text		 = $req['text'];
	$category	 = $req['category'];
	$query_start = $req['query_start'];

	//$pieces = explode(" ", $text);
	$pieces = preg_split("/ /", $text, 0, PREG_SPLIT_NO_EMPTY);
	$text 	= join("%", $pieces);

	if ($text == "%%%")
	{
		$response_xml = xmlrpc_encode(array('success'	   => False,
											'errorMessage' => "Invalid search terms"));
		echo $response_xml;
		return;
	}

	$terms = array();

	$type = process_region_type_flags($flags);
	if ($type != "")   $type 	 = " AND " . $type;
	if ($flags & 1024) $order 	 = "dwell DESC,";
	if ($category > 0) $category = "searchcategory = '".$DbLink->escape($category)."' AND ";
	else 			   $category = "";


	//$itm = "regionUUID,parcelname,parcelUUID,landingpoint,description,searchcategory,build,script,public,dwell,infouuid,mature";
	$itm = "parcelname,dwell,infouuid,mature";
	$query_str = "SELECT ".$itm." FROM ".SEARCH_PARCELS_TBL." WHERE ".
								$category."(parcelname LIKE '%".$DbLink->escape($text)."%'".
								" OR description LIKE '%".$DbLink->escape($text)."%')".$type.
								" ORDER BY $order parcelname LIMIT ".(0+$query_start).",101";

	//error_log("query.php: dir_places_query: ".$query_str);
	$DbLink->query($query_str);

	if ($DbLink->Errno==0)
	{
		$data = array();
		while (($row = $DbLink->next_record()))
		{
			$data[] = array("parcel_id" => $row["infouuid"],
							"name" 		=> $row['parcelname'],
							"for_sale" 	=> "False",
							"auction" 	=> "False",
							"dwell" 	=> $row["dwell"]);
		}

		$response_xml = xmlrpc_encode(array('success'	   => True,
											'errorMessage' => "",
											'data' 		   => $data));
	}
	else
	{				 
		$err_msg = $DbLink->Errno.': '.$DbLink->Error;
		$response_xml = xmlrpc_encode(array('success'	   => False,
											'errorMessage' => $err_msg));
	}

	echo $response_xml;
}



#
# Popular Place Query
#

xmlrpc_server_register_method($xmlrpc_server, "dir_popular_query", "dir_popular_query");


function dir_popular_query($method_name, $params, $app_data)
{
	global $utf8_encoding;
	global $DbLink;

	$req   = $params[0];
	$flags = $req['flags'];
	$terms = array();

	if ($flags&0x1000) $terms[] = "has_picture = 1"; 				// PicturesOnly (1 << 12)
	if ($flags&0x0800 or OPENSIM_PG_ONLY) $terms[] = "mature = 0"; 	// PgSimsOnly (1 << 11)

	$where = "";
	if (count($terms) > 0) $where = " WHERE ".join_terms(" AND ", $terms, False);

	//$itm = "parcelUUID, name, dwell, infoUUID, has_picture, mature ";
	$itm = "name, dwell, infoUUID";
	$query_str = "SELECT ".$itm." FROM ".SEARCH_POPULARPLACES_TBL.$where;

	//error_log("query.php: dir_popular_query: ".$query_str);
	$DbLink->query($query_str);

	if ($DbLink->Errno==0)
	{
		$data = array();
		while (($row = $DbLink->next_record()))
		{
			$name = $row['name'];
			if ($utf8_encoding) $name = base64_encode($name);

			$data[] = array("parcel_id" => $row["infoUUID"],
							"name" 		=> $name,
							"dwell" 	=> $row["dwell"]);
		}

		$response_xml = xmlrpc_encode(array('success'	   => True,
											'errorMessage' => "",
											'data' 		   => $data));
	}
	else
	{				 
		$err_msg = $DbLink->Errno.': '.$DbLink->Error;
		$response_xml = xmlrpc_encode(array('success'	   => False,
											'errorMessage' => $err_msg));
	}

	echo $response_xml;
}



#
# Land Query
#

xmlrpc_server_register_method($xmlrpc_server, "dir_land_query", "dir_land_query");


function dir_land_query($method_name, $params, $app_data)
{
	global $DbLink;

	$req		 = $params[0];
	$flags		 = $req['flags'];
	$type		 = $req['type'];
	$price		 = $req['price'];
	$area		 = $req['area'];
	$query_start = $req['query_start'];

	$terms = array();

	if ($type != 4294967295)	//Include all types of land?
	{
		//Do this check first so we can bail out quickly on Auction search
		if (($type & 26)==2) // Auction (from SearchTypeFlags enum)
		{
			$response_xml = xmlrpc_encode(array('success' 	   => False,
												'errorMessage' => "No auctions listed"));
			echo $response_xml;
			return;
		}

		if (($type & 24) == 8)  $terms[] = "parentestate =1";	//Mainland (24=0x18 [bits 3 & 4])
		if (($type & 24) == 16) $terms[] = "parentestate<>1";	//Estate   (24=0x18 [bits 3 & 4])
	}

	$s = process_region_type_flags($flags);
	if ($s != "") $terms[] = $s;

	if ($flags & 0x100000) $terms[] = "saleprice <= '".$DbLink->escape($price)."'";	//LimitByPrice (1 << 20)
	if ($flags & 0x200000) $terms[] = "area >= '".$DbLink->escape($area)."'"; 		//LimitByArea (1 << 21)

	//The PerMeterSort flag is always passed from a map item query.
	//It doesn't hurt to have this as the default search order.
	$order = "lsq";										//PerMeterSort (1 << 17)
	if ($flags & 0x80000) 	$order  = "parcelname"; 	//NameSort (1 << 19)
	if ($flags & 0x10000) 	$order  = "saleprice"; 		//PriceSort (1 << 16)
	if ($flags & 0x40000) 	$order  = "area"; 			//AreaSort (1 << 18)
	if (!($flags & 0x8000))	$order .= " DESC"; 			//SortAsc (1 << 15)


	$where = "";
	if (count($terms) > 0) $where = " WHERE ".join_terms(" AND ", $terms, False);

	$itm = "regionUUID,parcelname,parcelUUID,area,saleprice,landingpoint,infoUUID,dwell,parentestate,mature";
	$query_str = "SELECT ".$itm.",saleprice/area AS lsq FROM ".SEARCH_PARCELSALES_TBL.$where." ORDER BY ".$order.
										  " LIMIT ".$DbLink->escape($query_start).",101";
	//error_log('query.php: '.$query_str);
	$DbLink->query($query_str);

	if ($DbLink->Errno==0)
	{
		$data = array();
		while (($row = $DbLink->next_record()))
		{
			$data[] = array("parcel_id" 	=> $row["infoUUID"],
							"name" 			=> $row['parcelname'],
							"auction" 		=> "False",
							"for_sale" 		=> "True",
							"sale_price" 	=> $row["saleprice"],
							"landing_point" => $row["landingpoint"],
							"region_UUID" 	=> $row["regionUUID"],
							"area" 			=> $row["area"]);
		}

		$response_xml = xmlrpc_encode(array('success'	   => True,
											'errorMessage' => "",
											'data' 		   => $data));
	}
	else
	{				 
		$err_msg = $DbLink->Errno.': '.$DbLink->Error;
		$response_xml = xmlrpc_encode(array('success'	   => False,
											'errorMessage' => $err_msg));
	}

	echo $response_xml;
}



#
# Events Query
#

xmlrpc_server_register_method($xmlrpc_server, "dir_events_query", "dir_events_query");

function dir_events_query($method_name, $params, $app_data)
{
	global $utf8_encoding;
	global $DbLink;

	$req	  	= $params[0];
	$text	  	= $req['text'];
	$flags	  	= $req['flags'];
	$query_start= $req['query_start'];

	$pieces   	= explode("|", $text);
	$day	  	= $pieces[0];
	$category 	= $pieces[1];
	$text 	  	= $pieces[2];

	//$pieces   	= explode(" ", $text);
	$pieces 	= preg_split("/ /", $text, 0, PREG_SPLIT_NO_EMPTY);
	$text 	  	= join("%", $pieces);

	if ($text==null or $text=="%%%")
	{
		$response_xml = xmlrpc_encode(array('success'	   => False,
											'errorMessage' => "Invalid search terms"));
		echo $response_xml;
		return;
	}


	$terms = array();
	$type  = array();
	$now   = time(); 		//Setting a variable for NOW

	// Terms
	if (isNumeric($category) and $category!=0) $terms[] = "category = ".$category;
	if (isNumeric($day) and $day!=0) $now += $day * 86400;			// (24 * 60 * 60);
	$terms[] = "dateUTC > ".$now;

	// Type
	if (OPENSIM_PG_ONLY) {
		$type[] = "eventflags = 0";
	}
	else {
		if ($flags & 16777216) $type[] = "eventflags = 0";	//IncludePG (1 << 24)
		if ($flags & 33554432) $type[] = "eventflags = 1"; 	//IncludeMature (1 << 25)
		if ($flags & 67108864) $type[] = "eventflags = 2"; 	//IncludeAdult (1 << 26)
	}

	$terms[] = join_terms(" OR ", $type, True);


	$where  = " WHERE ".join_terms(" AND ", $terms, False);
	$where .= " AND (name LIKE '%".$DbLink->escape($text)."%' OR description LIKE '%".$DbLink->escape($text)."%') ";

	$itm = "uid,owneruuid,name,eventid,creatoruuid,category,description,dateUTC,duration,".
		   "covercharge,coveramount,simname,globalPos,eventflags";
	$query_str = "SELECT ".$itm." FROM ".SEARCH_EVENTS_TBL.$where." LIMIT ".$DbLink->escape($query_start).",101";

	//error_log("query.php: ".$query_str);
	$DbLink->query($query_str);


	if ($DbLink->Errno==0)
	{
		$data = array();
		while (($row = $DbLink->next_record()))
		{
			$date = date(DATE_FORMAT, $row["dateUTC"]);

			$name = $row['name'];
			if ($utf8_encoding) {
				$name = base64_encode($name);
			}

			$data[] = array("owner_id" 	  => $row["owneruuid"],
							"creator_id"  => $row["creatoruuid"],
							"name" 		  => $name,
							"event_id" 	  => $row["eventid"],
							"date" 		  => $date,
							"unix_time"	  => $row["dateUTC"],
							"event_flags" => $row["eventflags"]);
		}

		$response_xml = xmlrpc_encode(array('success'	   => True,
											'errorMessage' => "",
											'data' 		   => $data));
	}
	else
	{				 
		$err_msg = $DbLink->Errno.': '.$DbLink->Error;
		$response_xml = xmlrpc_encode(array('success'	   => False,
											'errorMessage' => $err_msg));
	}

	echo $response_xml;
}



#
# Classifieds Query
#

xmlrpc_server_register_method($xmlrpc_server, "dir_classified_query", "dir_classified_query");


function dir_classified_query ($method_name, $params, $app_data)
{
	global $utf8_encoding;
	global $DbLink;

	$req		 = $params[0];
	$text		 = $req['text'];
	$flags		 = $req['flags'];
	$category	 = $req['category'];
	$query_start = $req['query_start'];

	//$pieces = explode(" ", $text);
	$pieces = preg_split("/ /", $text, 0, PREG_SPLIT_NO_EMPTY);
	$text 	= join("%", $pieces);

	if ($text==null or $text=="%%%")
	{
		$response_xml = xmlrpc_encode(array('success'	   => False,
											'errorMessage' => "Invalid search terms"));
		echo $response_xml;
		return;
	}

	$terms = array();
	
	if (OPENSIM_PG_ONLY) {
		$terms[] = "classifiedflags&2 = 0";
	}
	else {
		if ($flags & 6) $terms[] = "classifiedflags&2 = 0"; 	//PG (1 << 2)
		if ($flags & 8) $terms[] = "classifiedflags&2 <> 0"; 	//Mature (1 << 3)
		//There is no bit for Adult in classifiedflags
		//if ($flags & 64)$terms[] = "classifiedflags&? > 0"; 	//Adult (1 << 6)
	}

	$type 	  = "";
	$category = "";
	$where 	  = "";

	if (count($terms)>0) $type = join_terms(" OR ", $terms, True);
	if ($category<>0) 	 $category = "category = ".$category."";

	if ($type != "" || $category != "")
	{
		if ($type == "" || $category == "") $where = " WHERE ".$type.$category;
		else 								$where = " WHERE ".$type." AND " .$category;
	}
	$where .= " AND (name LIKE '%".$DbLink->escape($text)."%' OR description LIKE '%".$DbLink->escape($text)."%') ";

	$itm = "classifieduuid,creatoruuid,creationdate,expirationdate,category,name,description,parceluuid,parentestate,".
		   "snapshotuuid,simname,posglobal,parcelname,classifiedflags,priceforlisting";
	$query_str = "SELECT ".$itm." FROM ".SEARCH_CLASSIFIEDS_TBL.$where." LIMIT ".$DbLink->escape($query_start).",101";

	//error_log("query.php: ".$query_str);
	$DbLink->query($query_str);

	if ($DbLink->Errno==0)
	{
		$data = array();
		while (($row = $DbLink->next_record()))
		{
			$name = $row['name'];
			if ($utf8_encoding) $name = base64_encode($name);

			$data[] = array("classifiedid" 	  => $row["classifieduuid"],
							"name" 			  => $name,
							"classifiedflags" => $row["classifiedflags"],
							"creation_date"   => $row["creationdate"],
							"expiration_date" => $row["expirationdate"],
							"priceforlisting" => $row["priceforlisting"]);
		}

		$response_xml = xmlrpc_encode(array('success'	   => True,
											'errorMessage' => "",
											'data' 		   => $data));
	}
	else
	{				 
		$err_msg = $DbLink->Errno.': '.$DbLink->Error;
		$response_xml = xmlrpc_encode(array('success'	   => False,
											'errorMessage' => $err_msg));
	}

	echo $response_xml;
}



#
# Events Info Query
#

xmlrpc_server_register_method($xmlrpc_server, "event_info_query", "event_info_query");


function event_info_query($method_name, $params, $app_data)
{
	global $utf8_encoding;
	global $DbLink;
	global $Categories;

	$req	 = $params[0];
	$eventID = $req['eventID'];

	$itm = "uid,owneruuid,name,eventid,creatoruuid,category,description,dateUTC,duration,covercharge,".
		   "coveramount,simname,globalPos,eventflags";
	$query_str = "SELECT ".$itm." FROM ".SEARCH_EVENTS_TBL." WHERE eventID = ".$DbLink->escape($eventID); 

	//error_log("query.pgp: ".$query_str);
	$DbLink->query($query_str);

	if ($DbLink->Errno==0)
	{
		$data = array();
		while (($row = $DbLink->next_record()))
		{
			//$date = strftime("%G-%m-%d %H:%M:%S", $row["dateUTC"]);
			$date = date(DATE_FORMAT, $row["dateUTC"]);
			$name = $row['name'];
			$desc = $row['description'];

			$category = $Categories[$row['category']];
			if ($category==null or $category=='') $category = $Categories[0];

			$simname = opensim_get_region_name($row["simname"]);
			if ($simname==null or $simname=='') $simname = 'unknown';

			$cord = explode('/', $row["globalPos"]);
			$pos  = join(',', $cord);
			if ($pos=='' or $pos==',,') $pos = '0,0,0';

			if ($utf8_encoding) {
				$name = base64_encode($name);
				$desc = base64_encode($desc);
				$category = base64_encode($category);
			}

			$data[] = array("event_id"		 => $row["eventid"],
							"owner" 		 => $row["owneruuid"],
							"creator" 		 => $row["creatoruuid"],
							"name" 			 => $name,
							"category" 		 => $category,
							"description"	 => $desc,
							"date"		 	 => $date,
							"dateUTC" 		 => $row["dateUTC"],
							"duration" 		 => $row["duration"],
							"covercharge" 	 => $row["covercharge"],
							"coveramount" 	 => $row["coveramount"],
							"simname" 		 => $simname,
							"globalposition" => $pos,
							"eventflags" 	 => $row["eventflags"]);
		}

		$response_xml = xmlrpc_encode(array('success'	   => True,
											'errorMessage' => "",
											'data' 		   => $data));
	}
	else
	{				 
		$err_msg = $DbLink->Errno.': '.$DbLink->Error;
		$response_xml = xmlrpc_encode(array('success'	   => False,
											'errorMessage' => $err_msg));
	}

	echo $response_xml;
}



#
# Classifieds Info Query
#

xmlrpc_server_register_method($xmlrpc_server, "classifieds_info_query", "classifieds_info_query");


function classifieds_info_query($method_name, $params, $app_data)
{
	global $utf8_encoding;
	global $DbLink;

	$req		  = $params[0];
	$classifiedID = $req['classifiedID'];

	$itm = "classifieduuid,creatoruuid,creationdate,expirationdate,category,name,description,parceluuid,parentestate,".
		   "snapshotuuid,simname,posglobal,parcelname,classifiedflags,priceforlisting";
	$query_str = "SELECT ".$itm." FROM ".SEARCH_CLASSIFIEDS_TBL." WHERE classifieduuid='". $DbLink->escape($classifiedID)."'"; 
	$DbLink->query($query_str);


	if ($DbLink->Errno==0)
	{
		$data = array();
		while (($row = $DbLink->next_record()))
		{
			$name = $row['name'];
			$desc = $row['description'];
			if ($utf8_encoding) {
				$name = base64_encode($name);
				$desc = base64_encode($desc);
			}

			$data[] = array("classifieduuid"  => $row["classifieduuid"],
							"creatoruuid" 	  => $row["creatoruuid"],
							"creationdate" 	  => $row["creationdate"],
							"expirationdate"  => $row["expirationdate"],
							"category" 		  => $row["category"],
							"name" 			  => $name,
							"description" 	  => $desc,
							"parceluuid" 	  => $row["parceluuid"],
							"parentestate" 	  => $row["parentestate"],
							"snapshotuuid" 	  => $row["snapshotuuid"],
							"simname"		  => $row["simname"],
							"posglobal"		  => $row["posglobal"],
							"parcelname"	  => $row['parcelname'],
							"classifiedflags" => $row["classifiedflags"],
							"priceforlisting" => $row["priceforlisting"]);
		}

		$response_xml = xmlrpc_encode(array('success'	   => True,
											'errorMessage' => "",
											'data' 		   => $data));
	}
	else
	{				 
		$err_msg = $DbLink->Errno.': '.$DbLink->Error;
		$response_xml = xmlrpc_encode(array('success'	   => False,
											'errorMessage' => $err_msg));
	}

	echo $response_xml;
}


	
#
# Process the request
#

$request_xml = $HTTP_RAW_POST_DATA;
xmlrpc_server_call_method($xmlrpc_server, $request_xml, '');
xmlrpc_server_destroy($xmlrpc_server);

?>
