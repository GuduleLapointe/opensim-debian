<?php
//
// Configration file for non Web Interface
//
//
//

// Please set this hepler script URL and directory
if (!defined('ENV_HELPER_URL'))  define('ENV_HELPER_URL',  'http://www.nsl.tuis.ac.jp/currency/helper/');
if (!defined('ENV_HELPER_PATH')) define('ENV_HELPER_PATH', '/home/apache/htdocs/currency/helper/');

//////////////////////////////////////////////////////////////////////////////////i
// Valiables for OpenSim

// Please set MySQL DB access information
define('OPENSIM_DB_HOST', 'mysql.nsl.tuis.ac.jp');
define('OPENSIM_DB_NAME', 'opensim_db');
define('OPENSIM_DB_USER', 'opensim_user');
define('OPENSIM_DB_PASS', 'opensim_pass');


// Money Server Access Key
// Please set same key with MoneyScriptAccessKey in MoneyServer.ini
define('CURRENCY_SCRIPT_KEY', '123456789');

// Group Module Access Keys
// Please set same keys with at [Groups] section in OpenSim.ini (case of Aurora-Sim, it is Groups.ini)
define('XMLGROUP_RKEY', '1234');	// Read Key
define('XMLGROUP_WKEY', '1234');	// Write key

// Please set user(robust) server's URL
define('USER_SERVER_URI', 'http://opensim.nsl.tuis.ac.jp:8002/'); 	// not use localhost or 127.0.0.1




//////////////////////////////////////////////////////////////////////////////////
// You need not change the below usually. 

define('USE_CURRENCY_SERVER', 1);
define('USE_UTC_TIME',		  1);

define('SYSURL', ENV_HELPER_URL);
$GLOBALS['xmlrpc_internalencoding'] = 'UTF-8';

if (USE_UTC_TIME) date_default_timezone_set('UTC');


// Currency DB
define('CURRENCY_MONEY_TBL',		'balances');
define('CURRENCY_TRANSACTION_TBL',	'transactions');


// XML Group.  see also xmlgroups_config.php 
define('XMLGROUP_ACTIVE_TBL',		'osagent');
define('XMLGROUP_LIST_TBL',			'osgroup');
define('XMLGROUP_INVITE_TBL',		'osgroupinvite');
define('XMLGROUP_MEMBERSHIP_TBL',	'osgroupmembership');
define('XMLGROUP_NOTICE_TBL',		'osgroupnotice');
define('XMLGROUP_ROLE_MEMBER_TBL',	'osgrouprolemembership');
define('XMLGROUP_ROLE_TBL',			'osrole');


// Avatar Profile. see also profile_config.php
define('PROFILE_CLASSIFIEDS_TBL',	'classifieds');
define('PROFILE_USERNOTES_TBL',		'usernotes');
define('PROFILE_USERPICKS_TBL',		'userpicks');
define('PROFILE_USERPROFILE_TBL',	'userprofile');
define('PROFILE_USERSETTINGS_TBL',	'usersettings');


// Search the In World. see also search_config.php 
define('SEARCH_ALLPARCELS_TBL',		'allparcels');
define('SEARCH_EVENTS_TBL',			'events');
define('SEARCH_HOSTSREGISTER_TBL',	'hostsregister');
define('SEARCH_OBJECTS_TBL',		'objects');
define('SEARCH_PARCELS_TBL',		'parcels');
define('SEARCH_PARCELSALES_TBL',	'parcelsales');
define('SEARCH_POPULARPLACES_TBL',	'popularplaces');
define('SEARCH_REGIONS_TBL',		'regions');
define('SEARCH_CLASSIFIEDS_TBL',	PROFILE_CLASSIFIEDS_TBL);


//
if (!defined('ENV_READED_CONFIG')) define('ENV_READED_CONFIG', 'YES');

?>
