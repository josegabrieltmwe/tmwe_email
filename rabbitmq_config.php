<?php

namespace tmwe_email;

/**
 * Description of rabbitmq_config
 *
 * @author pepe
 */
class Rabbitmq_Config {

    protected static $instance;
    protected $user;
    protected $server;
    protected $port;
    protected $password;
    protected $queue;
    protected $email_client_queue;
    protected $log_queue;

    /**
     * 
     * @return Rabbitmq_Config
     */
    public static function get_instance() {
        if (self::$instance == null) {
            $temp = new Rabbitmq_Config();
            self::$instance = $temp;
        }
        return self::$instance;
    }

    protected function __construct() {
        $this->server = '185.182.186.106';
        $this->queue = '';

        $this->port = '5672';
        $this->user = 'tmwe-rabbit-admin';
        $this->password = 'f3415fa7c1fcd36257ee98c0a9765e0b84e72ab4103786325146ef6f0439bf7e';

        $this->email_client_queue = 'tmwe_email_client_queue';
        $this->log_queue = 'tmwe_email_log_queue';
    }

    public function get_user() {
        return $this->user;
    }

    public function get_server() {
        return $this->server;
    }

    public function get_port() {
        return $this->port;
    }

    public function get_password() {
        return $this->password;
    }

    public function get_queue() {
        return $this->queue;
    }

    public function get_log_queue() {
        return $this->log_queue;
    }

    public function get_email_client_queue() {
        return $this->email_client_queue;
    }
}
