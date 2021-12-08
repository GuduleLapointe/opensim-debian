<?php
//
// by Fumi.Iseki 2012/04/12
//               2014/05/14
//               2014/06/09
//               2014/11/28
//               2014/12/04
//               2014/12/26
//

//
// About Capabilities
//    please see http://docs.moodle.org/dev/Roles#Capability-locality_changes_in_v1.9
//

defined('MOODLE_INTERNAL') || die();

$jbxl_moodle_tools_ver = 2014122600;


//
if (defined('JBXL_MOODLE_TOOLS_VER') or defined('_JBXL_MOODLE_TOOLS')) {
	if (defined('JBXL_MOODLE_TOOLS_VER')) {
		if (JBXL_MOODLE_TOOLS_VER < $jbxl_moodle_tools_ver) {
			debugging('JBXL_MOODLE_TOOLS: old version is used. '.JBXL_MOODLE_TOOLS_VER.' < '.$jbxl_moodle_tools_ver, DEBUG_DEVELOPER);
		}
	}
}
else {

define('JBXL_MOODLE_TOOLS_VER', $jbxl_moodle_tools_ver);



/*******************************************************************************
//
// cntxt: id or context of course
//

// function  jbxl_is_admin($uid)
// function  jbxl_is_teacher($uid, $cntxt)
// function  jbxl_is_assistant($uid, $cntxt)
// function  jbxl_is_student($uid, $cntxt)
// function  jbxl_has_role($uid, $cntxt, $rolename)
//
// function  jbxl_get_course_users($cntxt, $sort='')
// function  jbxl_get_course_students($cntxt, $sort='')
// function  jbxl_get_course_tachers($cntxt, $sort='')
// function  jbxl_get_course_assistants($cntxt, $sort='')
//
// function  jbxl_get_user_first_grouping($courseid, $userid)
//
// function  jbxl_db_exist_table($table, $lower_case=true)
//
// function  jbxl_download_data($format, $datas, $filename='')
// function  jbxl_save_csv_file($datas, $filename, $tocode='sjis-win')
//
// function  jbxl_get_user_link($user, $pattern='fullname')
// function  jbxl_get_user_name($user, $pattern='fullname')
// function  jbxl_get_fullnamehead($name_pattern, $firstname, $lastname, $deli='')
//
// for deprecated functions
// function  jbxl_get_moodle_version()
// function  jbxl_get_course_context($courseid)
// function  jbxl_add_to_log($event)
// function  jbxl_can_use_html_editor()
//

*******************************************************************************/



function  jbxl_is_admin($uid)
{
	$admins = get_admins();
	foreach ($admins as $admin) {
		if ($uid==$admin->id) return true;
	}
	return false;
}



function  jbxl_is_teacher($uid, $cntxt, $inc_admin=true)
{
	global $DB;

	if (!$cntxt) return false;
	if (!is_object($cntxt)) $cntxt = jbxl_get_course_context($cntxt);

	$ret = false;
	$roles = $DB->get_records('role', array('archetype'=>'editingteacher'), 'id', 'id'); 
	foreach($roles as $role) {
		$ret = user_has_role_assignment($uid, $role->id, $cntxt->id);
		if ($ret) return $ret;
	}

	if ($inc_admin) {
		$ret = jbxl_is_admin($uid); 
		if (!$ret) $ret = jbxl_has_role($uid, $cntxt, 'manager');
		if (!$ret) $ret = jbxl_has_role($uid, $cntxt, 'coursecreator');
	}
	return $ret;
}



function  jbxl_is_assistant($uid, $cntxt)
{
	global $DB;

	if (!$cntxt) return false;
	if (!is_object($cntxt)) $cntxt = jbxl_get_course_context($cntxt);

	$roles = $DB->get_records('role', array('archetype'=>'teacher'), 'id', 'id'); 
	foreach($roles as $role) {
		$ret = user_has_role_assignment($uid, $role->id, $cntxt->id);
		if ($ret) return $ret;
	}
	return false;
}



function  jbxl_is_student($uid, $cntxt)
{
	global $DB;

	if (!$cntxt) return false;
	if (!is_object($cntxt)) $cntxt = jbxl_get_course_context($cntxt);

	$roles = $DB->get_records('role', array('archetype'=>'student'), 'id', 'id'); 
	foreach($roles as $role) {
		$ret = user_has_role_assignment($uid, $role->id, $cntxt->id);	// slow?
		if ($ret) return $ret;
	}
	return false;
}



function  jbxl_has_role($uid, $cntxt, $rolename)
{
	global $DB;

	if (!$cntxt) return false;
	if (!is_object($cntxt)) $cntxt = jbxl_get_course_context($cntxt);

	$roles = $DB->get_records('role', array('archetype'=>$rolename), 'id', 'id'); 
	foreach($roles as $role) {
		$ret = user_has_role_assignment($uid, $role->id, $cntxt->id);
		if ($ret) return $ret;
	}
	return false;
}



function jbxl_get_course_users($cntxt, $sort='')
{
	global $DB;

	if (!$cntxt) return '';

	if ($sort) $sort = ' ORDER BY u.'.$sort;
	$sql = 'SELECT u.* FROM {role_assignments} r, {user} u WHERE r.contextid = ? AND r.userid = u.id '.$sort;
	//
	if (!is_object($cntxt)) $cntxt = jbxl_get_course_context($cntxt);
	$users = $DB->get_records_sql($sql, array($cntxt->id));

	return $users;
}



function jbxl_get_course_students($cntxt, $sort='')
{
	global $DB;

	if (!$cntxt) return '';

	$roles = $DB->get_records('role', array('archetype'=>'student'), 'id', 'id'); 
	if (empty($roles)) return '';

	$roleid = '';
	foreach($roles as $role) {
		if (!empty($roleid)) $roleid.= ' OR ';
		$roleid.= 'r.roleid = '.$role->id;
	}
	if ($sort) $sort = ' ORDER BY u.'.$sort;

	$sql = 'SELECT u.* FROM {role_assignments} r, {user} u '.
					 ' WHERE r.contextid = ? AND ('.$roleid.') AND r.userid = u.id '.$sort;
	//
	if (!is_object($cntxt)) $cntxt = jbxl_get_course_context($cntxt);
	$users = $DB->get_records_sql($sql, array($cntxt->id));

	return $users;
}



function jbxl_get_course_tachers($cntxt, $sort='')
{
	global $DB;

	if (!$cntxt) return '';

	$roles = $DB->get_records('role', array('archetype'=>'editingteacher'), 'id', 'id'); 
	if (empty($roles)) return '';

	$roleid = '';
	foreach($roles as $role) {
		if (!empty($roleid)) $roleid.= ' OR ';
		$roleid.= 'r.roleid = '.$role->id;
	}
	if ($sort) $sort = ' ORDER BY u.'.$sort;

	$sql = 'SELECT u.* FROM {role_assignments} r, {user} u '. 
					 ' WHERE r.contextid = ? AND ('.$roleid.') AND r.userid = u.id '.$sort;
	//
	if (!is_object($cntxt)) $cntxt = jbxl_get_course_context($cntxt);
	$users = $DB->get_records_sql($sql, array($cntxt->id));

	return $users;
}



function jbxl_get_course_assistants($cntxt, $sort='')
{
	global $DB;

	if (!$cntxt) return '';

	$roles = $DB->get_records('role', array('archetype'=>'teacher'), 'id', 'id'); 
	if (empty($roles)) return '';

	$roleid = '';
	foreach($roles as $role) {
		if (!empty($roleid)) $roleid.= ' OR ';
		$roleid.= 'r.roleid = '.$role->id;
	}
	if ($sort) $sort = ' ORDER BY u.'.$sort;

	$sql = 'SELECT u.* FROM {role_assignments} r, {user} u '.
					 ' WHERE r.contextid = ? AND ('.$roleid.') AND r.userid = u.id '.$sort;
	//
	if (!is_object($cntxt)) $cntxt = jbxl_get_course_context($cntxt);
	$users = $DB->get_records_sql($sql, array($cntxt->id));

	return $users;
}



/*
function jbxl_get_user_first_grouping($courseid, $userid)
{
	/////////////////////////////////
	return 0;	// for DEBUG
	/////////////////////////////////


	if (!$courseid or !$userid) return 0;

	$groupings = groups_get_user_groups($courseid, $userid);
	if (!is_array($groupings)) return 0;

	$keys = array_keys($groupings);
	if (count($keys)>1 && $keys[0]==0) return $keys[1];
	else return $keys[0];
}
*/




//
// Moodle DB
//

function jbxl_db_exist_table($table, $lower_case=false)
{
	global $DB;

	$ret = false;

/*
	// MySQL
	$results = $DB->get_records_sql('SHOW TABLES');
	if (is_array($results)) {
		$db_tbls = array_keys($results);
		foreach($db_tbls as $db_tbl) {
			if ($lower_case) $db_tbl = strtolower($db_tbl);
			if ($db_tbl==$table) {
				$ret = true;
				break;
			}
		}
	}
*/

	if ($lower_case) $table = strtolower($table);
	$results = $DB->get_records_sql('SELECT * FROM '.$table);

	if (is_array($results)) return true;
	return false;
}			




///////////////////////////////////////////////////////////////////////////////////////////////////////////
// 
// download
//
// $datas: 2次元のデータ配列
//
function  jbxl_download_data($format, $datas, $filename='')
{
	global $CFG;

	if (empty($datas->data)) return;
	if (empty($filename)) {
		$filename = 'jbxl_download_'.date('YmdHis');
	}

	//
	if ($format==='xls') {
		$excellib_version = 0;
		if (file_exists ($CFG->dirroot.'/lib/excellib.class.php')) {
			$excellib_version = 2;
			$tocode = 'UTF-8';
			require_once($CFG->dirroot.'/lib/excellib.class.php');
		}
		else {
			$excellib_version = 1;
			$tocode = 'sjis-win';
			require_once($CFG->dirroot.'/lib/excel/Worksheet.php');
			require_once($CFG->dirroot.'/lib/excel/Workbook.php');
		}

		//
		header("Content-type: application/vnd.ms-excel");
		header("Content-Disposition: attachment; filename=\"$filename.xls\"");
	
		/// Creating a workbook
		if ($excellib_version==2) {
			$workbook = new MoodleExcelWorkbook('-', 'Excel5');
			$workbook->send($filename);
		}	
		else {
			$workbook = new Workbook('-');
		}
		$myxls = $workbook->add_worksheet('data');
		
		//
		$i = 0;
		foreach ($datas->data as $line=>$data) {
			$j = 0;
			foreach ($data as $colm=>$val) {
				if ($datas->attr[$line][$colm]==='number') {
					$myxls->write_number($i, $j++, $val);
				}
				else {
					$myxls->write_string($i, $j++, mb_convert_encoding($val,  $tocode, 'auto'));
				}
			}
			$i++;
		}
		$workbook->close();	
	}

	//
	else if ($format==='txt') {
		$tocode = 'UTF-8';
		//
		header("Content-Type: application/download\n"); 
		header("Content-Disposition: attachment; filename=\"$filename.txt\"");

		foreach ($datas->data as $data) {
			foreach ($data as $val) {
				echo mb_convert_encoding($val, $tocode, 'auto')."\t";
			}
			echo "\r\n";
		}
	}	
		
	return;
}	



///////////////////////////////////////////////////////////////////////////////////////////////////////////
// 
// save CSV file
//
// $datas: 2次元のデータ配列
//
function  jbxl_save_csv_file($datas, $filename, $tocode='UTF-8')
{
	if (empty($datas->data)) return;
	if (empty($filename)) return;
	//$tocode = 'sjis-win';

	$filedata = '';
	if ($tocode=='UTF-8') {
		$filedata = pack('ccc', 0xEF, 0xBB, 0xBF);	// BOM
	}
	//
	foreach ($datas->data as $data) {
		$i = 0;
		foreach ($data as $val) {
			if ($i!=0) $filedata .= ', ';
			$filedata .= mb_convert_encoding($val, $tocode, 'auto');
			$i++;
		}
		$filedata .= "\r\n";
	}

	file_put_contents($filename, $filedata);
		
	return;
}	




///////////////////////////////////////////////////////////////////////////////////////////////////////////
// 
// Name
//

function  jbxl_get_user_link($user, $pattern='fullname', $target='')
{
	global $DB, $CFG;
	if (!is_object($user)) $user = $DB->get_record('user', array('id'=>$user));
	if (!$user) return '';

	if (!empty($target)) $target = 'target="'.$target.'"';
	$user_name = jbxl_get_user_name($user, $pattern);
	$link = '<a href='.$CFG->wwwroot.'/user/view.php?id='.$user->id.' '.$target.' >'.$user_name.'</a>';

	return $link;
}




function  jbxl_get_user_name($user, $pattern='fullname')
{
	global $DB;

	$user_name = '';
	
	if (!is_object($user)) $user = $DB->get_record('user', array('id'=>$user));
	if ($user) {
		if		($pattern=='firstname') $user_name = $user->firstname;
		else if ($pattern=='lastname')  $user_name = $user->lastname;
		else							$user_name = fullname($user);
	}

	return $user_name;
}



function  jbxl_get_fullnamehead($name_pattern, $firstname, $lastname, $deli='')
{
	global $CFG;

	if ($name_pattern=='fullname') {
		if ($CFG->fullnamedisplay=='lastname firstname') { // for better view (dlnsk)
			if ($deli=='') $fullnamehead = "$lastname $firstname";
			else		   $fullnamehead = "$lastname ".$deli." $firstname";
		}
		else {
			if ($deli=='') $fullnamehead = "$firstname $lastname";
			else		   $fullnamehead = "$firstname ".$deli." $lastname";
		}
	}
	else if ($name_pattern=='lastname') {
		$fullnamehead = "$lastname";
	}
	else {
		$fullnamehead = "$firstname";
	}

	return $fullnamehead;
}





//////////////////////////////////////////////////////////////////////////////////
//
// for deprecated functions
//

// see http://docs.moodle.org/dev/Releases
function  jbxl_get_moodle_version()
{
	global $CFG;

	if 		($CFG->version>=2014111000) return 2.8;
	else if ($CFG->version>=2014051200) return 2.7;
	else if ($CFG->version>=2013111800) return 2.6;
	else if ($CFG->version>=2013051400) return 2.5;
	else if ($CFG->version>=2012120300) return 2.4;
	else if ($CFG->version>=2012062500) return 2.3;
	else if ($CFG->version>=2011120500) return 2.2;
	else if ($CFG->version>=2011070100) return 2.1;
	else if ($CFG->version>=2010112400) return 2.0;
	else if ($CFG->version>=2007101509) return 1.9;

	return 1.8;
}


function  jbxl_get_course_context($courseid)
{
	$context = null;

	$ver = jbxl_get_moodle_version();
	if (floatval($ver)>=2.5) {
		$context = context_course::instance($courseid, IGNORE_MISSING);
	}
	else {
		$context = get_context_instance(CONTEXT_COURSE, $courseid);
	}

	return $context;
}


function  jbxl_add_to_log($event)
{
	if ($event==null) return;

	$ver = jbxl_get_moodle_version();
	if (floatval($ver)>=2.7) {
		$event->trigger();
	}
	else {
		if ($event->url==null)   $event->url  = '/';
		if ($event->info==null)  $event->info = ' ';
		if(!empty($event->url) && strlen($event->url)>100) $event->url = substr($event->url, 0, 97).'...';
		add_to_log($event->courseid, $event->name, $event->action, $event->url, $event->info);
	}
}


function  jbxl_can_use_html_editor()
{
	$ver = jbxl_get_moodle_version();
	if (floatval($ver)>=2.6) {
		return true;
	}
	return can_use_html_editor();
}


function  jbxl_get_system_context()
{
	$cntxt = null;
	$ver = jbxl_get_moodle_version();

	if (floatval($ver)>=2.6) {
		$cntxt = context_system::instance();
	}
	else {
		$cntxt = get_system_context();
	}
	return $cntxt;
}


}		// !defined('JBXL_MOODLE_TOOLS_VER')
