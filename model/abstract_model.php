<?php

namespace tmwe_email\model;

abstract class Abstract_Model {

    protected static $database;
    protected static $instances;

    /**
     * 
     * @param string class name
     * @return Abstract_Model
     */
    public static function get_instance($class = '') {
        $class = get_called_class();
        if (self::$instances == null || !isset(self::$instances[$class])) {
            if (self::$instances == null) {
                self::$instances = [];
            }
            self::$instances[$class] = new $class();
        }
        return self::$instances[$class];
    }

    protected function get_table_name($param = '') {
        $class_name = array_reverse(explode("\\", get_class($this)))[0];
        return strtolower($class_name);
    }

    public function is_table_available($table_name = null) {
        $table_name = $table_name == null ? $this->get_table_name() : $table_name;
        return $this->get_database()->table_exists($table_name);
    }

    /**
     * 
     * @return \model_layer\Database
     */
    public function get_database() {
        if (self::$database == null) {
            $config = \tmwe_email\Config::get_instance();
            self::$database = new \model_layer\Database([
                'host' => $config->get_mysql_host(),
                'user' => $config->get_mysql_user(),
                'pw' => $config->get_mysql_password(),
                    ], $config->get_mysql_database());
        }
        return self::$database;
    }

    protected function get_prefix() {
        return '';
    }

    public function insert($item) {
        $database = $this->get_database();
        return $database->insert($this->get_table_name(), $item);
    }

    public function select($where, $columns = '*', $table = false) {
        if (!$table) {
            $table = $this->get_table_name();
        }
        try {
            return $this->get_database()->select($table, $columns, $where);
        } catch (\Exception $e) {
            \tmwe_email\log\Logger::get_instance()->log($e->getMessage());
            return false;
        }
    }

    public function update($item, $table = false, $where = false) {
        if (!$where) {
            $where = ['id' => $item['id']];
        }
        if (!$table) {
            $table = $this->get_table_name();
        }

        if (count($where) == 0) {
            throw new \Exception('Wrong built update query');
        }

        return $this->get_database()->update_by_field_prefix($item, $table, $where);
    }

    public function remove($item) {
        return $this->update(['deleted_at' => date('Y-m-d H:i:s')], $this->get_table_name(), ['id' => $item['id']]);
    }

    protected function error($message) {
        \tmwe_email\log\Logger::get_instance()->error(get_called_class() . ' ' . $message);
    }

    protected function log($message) {
        \tmwe_email\log\Logger::get_instance()->log(get_called_class() . ' ' . $message);
    }
}
