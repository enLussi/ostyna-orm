<?php

namespace Ostyna\ORM\Utils;

use PDO;
use PDOException;
use PDOStatement;
use Ostyna\Component\Error\FatalException;

class DatabaseUtils {


  private static function get_PDO(){

    if(!isset($_ENV['DB_HOST']) || !isset($_ENV['DB_NAME']) || !isset($_ENV['DB_USER']) || !isset($_ENV['DB_PASS'])){
      throw new FatalException("PDO can't be create, there is no viable Host for Database.", 0);
    }

    $dsn = "mysql:host=$_ENV[DB_HOST];dbname=$_ENV[DB_NAME];";
    return new PDO($dsn, $_ENV['DB_USER'], $_ENV['DB_PASS']);
  }

  public static function prepare_request(string $query, array $params = [], array $options = []): PDOStatement {
    try {
      $pdo = self::get_PDO();

      if($pdo) {
        $stmt = $pdo->prepare($query);
        foreach($params as $key => $param) {
          $stmt->bindParam($key, $param);
        }
        return $stmt;
      }
    } catch (PDOException $pdoe) {

    }
  }

  public static function execute_request(PDOStatement $stmt) {
    $stmt->execute();
    return $stmt->fetchAll();
  }

}