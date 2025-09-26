<?php

namespace tmwe_email\controller;

/**
 * Description of stored_filter
 *
 * @author pepe
 */
abstract class Abstract_Json_Controller extends Abstract_Controller {
    protected $is_test = false;

    protected abstract function get_array_result($param);

    protected function set_header() {
        header('content-type: application/json');
    }
    
    protected function validate_request($param = []){
        return true;
    }
    
    protected function handle_not_valid_request($param = []){
        http_response_code(400);
        die;
    }

    /**
     * 
     * @param type $param
     * @return void
     */
    protected function do($param = []) {
       if(!$this->validate_request($param)){
           return $this->handle_not_valid_request($param);
       }
       echo $this->encode_array_result($this->get_array_result($param));
    }
    
    protected function encode_array_result($param){
        return json_encode($param);
    }


    public function get_array_result_test($params = []){
        $this->is_test = true;
        return $this->get_array_result($params);
    }
}
