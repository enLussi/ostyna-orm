<?php

namespace Ostyna\ORM\Base;

use Ostyna\ORM\Utils\DatabaseUtils;

abstract class BaseSupply {
  abstract public function supply();

  protected function send_supplies(string $table, array $insertions) {
    $keys = "";
    $values = "";

    
    foreach($insertions as $column => $insertion) {
      $keys .= "$column, ";
      if(is_array($insertion)) {
        $insertion = json_encode($insertion);
      }
      $values .= "'$insertion', ";
    }

    $keys = rtrim($keys, ', ');
    $values = rtrim($values, ', ');
    $row_id = DatabaseUtils::sql("INSERT INTO $table ($keys) VALUES ($values);", respond: true);

    return $row_id;

  }
}