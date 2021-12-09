<?php
//
// Environment Interface for non Web Interface
//												by Fumi.Iseki
//
//

require_once(realpath(dirname(__FILE__).'/config.php'));

require_once(ENV_HELPER_PATH.'/../include/tools.func.php');
require_once(ENV_HELPER_PATH.'/../include/mysql.func.php');
require_once(ENV_HELPER_PATH.'/../include/env.mysql.php');
require_once(ENV_HELPER_PATH.'/../include/opensim.mysql.php');



//
//
//
function  env_get_user_email($uid)
{
	return "";
}



//
// Config Value
//

$env_config["currency_script_key"] = CURRENCY_SCRIPT_KEY;




function  env_get_config($name)
{
	global $env_config;

	return $env_config[$name];
}



//
if (!defined('ENV_READED_INTERFACE')) define('ENV_READED_INTERFACE', 'YES');

?>
