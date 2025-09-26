<?php

namespace tmwe_email\controller;

abstract class Abstract_Controller {

    protected static $instances;
    public $data;

    public static function get_instance($class = '') {
        $class = !empty($class) ? $class : get_called_class();
        if (self::$instances == null || !isset(self::$instances[$class])) {
            if (self::$instances == null) {
                self::$instances = [];
            }
            self::$instances[$class] = new $class();
        }
        return self::$instances[$class];
    }
    
    public function get_controller_url($params){
        $url_protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on')?'https':'http';
        $dom_name = $_SERVER['HTTP_HOST'];
        $source_URI = '/pdf.php?action='.$this->response_on();
        
        $temp = '';
        
        if(count($params) != 0){
            $temp = (strpos($source_URI, '?')!==false?'&':'?').http_build_query($params);
        }
        return $url_protocol."://".$dom_name.$source_URI.$temp ;
    }

    
    public function get_url($params, $request_uri = false){
        $url_protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on')?'https':'http';
        $dom_name = $_SERVER['HTTP_HOST'];
        $source_URI = !$request_uri?$_SERVER['REQUEST_URI']:$request_uri;
        
        $temp = '';
        
        if(count($params) != 0){
            $temp = (strpos($source_URI, '?')!==false?'&':'?').http_build_query($params);
        }
        return $url_protocol."://".$dom_name.$source_URI.$temp ;
    }

    /**
     * Change it later to use session;
     * @global type $idanagfrc
     * @return type
     */
    protected function get_current_anagrafica() {
        global $idanagfrc;
        return $idanagfrc;
    }

    protected function get_current_language() {
        if (!isset($_SESSION['erp_lng'])) {
            return 'ITA';
        }

        if ($_SESSION['erp_lng'] != 'ITA' && $_SESSION['erp_lng'] != 'ENG') {
            return 'ENG';
        }

        return 'ITA';
    }
    
    protected function get_current_language_iso2() {
        if (!isset($_SESSION['erp_lng'])) {
            return 'IT';
        }

        if ($_SESSION['erp_lng'] != 'ITA' && $_SESSION['erp_lng'] != 'ENG') {
            return 'EN';
        }

        return 'IT';
    }

    protected function get_current_user_id() {
        return isset($_SESSION['idlog']) ? $_SESSION['idlog'] : false;
    }
    
    protected function get_current_user(){
        return \tmwe_email\service\Security_Service::get_instance()->get_current_user();
    }

    protected function is_post() {
        return $_SERVER['REQUEST_METHOD'] === 'POST';
    }

    protected function get_from_post() {
        return json_decode(file_get_contents('php://input'), true);
    }

    protected function get_parameter($field = false, $default = false, $force_get = false) {
        if ($force_get) {
            return isset($_GET[$field]) ? $_GET[$field] : $default;
        }
        if ($this->data === null) { // && !isset($this->data[$field])) { 
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                $this->data = $this->get_from_post();
                if (!$field) {
                    return $this->data;
                }
                return isset($this->data[$field]) ? $this->data[$field] : $default;
            } else {
                $this->data = $_GET;
            }
        }
        if (!$field) {
            return $this->data;
        }
        return isset($this->data[$field]) ? $this->data[$field] : $default;
    }

    protected function is_secured() {
        return true;
    }

    protected function set_header() {
        
    }

    public function handle_not_found() {
        http_response_code(404);
        return false;
    }

    public function handle_not_authorized() {
        http_response_code(401);
        return false;
    }
    
    public function handle_error() {
        http_response_code(502);
        die;
    }

    protected function is_authorized() {
        return \tmwe_email\service\Security_Service::get_instance()->check_permission();
    }

    public function handle_request($param = []) {
        $this->set_header();
        if (!$this->is_secured() || ($this->is_authorized())) {
            return $this->do();
        } else {
            return $this->handle_not_authorized();
        }
    }

    protected function get_request() {
        return $this->get_parameter();
    }

    /**
     * @return [] array for json
     */
    protected abstract function do($param = []);

    /**
     * @return string the route to be handled by the concrete controller
     */
    public function response_on(){
        $class_name = array_reverse(explode("\\",get_class($this)))[0];
        $resp_on =  str_replace('_controller', '', strtolower($class_name)) ;
        return $resp_on;
    }

    public function __toString(): string {
        return $this->handle_request();
    }

    public function stop_query_execution($error_message) {
        http_response_code(400);
        die;
    }

    protected function store_in_session($index, $val) {
        $_SESSION[$index] = $val;
        return $_SESSION;
    }

    protected function get_from_session($index, $default = null) {
        return isset($_SESSION[$index]) ? $_SESSION[$index] : $default;
    }
}
