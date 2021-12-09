<?php
//
// Group DB configration by Fumi.Iseki
//

require_once('../include/env_interface.php');



if (defined('CMS_DB_HOST'))
{
	$dbPort 		= 3306;
	$dbHost 		= CMS_DB_HOST;
	$dbName 		= CMS_DB_NAME;
	$dbUser 		= CMS_DB_USER;
	$dbPassword		= CMS_DB_PASS;
	$useMySQLi		= CMS_DB_MYSQLI;
}
else
{
	$dbPort 		= 3306;
	$dbHost 		= OPENSIM_DB_HOST;
	$dbName 		= OPENSIM_DB_NAME;
	$dbUser 		= OPENSIM_DB_USER;
	$dbPassword		= OPENSIM_DB_PASS;
	$useMySQLi		= OPENSIM_DB_MYSQLI;
}


// Access Key
if (defined('XMLGROUP_RKEY'))
{
	$groupReadKey  	= XMLGROUP_RKEY;
	$groupWriteKey 	= XMLGROUP_WKEY;
}
else
{
	$groupReadKey  	= "1234";
	$groupWriteKey 	= "1234";
}


// DB Table Name
if (defined('XMLGROUP_ACTIVE_TBL'))
{
	$osagent 				= XMLGROUP_ACTIVE_TBL;
	$osgroup 				= XMLGROUP_LIST_TBL;
	$osgroupinvite 			= XMLGROUP_INVITE_TBL;
	$osgroupmembership 		= XMLGROUP_MEMBERSHIP_TBL;
	$osgroupnotice 			= XMLGROUP_NOTICE_TBL;
	$osgrouprolemembership	= XMLGROUP_ROLE_MEMBER_TBL;
	$osrole 				= XMLGROUP_ROLE_TBL;
}
else
{
	$osagent 				= 'osagent';
	$osgroup 				= 'osgroup';
	$osgroupinvite 			= 'osgroupinvite';
	$osgroupmembership 		= 'osgroupmembership';
	$osgroupnotice 			= 'osgroupnotice';
	$osgrouprolemembership	= 'osgrouprolemembership';
	$osrole 				= 'osrole';
}


$groupRequireAgentAuthForWrite = FALSE;
$groupEnforceGroupPerms = FALSE;

?>
