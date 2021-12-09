<?php

//
// Modified by Fumi.Iseki for XoopenSim/Modlos
//

require_once('./profile_config.php');


// for Debug
$request_xml = $HTTP_RAW_POST_DATA;
error_log('profile.php: '.$request_xml);


//
if (!opensim_is_access_from_region_server()) {
	$remote_addr = $_SERVER["REMOTE_ADDR"];
	error_log("profile.php: Illegal access from ".$remote_addr);
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
#			Modified and Fixed Bugs by Fumi.Iseki for Xoops Cube and Moodle '10 4/16
#

###################### No user serviceable parts below #####################

#
# The XMLRPC server object
#

$xmlrpc_server = xmlrpc_server_create();



#
# Classifieds
#

# Request Classifieds Name

xmlrpc_server_register_method($xmlrpc_server, 'avatarclassifiedsrequest', 'avatarclassifiedsrequest');

function avatarclassifiedsrequest($method_name, $params, $app_data)
{
	global $utf8_encoding;
	global $DbLink;

	$req 	= $params[0];
	$uuid 	= $req['uuid'];

    //$items = "classifieduuid,creatoruuid,creationdate,expirationdate,category,name,description,parceluuid,parentestate,".
    //    	 "snapshotuuid,simname,posglobal,parcelname,classifiedflags,priceforlisting";
    $items = "classifieduuid,creatoruuid,name";
	$query_str = 'SELECT '.$items.' FROM '.PROFILE_CLASSIFIEDS_TBL." WHERE creatoruuid='".$DbLink->escape($uuid)."'";

	//error_log('profile.php: '.$query_str);
	$DbLink->query($query_str);

	if ($DbLink->Errno==0) 
	{
		$data = array();
		while ($row = $DbLink->next_record())
		{
			$name = $row['name'];
			if ($utf8_encoding) $name = base64_encode($name);

			$data[] = array('classifiedid' => $row['classifieduuid'],
							'creatoruuid'  => $row['creatoruuid'],
							'name'		   => $name );
		}

		$response_xml = xmlrpc_encode(array('success'	   => True,
											'errorMessage' => '',
											'data' 		   => $data ));
	}
	else 
	{
		$err_msg = $DbLink->Errno.': '.$DbLink->Error;
		$response_xml = xmlrpc_encode(array('success'	   => False,
											'errorMessage' => $err_msg));
	}

	echo $response_xml;
}



# Request Calssifieds

xmlrpc_server_register_method($xmlrpc_server, 'classifiedinforequest', 'classifiedinforequest');

function classifiedinforequest($method_name, $params, $app_data)
{
	global $utf8_encoding;
	global $DbLink;

	$req  = $params[0];
	$pick = $req['classified_id'];
	//$uuid = $req['avatar_id'];

    $items = "classifieduuid,creatoruuid,creationdate,expirationdate,category,name,description,parceluuid,parentestate,".
        	 "snapshotuuid,simname,posglobal,parcelname,classifiedflags,priceforlisting";
	$query_str = 'SELECT '.$items.' FROM '.PROFILE_CLASSIFIEDS_TBL." WHERE classifieduuid='".$DbLink->escape($pick)."'";

	//error_log("profile.php: ".$query_str);
	$DbLink->query($query_str);

	if ($DbLink->Errno==0) 
	{
		$data = array();
		while ($row = $DbLink->next_record())
		{
			if ($row['description']=='') $row['description'] = 'No description given';
		
			$name = $row['name'];
			$desc = $row['description'];
			if ($utf8_encoding) {
				$name = base64_encode($name);
				$desc = base64_encode($desc);
			}

			$data[] = array('classifieduuid' 	=> $row['classifieduuid'],
							'creatoruuid' 		=> $row['creatoruuid'],
							'creationdate'		=> $row['creationdate'],
							'expirationdate' 	=> $row['expirationdate'],
							'category' 			=> $row['category'],
							'name' 				=> $name,
							'description' 		=> $desc,
							'parceluuid' 		=> $row['parceluuid'],
							'parentestate' 		=> $row['parentestate'],
							'snapshotuuid' 		=> $row['snapshotuuid'],
							'simname' 			=> $row['simname'],
							'posglobal' 		=> $row['posglobal'],
							'parcelname' 		=> $row['parcelname'],
							'classifiedflags' 	=> $row['classifiedflags'],
							'priceforlisting' 	=> $row['priceforlisting']);
		}

		$response_xml = xmlrpc_encode(array('success'	   => True,
											'errorMessage' => '',
											'data' 		   => $data ));
	}
	else
	{
		$err_msg = $DbLink->Errno.': '.$DbLink->Error;
		$response_xml = xmlrpc_encode(array('success'	   => False,
											'errorMessage' => $err_msg));
	}

	echo $response_xml;
}



# Update Classifieds 

xmlrpc_server_register_method($xmlrpc_server, 'classified_update', 'classified_update');

function classified_update($method_name, $params, $app_data)
{
	global $DbLink;

	$req 			= $params[0];
	$classifieduuid = $req['classifiedUUID'];
	$creator		= $req['creatorUUID'];
	$category		= $req['category'];
	$name			= $req['name'];
	$description	= $req['description'];
	$parceluuid		= $req['parcel_uuid'];
	$parentestate	= $req['parentestate'];
	$snapshotuuid	= $req['snapshotUUID'];
	$simname		= $req['sim_name'];
	$globalpos		= $req['pos_global'];
	$classifiedflag = $req['classifiedFlags'];
	$priceforlist	= $req['classifiedPrice'];
	//$parcelname		= $req['parcelname'];
	
	$parcelname = '';
	if ($parceluuid!='') {
		$pclname = opensim_get_parcel_name($parceluuid);
		if ($name!=null) $parcelname = $pclname;
	}
	else {
		$parceluuid = '00000000-0000-0000-0000-0000000000000';
	}

	if ($parcelname=='')  $parcelname  = 'No Name';
	if ($description=='') $description = 'No Description';

	if ($classifiedflag==2)
	{
		$creationdate   = time();
		$expirationdate = time() + 604800;		// (7 * 24 * 60 * 60);
	}
	else
	{
		$creationdate   = time();
		$expirationdate = time() + 31536000;	//(365 * 24 * 60 * 60);
	}
	
    $items = "classifieduuid,creatoruuid,creationdate,expirationdate,category,name,description,parceluuid,parentestate,".
        	 "snapshotuuid,simname,posglobal,parcelname,classifiedflags,priceforlisting";
	$query_str = 'REPLACE INTO '.PROFILE_CLASSIFIEDS_TBL.' ('.$items.') VALUES ('.
					"'". $DbLink->escape($classifieduuid)."',".
					"'". $DbLink->escape($creator) 		 ."',".
						 $DbLink->escape($creationdate)  .','.
						 $DbLink->escape($expirationdate).','.
					"'". $DbLink->escape($category) 	 ."',".
					"'". $DbLink->escape($name) 		 ."',".
					"'". $DbLink->escape($description) 	 ."',".
					"'". $DbLink->escape($parceluuid) 	 ."',".
						 $DbLink->escape($parentestate)  .','.
					"'". $DbLink->escape($snapshotuuid)  ."',".
					"'". $DbLink->escape($simname) 		 ."',".
					"'". $DbLink->escape($globalpos) 	 ."',".
					"'". $DbLink->escape($parcelname) 	 ."',".
						 $DbLink->escape($classifiedflag).','.
						 $DbLink->escape($priceforlist)  .')';

	$DbLink->query($query_str);

	if ($DbLink->Errno==0)
	{
		$response_xml = xmlrpc_encode(array('success'	  	=> True,
											'errorMessage' 	=> '' ));
	}
	else
	{
		$err_msg = $DbLink->Errno.': '.$DbLink->Error;
		$response_xml = xmlrpc_encode(array('success'	   => False,
											'errorMessage' => $err_msg));
	}

	echo $response_xml;
}



# Delete Classifieds

xmlrpc_server_register_method($xmlrpc_server, 'classified_delete', 'classified_delete');

function classified_delete($method_name, $params, $app_data)
{
	global $DbLink;

	$req 			= $params[0];
	$classifieduuid	= $req['classifiedID'];

	$query_str = 'DELETE FROM '.PROFILE_CLASSIFIEDS_TBL." WHERE classifieduuid='".$DbLink->escape($classifieduuid)."'";
	$DbLink->query($query_str);

	if ($DbLink->Errno==0)
	{
		$response_xml = xmlrpc_encode(array('success'	   => True,
											'errorMessage' => '' ));
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
# Picks
#

# Request Picks Name

xmlrpc_server_register_method($xmlrpc_server, 'avatarpicksrequest', 'avatarpicksrequest');

function avatarpicksrequest($method_name, $params, $app_data)
{
	global $utf8_encoding;
	global $DbLink;

	$req 	= $params[0];
	$uuid 	= $req['uuid'];

	//$items = "pickuuid,creatoruuid,toppick,parceluuid,name,description,snapshotuuid,user,originalname,simname,posglobal,sortorder,enabled";
	$items = "pickuuid,creatoruuid,name";
	$query_str = 'SELECT '.$items.' FROM '.PROFILE_USERPICKS_TBL." WHERE creatoruuid='".$DbLink->escape($uuid)."'";
	$DbLink->query($query_str);

	if ($DbLink->Errno==0)
	{
		$data = array();
		while ($row = $DbLink->next_record())
		{
			$name = $row['name'];
			if ($utf8_encoding) $name = base64_encode($name);

			$data[] = array('pickid' 	  => $row['pickuuid'],
							'creatoruuid' => $row['creatoruuid'],
							'name' 	 	  => $name );
		}

		$response_xml = xmlrpc_encode(array('success'	   => True,
											'errorMessage' => '',
											'data' 		   => $data ));
	}
	else
	{
		$err_msg = $DbLink->Errno.': '.$DbLink->Error;
		$response_xml = xmlrpc_encode(array('success'	   => False,
											'errorMessage' => $err_msg));
	}

	echo $response_xml;
}



# Request Picks

xmlrpc_server_register_method($xmlrpc_server, 'pickinforequest', 'pickinforequest');

function pickinforequest($method_name, $params, $app_data)
{
	global $utf8_encoding;
	global $DbLink;

	$req  = $params[0];
	$pick = $req['pick_id'];
	//$uuid = $req['avatar_id'];

	$items = "pickuuid,creatoruuid,toppick,parceluuid,name,description,snapshotuuid,user,originalname,simname,posglobal,sortorder,enabled";
	$query_str = 'SELECT '.$items.' FROM '.PROFILE_USERPICKS_TBL." WHERE pickuuid='". $DbLink->escape($pick)."'";
	$DbLink->query($query_str);

	if ($DbLink->Errno==0)
	{
		$data = array();
		while ($row = $DbLink->next_record())
		{
			if ($row['description'] == '') $row['description'] = 'No description given';
		
			$desc = $row['description'];
			$name = $row['name'];
			if ($utf8_encoding) {
				$desc = base64_encode($desc);
				$name = base64_encode($name);
			}

			$data[] = array(
					'pickuuid' 		=> $row['pickuuid'],
					'creatoruuid' 	=> $row['creatoruuid'],
					'toppick' 		=> $row['toppick'],
					'parceluuid' 	=> $row['parceluuid'],
					'name' 			=> $name,
					'description' 	=> $desc,
					'snapshotuuid' 	=> $row['snapshotuuid'],
					'user' 			=> $row['user'],
					'originalname' 	=> $row['originalname'],
					'simname' 		=> $row['simname'],
					'posglobal' 	=> $row['posglobal'],
					'sortorder'		=> $row['sortorder'],
					'enabled' 		=> $row['enabled']);
		}

		$response_xml = xmlrpc_encode(array('success'	   => True,
											'errorMessage' => '',
											'data' 		   => $data ));
	}
	else
	{
		$err_msg = $DbLink->Errno.': '.$DbLink->Error;
		$response_xml = xmlrpc_encode(array('success'	   => False,
											'errorMessage' => $err_msg));
	}

	echo $response_xml;
}



# Update Picks

xmlrpc_server_register_method($xmlrpc_server, 'picks_update', 'picks_update');

function picks_update($method_name, $params, $app_data)
{ 
	global $DbLink;

	$req 			= $params[0];
	$creator		= $req['creator_id'];
	$pickuuid		= $req['pick_id'];
	$name			= $req['name'];
	$simname		= $req['sim_name'];
	$parceluuid		= $req['parcel_uuid'];
	$description	= $req['desc'];
	$posglobal		= $req['pos_global'];
	$sortorder		= $req['sort_order'];
	$snapshotuuid	= $req['snapshot_id']; 
	$toppick		= $req['top_pick'];
	$enabled		= $req['enabled'];
	//$user_uuid	= $req['agent_id'];	// not use
	//$user			= $req['user'];
	//$original		= $req['original'];


	$user = '';
	$original = '';

	if ($parceluuid!='') {
		$pclinfo = opensim_get_parcel_info($parceluuid);
		if ($pclinfo!=null) {
			$avname  = opensim_get_avatar_name($pclinfo['OwnerUUID']);
			if ($avname!=null) $user = $avname['fullname'];
			$pclname = $pclinfo['Name'];
			if ($pclname!=null) $original = $pclname;
		}
	}
	else {
		$parceluuid = '00000000-0000-0000-0000-0000000000000';
	}

	if ($user=='') 		  $user 	   = 'No User';
	if ($original=='') 	  $original    = 'No Name';
	if ($description=='') $description = 'No Description';


	$items = "pickuuid,creatoruuid,toppick,parceluuid,name,description,snapshotuuid,user,originalname,simname,posglobal,sortorder,enabled";
	$query_str = 'REPLACE INTO '.PROFILE_USERPICKS_TBL.' ('.$items.') VALUES ('.
					"'".$DbLink->escape($pickuuid)	  ."',".
					"'".$DbLink->escape($creator)	  ."',".
					"'".$DbLink->escape($toppick)	  ."',".
					"'".$DbLink->escape($parceluuid)  ."',".
					"'".$DbLink->escape($name)		  ."',".
					"'".$DbLink->escape($description) ."',".
					"'".$DbLink->escape($snapshotuuid)."',".
					"'".$DbLink->escape($user)		  ."',".
					"'".$DbLink->escape($original)	  ."',".
					"'".$DbLink->escape($simname)	  ."',".
					"'".$DbLink->escape($posglobal)	  ."',".
					"'".$DbLink->escape($sortorder)	  ."',".
					"'".$DbLink->escape($enabled)	  ."')";
		
	$DbLink->query($query_str);

	if ($DbLink->Errno==0)
	{
		$response_xml = xmlrpc_encode(array('success'	   => True,
											'errorMessage' => '' ));
	}
	else
	{
		$err_msg = $DbLink->Errno.': '.$DbLink->Error;
		$response_xml = xmlrpc_encode(array('success'	   => False,
											'errorMessage' => $err_msg));
	}

	echo $response_xml;
}



# Delete Picks

xmlrpc_server_register_method($xmlrpc_server, 'picks_delete', 'picks_delete');

function picks_delete($method_name, $params, $app_data)
{
	global $DbLink;

	$req 		= $params[0];
	$pickuuid	= $req['pick_id'];

	$query_str = 'DELETE FROM '.PROFILE_USERPICKS_TBL." WHERE pickuuid='".$DbLink->escape($pickuuid)."'";
	$DbLink->query($query_str);

	if ($DbLink->Errno==0)
	{
		$response_xml = xmlrpc_encode(array('success'	   => True,
											'errorMessage' => '' ));
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
# Notes
#

# Request Notes

xmlrpc_server_register_method($xmlrpc_server, 'avatarnotesrequest', 'avatarnotesrequest');

function avatarnotesrequest($method_name, $params, $app_data)
{
	global $utf8_encoding;
	global $DbLink;

	$req 		= $params[0];
	$uuid 		= $req['avatar_id'];
	$targetuuid	= $req['target_id'];


	$items = "useruuid, targetuuid, notes";
	$query_str = 'SELECT '.$items.' FROM '.PROFILE_USERNOTES_TBL." WHERE useruuid='". $DbLink->escape($uuid)."' AND ".
																		"targetuuid='".$DbLink->escape($targetuuid)."'";
	$DbLink->query($query_str);

	if ($DbLink->Errno==0)
	{
		$data = array();
		while ($row = $DbLink->next_record())
		{
			$notes = $row['notes'];
			if ($utf8_encoding) $notes = base64_encode($notes);

			$data[] = array('target_id' => $row['targetuuid'],
							'notes'     => $notes);
		}

		$response_xml = xmlrpc_encode(array('success'	   => True,
											'errorMessage' => '',
											'data' 		   => $data ));
	}
	else
	{
		$err_msg = $DbLink->Errno.': '.$DbLink->Error;
		$response_xml = xmlrpc_encode(array('success'	   => False,
											'errorMessage' => $err_msg));
	}

	echo $response_xml;
}



# Update Notes

xmlrpc_server_register_method($xmlrpc_server, 'avatar_notes_update', 'avatar_notes_update');

function avatar_notes_update($method_name, $params, $app_data)
{
	global $DbLink;

	$req 		= $params[0];
	$uuid 		= $req['avatar_id'];
	$targetuuid	= $req['target_id'];
	$notes		= $req['notes'];
	$ready		= 0;

	$query_str = 'SELECT COUNT(*) FROM '.PROFILE_USERNOTES_TBL." WHERE useruuid='".$DbLink->escape($uuid)."' AND ".
																	"  targetuuid='".$DbLink->escape($targetuuid)."'";
	$DbLink->query($query_str);
	list($ready) = $DbLink->next_record();

	if ($ready!=0)
	{
		if ($notes=='')
		{
			$query_str = 'DELETE FROM '.PROFILE_USERNOTES_TBL." WHERE useruuid='".$DbLink->escape($uuid)."' AND ".
																	" targetuuid='".$DbLink->escape($targetuuid)."'";
		}
		else
		{
			$query_str = 'UPDATE '.PROFILE_USERNOTES_TBL." SET notes='".$DbLink->escape($notes)."'".
						" WHERE useruuid='".$DbLink->escape($uuid)."' AND targetuuid='".$DbLink->escape($targetuuid)."'";
		}
	}
	else
	{
		$items = "useruuid, targetuuid, notes";
		$query_str = 'INSERT INTO '.PROFILE_USERNOTES_TBL."(".$items.") VALUES ('".$DbLink->escape($uuid)."',".
																			 "  '".$DbLink->escape($targetuuid)."',".
																			 "  '".$DbLink->escape($notes)."')";
	}
	
	//error_log("profile.php: ".$query_str);
	$DbLink->query($query_str);

	if ($DbLink->Errno==0)
	{
		$response_xml = xmlrpc_encode(array('success'	   => True,
											'errorMessage' => '' ));
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
# Profile
#

# Request Profiles and Interests

xmlrpc_server_register_method($xmlrpc_server, 'avatar_properties_request', 'avatar_properties_request');

function avatar_properties_request($method_name, $params, $app_data)
{
	global $utf8_encoding;
	global $DbLink;

	$req 	= $params[0];
	$uuid 	= $req['avatar_id'];

	$items = "useruuid,profilePartner,profileImage,profileAboutText,profileAllowPublish,profileMaturePublish,".
			 "profileURL,profileWantToMask,profileWantToText,profileSkillsMask,profileSkillsText,".
			 "profileLanguagesText,profileFirstImage,profileFirstText";
	$query_str = "SELECT ".$items." FROM ".PROFILE_USERPROFILE_TBL." WHERE useruuid='".$DbLink->escape($uuid)."'";
																//	" AND profileAllowPublish='1'";
	//error_log("profile.php: ".$query_str);
	$DbLink->query($query_str);

	if ($DbLink->Errno==0)
	{
		$data = array();
		while ($row = $DbLink->next_record()) 
		{
			$webURL	   = $row['profileURL'];
			$aboutText = $row['profileAboutText'];
			$firstText = $row['profileFirstText'];
			$wantoText = $row['profileWantToText'];
			$skillText = $row['profileSkillsText'];
			$langText  = $row['profileLanguagesText'];

			$userFlags = 0;
			if ($row['profileAllowPublish']==0x01)  $userFlags |= 0x01;
			if ($row['profileMaturePublish']==0x01 and !OPENSIM_PG_ONLY) $userFlags |= 0x02;

			if ($utf8_encoding) {
				$aboutText = base64_encode($aboutText);
				$firstText = base64_encode($firstText);
				$wantoText = base64_encode($wantoText);
				$skillText = base64_encode($skillText);
				$langText  = base64_encode($langText);
			}

			$data[] = array('Partner' 		 => $row['profilePartner'],
							'ProfileUrl' 	 => $webURL,
							'WantToMask' 	 => $row['profileWantToMask'],
							'WantToText' 	 => $wantoText,
							'SkillsMask' 	 => $row['profileSkillsMask'],
							'SkillsText' 	 => $skillText,
							'LanguagesText'	 => $langText,
							'FirstLifeImage' => $row['profileFirstImage'],
							'FirstLifeText'	 => $firstText,
							'Image' 		 => $row['profileImage'],
							'AboutText'		 => $aboutText,
				  			'UserFlags' 	 => $userFlags);
		}

		$response_xml = xmlrpc_encode(array('data' => $data));
	}
	else
	{
		$err_msg = $DbLink->Errno.': '.$DbLink->Error;
		$response_xml = xmlrpc_encode(array('success'	   => False,
											'errorMessage' => $err_msg));
	}

	echo $response_xml;
}



# Update Profile

xmlrpc_server_register_method($xmlrpc_server, 'avatar_properties_update', 'avatar_properties_update');

function avatar_properties_update($method_name, $params, $app_data)
{
	global $DbLink;

	$req 		= $params[0];
	$uuid 		= $req['avatar_id'];
	$url		= $req['ProfileUrl'];
	$firstImage	= $req['FirstiLifeImage'];
	$firstText	= $req['FirstLifeText'];
	$image		= $req['Image'];
	$aboutText	= $req['AboutText'];
	$partner	= $req['Partner'];
	$userFlags	= $req['UserFlags'];
	$publish	= 0;
	$mature		= 0;
	$ready 		= 0;

	if ($userFlags&0x01) $publish = 0x01;
	if ($userFlags&0x02 and !OPENSIM_PG_ONLY) $mature = 0x01;


	// for OpenSim DB
	$flags = (opensim_get_avatar_flags($uuid)&~0x03) | ($userFlags&0x03);
	opensim_set_avatar_flags($uuid, $flags);

	$query_str = 'SELECT COUNT(*) FROM '.PROFILE_USERPROFILE_TBL." WHERE useruuid='".$DbLink->escape($uuid)."'";
	$DbLink->query($query_str);
	list($ready) = $DbLink->next_record();

	if ($ready!=0)
	{
		$query_str = 'UPDATE '.PROFILE_USERPROFILE_TBL.' SET '.
							"profileURL='".$DbLink->escape($url)."',".
							"profileFirstImage='".$DbLink->escape($firstImage)."',".
  							"profileFirstText='".$DbLink->escape($firstText)."',".
							"profileImage='".$DbLink->escape($image)."',".
							"profileAboutText='".$DbLink->escape($aboutText)."',".
							"profilePartner='".$DbLink->escape($partner)."',".
							"profileAllowPublish='".$DbLink->escape($publish)."',".
							"profileMaturePublish='".$DbLink->escape($mature)."'".
						" WHERE useruuid='".$DbLink->escape($uuid)."'";
	}
	else
	{
		$items = "useruuid, profileURL, profileFirstImage, profileFirstText, profileImage, profileAboutText, ".
				 "profilePartner, profileAllowPublish, profileMaturePublish";
		$query_str = 'INSERT INTO '.PROFILE_USERPROFILE_TBL." (".$items.") ".
						" VALUES ('".$DbLink->escape($uuid)."',".
								 "'".$DbLink->escape($url)."',".
								 "'".$DbLink->escape($firstImage)."',".
							 	 "'".$DbLink->escape($firstText)."',".
							 	 "'".$DbLink->escape($image)."',".
							 	 "'".$DbLink->escape($aboutText)."',".
							 	 "'".$DbLink->escape($partner)."',".
							 	 "'".$DbLink->escape($publish)."',".
 								 "'".$DbLink->escape($mature)."')";
	}

	//error_log("profile: avatar_properties_update: ".$query_str);
	$DbLink->query($query_str);

	if ($DbLink->Errno==0)
	{
		$response_xml = xmlrpc_encode(array('success'	   => True,
											'errorMessage' => ''));
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
# Interests
#

# Profile Interests Update

xmlrpc_server_register_method($xmlrpc_server, 'avatar_interests_update', 'avatar_interests_update');

function avatar_interests_update($method_name, $params, $app_data)
{
	global $DbLink;

	$req 		= $params[0];
	$uuid 		= $req['avatar_id'];
	$wantmask	= $req['WantToMask'];
	$wanttext	= $req['WantToText'];
	$skillsmask	= $req['SkillsMask'];
	$skillstext	= $req['SkillsText'];
	$langtext	= $req['LanguagesText'];

	//$items = "profileWantToMask,profileWantToText,profileSkillsMask,profileSkillsText,profileLanguagesText";
	$query_str = 'UPDATE '.PROFILE_USERPROFILE_TBL.' SET '.
							"profileWantToMask='".$DbLink->escape($wantmask)."',".
							"profileWantToText='".$DbLink->escape($wanttext)."',".
							"profileSkillsMask='".$DbLink->escape($skillsmask)."',".
							"profileSkillsText='".$DbLink->escape($skillstext)."',".
							"profileLanguagesText='".$DbLink->escape($langtext)."'".
					" WHERE useruuid='".$DbLink->escape($uuid)."'";

	//error_log("profile: ".$query_str);
	$DbLink->query($query_str);

	if ($DbLink->Errno==0)
	{
		$response_xml = xmlrpc_encode(array('success'	   => True,
											'errorMessage' => ''));
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
# User Info (Preferences)
#

xmlrpc_server_register_method($xmlrpc_server, 'user_preferences_request', 'user_preferences_request');

function user_preferences_request($method_name, $params, $app_data)
{
	global $DbLink;

	$req 	= $params[0];
	$uuid 	= $req['avatar_id'];

	$items = "imviaemail,visible,email";
	$query_str = 'SELECT '.$items.' FROM '.PROFILE_USERSETTINGS_TBL." WHERE useruuid='".$DbLink->escape($uuid)."'";
	
	//error_log("profile: ".$query_str);
	$DbLink->query($query_str);

	if ($DbLink->Errno==0)
	{
		$data = array();

		if ($DbLink->num_rows()!=0)
		{
			while ($row = $DbLink->next_record())
			{
				$imviaemail = $row['imviaemail'];
				if ($imviaemail=="") $imviaemail = "false";
				$visible = $row['visible'];
				if ($visible=="") $visible = "true";
				$email = $row['email'];
				if ($email=="") $email = "unknown";
	
				$data[] = array('imviaemail' => $imviaemail,
								'visible' 	 => $row['visible'],
								'email' 	 => $email);
			}
		}

		else 
		{
 			$email	 = env_get_user_email($uuid);
			if ($email=="") $email = 'unknown';

			$query_str = 'INSERT '.PROFILE_USERSETTINGS_TBL.' (useruuid,imviaemail,visible,email) '.
							" VALUES('".$uuid."','False','True','".$DbLink->escape($email)."')";
			$DbLink->query($query_str);

			$data[] = array('imviaemail' => 'False',
							'visible' 	 => 'True',
							'email' 	 => $email);
		}

		$response_xml = xmlrpc_encode(array('success'	   => True,
											'errorMessage' => '',
											'data' 		   => $data ));
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

xmlrpc_server_register_method($xmlrpc_server, 'user_preferences_update', 'user_preferences_update');

function user_preferences_update($method_name, $params, $app_data)
{
	global $DbLink;

	$req 	 = $params[0];
	$uuid 	 = $req['avatar_id'];
	$wantim	 = $req['imViaEmail'];
	$visible = $req['visible'];
 	$email 	 = env_get_user_email($uuid);
	if ($email=="") $email = 'unknown';

	$query_str = 'UPDATE '.PROFILE_USERSETTINGS_TBL.' SET '.
							"imviaemail='".$DbLink->escape($wantim)."',".
							"visible='".$DbLink->escape($visible)."',".
							"email='".$DbLink->escape($email)."'".
					" WHERE useruuid='".$DbLink->escape($uuid)."'";

	//error_log("porfile: ".$query_str);	
	$DbLink->query($query_str);

	if ($DbLink->Errno==0)
	{
		$response_xml = xmlrpc_encode(array('success'	   => True,
											'errorMessage' => ''));
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
