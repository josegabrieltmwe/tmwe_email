<?php

namespace tmwe_email\log;


/**
 * This is the log handler for development
 * 
 * @author JG <josegabrielespinosa2015@gmail.com>
 */

class Logger_Rabbitmq {

    private static $instance;
    protected $logger;

    /**
     * 
     * @param mixed $index 
     * @return tmwe_fedex\log\Logger
     */
    public static function get_instance($index = false) {
        if (self::$instance == null) {
            self::$instance = new Logger_Rabbitmq($index);
        }
        return self::$instance;
    }

    protected function __construct($index = false) {
        $this->logger = new \Monolog\Logger('tmwe');
        $this->logger->pushHandler(\tmwe_email\log\Rabbitmq_Log::get_instance());
    }

    public function info($message, $context = array()) {
        return $this->log($message, \Monolog\Logger::INFO, $context);
    }

    public function error($message, $context = array()) {
        return $this->log($message, \Monolog\Logger::ERROR, $context);
    }

    public function warning($message, $context = array()) {
        return $this->log($message, \Monolog\Logger::WARNING, $context);
    }

    public function log($message_log, $level = null, $context = array()) {
        
        if (is_array($message_log)) {
            $message_log = (json_encode($message_log));
        }

        if ($level == \Monolog\Logger::WARNING) {
            $this->logger->warning($message_log, $context);
        }
        if ($level == \Monolog\Logger::ERROR) {
            $this->logger->error($message_log, $context);
        }
        if ($level == \Monolog\Logger::INFO || $level == '') {
            $this->logger->info($message_log, $context);
        }
    }
}
