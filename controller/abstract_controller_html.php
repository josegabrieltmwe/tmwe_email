<?php

namespace tmwe_email\controller;

/**
 * Description of abstract_controller_html
 *
 * @author pepe
 */
abstract class Abstract_Controller_Html extends Abstract_Controller {
    
    public function render(\tmwe_email\template\Abstract_Template $template, $params = []) {
        $params['crsf_token'] = $this->get_crsf_token();
        $template->set_params($params);
        echo $template;
    }
    
    public function handle_not_authorized() {
        header('Location: '."https://".$_SERVER['HTTP_HOST'].'/erp/login_dev.php');
    }
    
    public function get_translation($groups){
        return \tmwe_email\model\Testi_Erp::get_instance()->get_erp_labels_in($this->get_current_language(), $groups);
    }
    
    protected function validate_crsf_token(){
        return $this->get_crsf_token() === $this->get_parameter('crsf_token');
    }
    
    protected function get_crsf_token(){
        $crsf_token = $this->get_from_session('crsf_token', false);
        if($crsf_token == false){
            $crsf_token = uniqid('', true);
            $this->store_in_session('crsf_token', $crsf_token);
        }
        
        return $crsf_token;
    }
    
    protected function get_from_post(){
        return $_POST;
    }
}
