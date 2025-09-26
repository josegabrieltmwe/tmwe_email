<?php

namespace tmwe_email\model;

/**
 * Description of abstract_model_time_created_updated
 *
 * @author pepe
 */
abstract class Abstract_Model_Time_Created_Updated extends Abstract_Model {

    public function insert($item) {
        $date = date("Y-m-d G:i:s");
        $item['created_at'] = $date;

        $id = parent::insert($item);
        $item['id'] = $id;
        return $item;
    }

    public function update($item, $table = false, $where = false) {
        $date = date("Y-m-d G:i:s");
        $item['updated_at'] = $date;
        return parent::update($item, $table, $where);
    }
}
