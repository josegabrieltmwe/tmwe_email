<?php

namespace tmwe_email\controller;

/**
 * Description of stored_filter
 *
 * @author pepe
 */
abstract class Abstract_Crud_Controller extends Abstract_Json_Controller {

    protected function is_new_item_request($item) {
        return !isset($item['id']) || ( $item['id'] === '' || $item['id'] == null );
    }

    protected function create_item($item) {
        $temp = $this->get_model()->insert($item);
        return ['id' => $temp];
    }

    protected function is_delete_request($request) {
        $request = $request ? $request : $this->get_request();
        return isset($request['action']) && $request['action'] == 'delete';
    }

    protected function delete_item($item) {
        return ['result' => $this->get_model()->remove($item), 'id' => $item['id']];
    }

    protected function update_item($item) {
        return $this->get_model()->update($item);
    }

    protected function select_item($params = []) {
        return $this->get_model()->select($params);
    }

    protected function get_item_from_request($request = false) {
        $request = $request ? $request : $this->get_request();
        return isset($request['item']) ? $request['item'] : (isset($request['id']) ? ['id' => $request['id']] : []);
    }

    protected function get_user() {
        return [
            'id' => $this->get_from_session('idlog'),
            'username' => $this->get_from_session('idlog_nome'),
        ];
    }

    /**
     * @return \tmwe_email\model\Abstract_Model Description
     */
    protected abstract function get_model($params = []);

    protected function get_array_result($param) {
        $resp = [];
        $request = $this->get_request();
        if ($this->is_post()) {
            $item = $this->get_item_from_request($request);
            if ($this->is_new_item_request($item)) {
                $temp = $this->create_item($item);
                if ($temp) {
                    $resp = $temp;
                }
            } else {
                if ($this->is_delete_request($request)) {
                    $resp = $this->delete_item($item);
                } else {
                    $resp = $this->update_item($item);
                }
            }
        } else {
            $resp = $this->select_item($request);
        }

        return $resp;
    }
}
