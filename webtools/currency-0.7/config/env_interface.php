<?php
//
// Environment Interface for non Web Interface
//												by Fumi.Iseki
//
//

require_once(realpath(dirname(__FILE__).'/config.php'));
$helperinclude=dirname(ENV_HELPER_PATH) . '/include';
require_once($helperinclude . '/tools.func.php');
require_once($helperinclude . '/mysql.func.php');
require_once($helperinclude . '/env.mysql.php');
require_once($helperinclude . '/opensim.mysql.php');



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
