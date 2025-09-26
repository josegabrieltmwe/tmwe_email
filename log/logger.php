<?php

namespace tmwe_email\log;

/**
 * This is the log handler for development
 * 
 * @author JG <josegabrielespinosa2015@gmail.com>
 */
class Logger {

    private static $instance;
    protected $logger;

    /**
     * 
     * @param mixed $index 
     * @return Logger
     */
    public static function get_instance($index = false) {
        if (self::$instance == null) {
            self::$instance = new Logger($index);
        }
        return self::$instance;
    }

    protected function __construct($index = false) {
        $this->logger = new \Monolog\Logger('tmwe_email');
        //    $this->logger->pushHandler(new Rabbitmq_Log(\Monolog\Logger::DEBUG));
        $this->logger->pushHandler(new \Monolog\Handler\StreamHandler(__DIR__ . '/dev.log', \Monolog\Logger::DEBUG));
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

    public function log($message_log, $level = \Monolog\Logger::INFO, $context = array()) {

        $message_log = is_array($message_log) ? json_encode($message_log) : $message_log;

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
