<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');


// include config file
include(PATH_THIRD.'eventbrite_ee/config.php');


class Eventbrite_ee {
	
										
	function __construct()
	{
        // Make a local reference to the ExpressionEngine super object
        $this->EE =& get_instance();

        //Get settings from db
        $this->authentication_tokens = $this->_get_settings();
        
		$this->EE->load->library('Eventbrite', $this->authentication_tokens);
		//$this->authentication_tokens
    }

    function events()
    {
    	$variables = array();
    	$events = $this->EE->eventbrite->user_list_events();
    	if(isset($events['error'])){
			return $events['error']['error_message'];
		}
		$c = 0;
		foreach($events as $row){
			foreach($row as $event){
				$data = $this->_prep_vars($event['event']);
				$variables[] = $data[0];
			}
		}
		
		$output = $this->EE->TMPL->parse_variables($this->EE->TMPL->tagdata, $variables);
		return $output;
    }
	
	function event()
	{
		if(!$id = $this->EE->TMPL->fetch_param('id')){
			return;
		}
		
		$event = $this->EE->eventbrite->event_get(array('id' => $id));
		if(isset($event['error'])){
			return $event['error']['error_message'];
		}
		$variables = $this->_prep_vars($event['event']);
		//echo '<pre>';
		//print_r($variables);
		//echo '</pre>';
		$output = $this->EE->TMPL->parse_variables($this->EE->TMPL->tagdata, $variables);
		return $output;		
	}

	function _get_settings(){
		$result = ee()->db->get('eventbrite_settings');
		if($result->num_rows() == 0){
			return;
		}
		else{
			return $result->row_array();
		}
	}

	function _prep_vars($array){
		foreach($array as $key => $val){
			if(is_array($array[$key])){
				if(isset($array[$key][0])){
					foreach($array[$key] as $k => $v){
						foreach($array[$key][$k] as $row){
							$prepped[] = $row;
						}
						
					}
					$array[$key] = $prepped;
				}
				else{
					$array[$key] = array($val);
				}
			}
		}
		return array($array);
	}
	
}
// END CLASS

/* End of file mod.eventbrite.php */
/* Location: ./system/expressionengine/third_party/eventbrite/mod.eventbrite.php */
?>