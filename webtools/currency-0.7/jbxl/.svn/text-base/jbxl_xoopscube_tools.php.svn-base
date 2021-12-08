<?php
/****************************************************************
	jbxl_xoopscube_func.php  by Fumi.Iseki


 function  jbxl_get_userid_by_name($username)
 function  jbxl_get_username_by_id($uid)
 function  jbxl_get_userinfo_by_id($uid, $item='uname')
 function  jbxl_get_xoops_config($cname, $module="Legacy System")

****************************************************************/


if (!defined('XOOPS_ROOT_PATH')) exit();

$jbxl_xoopscube_tools_ver = 2014071300;


//
if (!defined('JBXL_XOOPSCUBE_TOOLS_VER')) {

define('JBXL_XOOPSCUBE_TOOLS_VER', $jbxl_xoopscube_tools_ver);



function  jbxl_get_userid_by_name($username)
{
	$uid = 0;
	if ($username==null or $username=='') return $uid;

	$userHandler =& xoops_getmodulehandler('users', 'user');
	$criteria =& new CriteriaCompo();
	$criteria->add(new Criteria('uname', $username));
	$userArr =& $userHandler->getObjects($criteria);
	if (count($userArr)!=0) $uid = $userArr[0]->get('uid');

	return $uid;
}



function  jbxl_get_username_by_id($uid)
{
	$uname = '';
	if ($uid==null or $uid=='' or $uid=='0') return $uname;

	$userHandler =& xoops_getmodulehandler('users', 'user');
	$criteria =& new CriteriaCompo();
	$criteria->add(new Criteria('uid', $uid));
	$userArr =& $userHandler->getObjects($criteria);
	if (count($userArr)!=0) $uname = $userArr[0]->get('uname');

	return $uname;
}



function  jbxl_get_userinfo_by_id($uid, $item='uname')
{
	$info = '';
	if ($uid==null or $uid=='' or $uid=='0') return $info;

	$userHandler =& xoops_getmodulehandler('users', 'user');
	$criteria =& new CriteriaCompo();
	$criteria->add(new Criteria('uid', $uid));
	$userArr =& $userHandler->getObjects($criteria);
	if (count($userArr)!=0) $info = $userArr[0]->get($item);

	return $info;
}



function  jbxl_get_xoops_config($cname, $module="Legacy System")
{
	global $xoopsDB;

	$sql = "SELECT conf_value FROM ".$xoopsDB->prefix('modules').",".$xoopsDB->prefix('config').
							" WHERE name='".$module."' AND mid=conf_modid and conf_name='".$cname."'";
	$rslt = $xoopsDB->query($sql);
	list($val) = $xoopsDB->fetchRow($rslt) ;

	return $val;
}


}       // !defined('JBXL_XOOPSCUBE_TOOLS_VER')
