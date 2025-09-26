<?php

namespace tmwe_email\service\http_base\api;

abstract class Abstract_Client {

    protected static $instance;
    protected $requested_parameters;
    protected $requested_endpoint;
    protected $use_testing;

    /**
     * 
     * @param string $class
     * @return Abstract_Client
     */
    public static function get_instance() {
        $class = get_called_class();
        if (self::$instance == null || !isset(self::$instance[$class])) {
            if (self::$instance == null) {
                self::$instance = [];
            }
            self::$instance[$class] = new $class();
        }
        return self::$instance[$class];
    }

    protected function run_request($endpoint, $headers, $params = false, $method = false) {
        $client = new \GuzzleHttp\Client();
        $params = html_entity_decode($params, ENT_COMPAT | ENT_HTML5, 'UTF-8');
        $method = !$method ? $this->get_method() : $method;
        $request = new \GuzzleHttp\Psr7\Request($method, $endpoint, $headers, ($params && isset($params)) ? $params : null);
        $respsonse = $client->send($request);

        return $respsonse;
    }

    protected function prepare_log_message($message) {
        return get_called_class() . '|' . is_array($message) ? json_encode($message) : $message;
    }

    protected function log_error($message) {
        \tmwe_email\log\Logger::get_instance()->error($this->prepare_log_message($message));
    }

    protected function log($message) {
        \tmwe_email\log\Logger::get_instance()->log($this->prepare_log_message($message));
    }

    protected function html_to_file($html, $file_name) {
        $file_address = $this->get_complete_label_filename($file_name);
        $temp = (file_put_contents($file_address, $html) !== false) ? ['success' => true] : ['success' => false];
        return $file_address;
    }

    protected function base64_to_file($base_64, $file_name) {
        $file_address = $this->get_complete_label_filename($file_name);
        $temp = (file_put_contents($file_address, base64_decode($base_64)) !== false) ? ['success' => true] : ['success' => false];
        return $file_address;
    }

    protected function handle_error_exception($ex) {
        return ['success' => false, 'errors' => [['message' => $ex->getMessage(), 'code' => 500]]];
    }

    protected function handle_error_response($ex, $error_content) {
        $error_array = json_decode($error_content, true);
        return [
            'success' => false,
            'errors' => isset($error_array['errors']) ?
            $error_array['errors'] :
            [
        ['message' => $error_content,
            'code' => $ex->getResponse()->getStatusCode()
        ]
            ]
        ];
    }

    protected function execute($headers, $params, $use_testing) {
        if (isset($params['shipment_id'])) {
            unset($params['shipment_id']);
        }

        $endpoint = $this->get_endpoint($use_testing);
        try {
            $respsonse = $this->run_request($endpoint, $headers, $params);
            $resp = $respsonse->getBody()->getContents();
            $result = $this->handle_request_response($resp, $use_testing, $endpoint, $headers, $params);
        } catch (\GuzzleHttp\Exception\BadResponseException $ex) {
            $error = $ex->getResponse()->getBody()->getContents();

            $this->error_log_guzzle($ex, $error, $params);

            return $this->handle_error_response($ex, $error);
        } catch (\Exception $ex) {
            $this->log_error_exception($ex, $params);
            $resp_exception = $this->handle_error_exception($ex);
            return $resp_exception;
        }


        $result['success'] = !isset($result['success']) ? true : $result['success'];

        if ($result['success']) {
            $this->log_ok($resp, $params);
        } else {
            $this->log_ko($resp, $params);
        }

        return $result;
    }

    protected function handle_request_response($resp, $use_testing = false, $endpoint = false, $headers = false, $params = false) {
        return json_decode($resp, true);
    }

    protected function log_error_exception($ex, $params) {
        $message = 'Error ' . $ex->getMessage() . ' | ' . (is_array($params) ? json_encode($params) : $params);
        $this->log_error($message);
    }

    protected function error_log_guzzle($ex, $error, $params) {
        return $this->log_error("Error " . '[' . $ex->getResponse()->getStatusCode() . ']' . $ex->getMessage() . '| ' . ($error) . '| ' . (is_array($params) ? json_encode($params) : $params));
    }

    protected function log_ok($resp, $params) {
        return $this->log('Response ' . $resp . '| Request .' . (is_array($params) ? json_encode($params) : $params));
    }

    protected function log_ko($resp, $params) {
        return $this->log_error('Response ' . $resp . '| Request .' . (is_array($params) ? json_encode($params) : $params));
    }

    protected function get_method() {
        return 'POST';
    }

    protected function handle_response($params) {
        if (!is_array($params)) {
            $params = json_decode($params, true);
        }

        if (!$params) {
            $params = [];
        }

        if (!isset($params['success'])) {
            $params['success'] = false;
        }
        return $params;
    }

    public abstract function get_prod_endpoint();

    public abstract function get_dev_endpoint();

    public function get_endpoint($use_testing) {
        if ($use_testing) {
            return $this->get_dev_endpoint();
        }
        return $this->get_prod_endpoint();
    }

    protected function get_content_type() {
        return 'application/json';
    }

    protected function get_headers($params) {
        return [
            'Accept' => 'application/json',
            'Cache-Control' => 'no-cache',
            'Content-Type' => $this->get_content_type(),
        ];
    }

    public function request($params = [], $use_testing = false) {
        $headers = $this->get_headers($params);
        $response = $this->execute($headers, $params, $use_testing);
        $result = $this->handle_response($response);

        return $result;
    }

    protected function get_complete_label_filename($file_name) {
        $file_dir = $this->get_base_dir();
        $file_address = $file_dir . $file_name;
        return $file_address;
    }

    protected function from_xml_to_array($xml_string) {
        $xml_object = simplexml_load_string($xml_string);
        $json = json_encode($xml_object);
        $temp = json_decode($json, true);
        return $temp;
    }

    ///home/www/tmwe.it/www/erp/erplogs/";
    //$file_dir = __DIR__ . '/../../../files/';
    //$file_dir = __DIR__ . '/../../../../erplogs/';

    protected function get_base_dir() {
        //return $file_dir = __DIR__ . '/../../../files/';
        return $file_dir = __DIR__ . '/../../../../erplogs/';
    }

    protected function get_complete_label_auxiliar_filename($file_name) {
        $file_dir = $this->get_base_dir();
        $file_address = $file_dir . $file_name;
        return $file_address;
    }
}
