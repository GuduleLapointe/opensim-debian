<?php
//
// by Fumi.Iseki '09 5/31
//

require_once('./xmlgroups_config.php');


//$debugXMLRPC = 1;
//$debugXMLRPCFile = 'xmlgroup.log';

//$request_xml = $HTTP_RAW_POST_DATA;
//error_log('xmlgroups.php: '.$request_xml);	// see /var/log/php/... or /var/log/httpd/error_log


if ($useMySQLi) {
	include(ENV_HELPER_PATH.'/xmlrpci.php');
}
else {
	include(ENV_HELPER_PATH.'/xmlrpc.php');
}

