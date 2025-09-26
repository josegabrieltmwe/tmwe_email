<?php

require_once __DIR__.'/vendor/autoload.php';
require_once __DIR__.'/config.php';

function tmwe_pdf_load_dependencies() {

    spl_autoload_register(function ($class_name) {
        
        //change to include erp model_layer
        if (false !== strpos($class_name, 'model_layer')) {
            require_once __DIR__.'/../model_layer/database.php';
        }
        
        if (false !== strpos($class_name, 'tmwe_')) {
            $classes_dir = (dirname(__FILE__));
            $strip = explode('\\', strtolower($class_name));
            $class_download = "";
            for ($i = 1; $i < count($strip) - 1; $i++) {
                $class_download .= $strip[$i] . '/';
            }
            
            $class_download .= $strip[count($strip) - 1] . '.php';
            require_once str_replace("\\", '/', $classes_dir . '/' . $class_download);
        }
        
    });
}

tmwe_pdf_load_dependencies();