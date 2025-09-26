<?php

namespace tmwe_email\service;

/**
 * Description of abstract_service
 *
 * @author pepe
 */
abstract class Abstract_Service {

    protected static $instances;

    /**
     * 
     * @param string class name
     * @return Abstract_Service
     */
    public static function get_instance() {
        $class = get_called_class();
        if (self::$instances == null || !isset(self::$instances[$class])) {
            if (self::$instances == null) {
                self::$instances = [];
            }
            self::$instances[$class] = new $class();
        }
        return self::$instances[$class];
    }
    
    protected function log_fail($message){
        \tmwe_email\log\Logger::get_instance()->error($message);
    }
    
    protected function log($message){
        \tmwe_email\log\Logger::get_instance()->log($message);
    }
}
