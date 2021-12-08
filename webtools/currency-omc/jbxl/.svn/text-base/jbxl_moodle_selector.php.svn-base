<?php
//////////////////////////////////////////////////////////////////////////////////////////////
// jbxl_moodle_selector.php for Moodle
//
//										by Fumi.Iseki
//

defined('MOODLE_INTERNAL') || die();

$jbxl_moodle_selector_ver = 2013092100;


//
if (defined('JBXL_MOODLE_SELECTOR_VER')) {
    if (JBXL_MOODLE_SELECTOR_VER < $jbxl_moodle_selector_ver) {
		debugging('JBXL_MOODLE_SELECTOR: old version is used. '.JBXL_MOODLE_SELECTOR_VER.' < '.$jbxl_moodle_selector_ver, DEBUG_DEVELOPER);
    }
}
else {

define('JBXL_MOODLE_SELECTOR_VER', $jbxl_moodle_selector_ver);




abstract class  jbxl_id_selector_base
{
	var $include_html = 'jbxl_moodle_selector.html';
	var $action_url   = '';
	var $title_top	  = '';
	var $title_left	  = 'Left';
	var $title_right  = 'Right';
	var $course_id;

	var $ids		  = array();
	var $ids_left 	  = array();
	var $ids_right 	  = array();

	var $select_left  = array();		// move to left
	var $select_right = array();		// move to right

	var $hasError = false;
	var $errorMsg = array();


	abstract protected function get_all_ids();				// id=>1(right)/0(left) の配列を返す
	abstract protected function get_record($id);
	abstract protected function set_record($id, $rec);
	abstract protected function set_item_left($rec);
	abstract protected function set_item_right($rec);
	//
	abstract public    function get_name($id);
	abstract public    function sorting(array $ids);


	//
	public function __construct($html, $url, $ltitle, $rtitle) 
	{
		if (!empty($html))   $this->include_html = $html;
		if (!empty($url))	 $this->action_url	 = $url;
		if (!empty($ltitle)) $this->title_left	 = $ltitle;
		if (!empty($rtitle)) $this->title_right	 = $rtitle;
	}


	public function  execute()
	{
		$this->ids = $this->get_all_ids();
		$course_id = optional_param('courseid', '0', PARAM_INT);

		// Form	
		if ($form_data=data_submitted()) {
			if (!confirm_sesskey()) {
				$this->hasError = true;
				$this->errorMsg[] = 'jbxl_id_selector_base: sesskey error';
				return false;
			}

			$lsubmit = optional_param('submit_left',  '', PARAM_TEXT);
			$rsubmit = optional_param('submit_right', '', PARAM_TEXT);

			if ($lsubmit!='') {
				$this->select_right = $form_data->select_right;
				$this->action_moveto_left();
			}
			elseif ($rsubmit!='') {
				$this->select_left = $form_data->select_left;
				$this->action_moveto_right();
			}
		}
	}



	public function  print_page() 
	{
		$this->ids = $this->sorting($this->ids);

		foreach ($this->ids as $id=>$right) {
			if ($id>0) {
				if ($right) $this->ids_right[] = $id;
				else	 	$this->ids_left[]  = $id;
			}
		}

		$items_left  = $this->ids_left;
		$items_right = $this->ids_right;
		include($this->include_html);
	}



	protected function  action_moveto_right()
	{
		foreach($this->select_left as $id) {
			$rec = $this->get_record($id);
			$rec = $this->set_item_right($rec);
			$this->set_record($id, $rec);
			$this->ids[$id] = true;
		}
	}



	protected function  action_moveto_left()
	{
		foreach($this->select_right as $id) {
			$rec = $this->get_record($id);
			$rec = $this->set_item_left($rec);
			$this->set_record($id, $rec);
			$this->ids[$id] = false;
		}
	}



	public function  set_title($title)
	{
		$this->title_top = $title;
	}
}




}	   // !defined('JBXL_MOODLE_SELECTOR_VER')
