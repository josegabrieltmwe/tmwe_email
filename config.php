<?php

namespace tmwe_email;

class Config {

    protected static $instance;
    protected $mysql_host;
    protected $mysql_database;
    protected $mysql_user;
    protected $mysql_password;

    protected function __construct() {
        $this->mysql_database = "tmwe_email_client";
        $this->mysql_host = "127.0.0.1";
        $this->mysql_user = "tmweweb";
        $this->mysql_password = "Rt75-fdkl787-GJHG";
    }

    public function get_mysql_host() {
        return $this->mysql_host;
    }

    public function get_mysql_database() {
        return $this->mysql_database;
    }

    public function get_mysql_user() {
        return $this->mysql_user;
    }

    public function get_mysql_password() {
        return $this->mysql_password;
    }

    /**
     * 
     * @return Config
     */
    public static function get_instance() {
        if (self::$instance == null) {
            self::$instance = new Config();
        }
        return self::$instance;
    }
}
