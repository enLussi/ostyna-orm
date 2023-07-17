<?php

namespace Ostyna\ORM\Utils;

use PDO;
use PDOException;
use PDOStatement;
use Ostyna\Component\Error\FatalException;

class DatabaseUtils {

  public static function db_available(): bool {

    if(!isset($_ENV['DB_HOST']) && !isset($_ENV['DB_NAME']) && !isset($_ENV['DB_USER']) && !isset($_ENV['DB_PASS'])) {
      return false;
    }

    if(strlen($_ENV['DB_HOST']) < 1 && strlen($_ENV['DB_NAME']) < 1 && strlen($_ENV['DB_USER']) < 1 && strlen($_ENV['DB_PASS']) < 1) {
      return false;
    }

    return true;
  }

  public static function get_PDO(?array $options = null){

    self::db_available();

    if(!isset($options)) {
      $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
      ];
    }

    // var_dump([$_ENV['DB_HOST'], $_ENV['DB_NAME'], $_ENV['DB_USER'], $_ENV['DB_PASS']]);
    $driver = strtolower($_ENV['DB_TYPE']);
    $dsn = "$driver:host=$_ENV[DB_HOST];dbname=$_ENV[DB_NAME];";
    try {
      $pdo = new PDO($dsn, $_ENV['DB_USER'], $_ENV['DB_PASS'], $options);
      return $pdo;
    } catch (PDOException $pdoe) {
      echo "Une erreur est survenue lors de la connection à la base de donnée.\n".$pdoe->getMessage()."\n";
    }
    return false;
  }

  public static function prepare_request(string $query, array $params = [], array $options = []): PDOStatement {
    try {
      $pdo = self::get_PDO();

      if($pdo) {
        $stmt = $pdo->prepare($query);
        foreach($params as $key => $param) {
          $stmt->bindParam(":$key", $param);
        }
        return $stmt;
      }
    } catch (PDOException $pdoe) {

    }
  }

  public static function execute_request(PDOStatement $stmt) {
    try {
      $stmt->execute();
    }catch (PDOException $pdoe) {
      echo $pdoe->getMessage();
    }
    return $stmt->fetchAll();
  }

  public static function sql(string $query, array $params = [], ?array $options = null, bool $respond = false) {

    $insert_query = (substr($query, 0, 6) === 'INSERT');

    try {
      $pdo = self::get_PDO($options);

      if($pdo) {
        $stmt = $pdo->prepare($query);
        foreach($params as $key => $param) {
          $stmt->bindParam(":$key", $param);
        }
        $stmt->execute();
        if($respond && !$insert_query) {
          return $stmt->fetchAll();
        } elseif ($respond && $insert_query) {
          return $pdo->lastInsertId();
        }
      }
    } catch (PDOException $pdoe) {
      echo $pdoe->getMessage();
    }
    
  }

  public static function get_entity(int $id, string $table) {
    $entity = self::sql("SELECT * FROM $table WHERE id = :id", [
      "id" => strval($id)
    ], respond: true);
    if( !is_null($entity) && count($entity) > 0 ) {
      return $entity[0];
    }
    return null;
  }

  public static function get_entities(string $table) {
    $entities = self::sql("SELECT * FROM $table", respond: true);
    if( !is_null($entities) && count($entities) > 0 ) {
      return $entities;
    }
    return null;
  }

}