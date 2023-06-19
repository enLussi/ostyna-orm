<?php


namespace Ostyna\ORM\Commands;

use Ostyna\Component\Commands\AbstractCommand;
use Ostyna\Component\Utils\ConsoleUtils;
use Ostyna\Component\Utils\CoreUtils;
use Ostyna\ORM\Utils\DatabaseUtils;
use PDO;

class EntityCommand extends AbstractCommand {


  public function execute(array $options = []){
    
    $executing = true;
    while (count($options) > 0 && $executing) {
      switch($options[0]) {
        case 'new':
          $options = $this->delete_same_category_options($options, ['modify', 'prepare', 'remove']);
          $executing = $this->new_entity();
          $options = array_diff($options, ['new']);
          break;
        case 'modify':
          $options = $this->delete_same_category_options($options, ['new', 'prepare', 'remove', 'migrate']);
          $executing = $this->modify_entity();
          $options = array_diff($options, ['modify']);
          break;
        case 'prepare':
          $options = $this->delete_same_category_options($options, ['modify', 'new', 'remove', 'migrate']);
          $executing = $this->prepare_entity();
          $options = array_diff($options, ['prepare']);
          break;
        case 'remove':
          $options = $this->delete_same_category_options($options, ['modify', 'new', 'prepare', 'migrate']);
          $executing = $this->remove_entity();
          $options = array_diff($options, ['remove']);
          break;
        case 'migrate':
          $executing = $this->migrate_entity();
          $options = array_diff($options, ['migrate']);
          break;
        case 'generate':
          $options = $this->delete_same_category_options($options, ['modify', 'new', 'prepare', 'migrate', 'remove']);
          $executing = $this->class_entity('Test');
          $options = array_diff($options, ['generate']);
          break;
        default:
          break;
      }
    }

    if($executing) {
      ConsoleUtils::success_prompt("Success");
    } else {
      ConsoleUtils::abort_prompt("Aborted");
    }

    
  }

  private function new_entity() {

    $entity = "";
    $properties = [];

    // On récupère tous les noms d'entité déjà existant
    $entities_names = self::get_entity_names();

    // On récupère le nom de l'entité via l'utilisateur
    // Le nom est standardisé avec ucfirst et strtolower
    $entity = ConsoleUtils::prompt_response("Nom de la Classe de l'entité", function (string $response, $data) {
      if(strlen($response) <= 3) {
        return false;
      } 
      if(in_array($response, $data)) {
        return false;
      }
      return true;
    }, "", "", "Nom de classe non valide (nom trop court[3] ou déjà existant)", "", $entities_names);
    $entity = ucfirst(strtolower($entity));

    // Ajout de la colonne id (clé primaire)
    $properties[] = [
      'Field' => "id",
      'Type' => "INT(11)",
      'Null' => "NO",
      'Key' => "PRI",
      'Default' => null,
      'Extra' => "auto_increment"
    ];

    // Création de la valeur dans entities.json
    ConsoleUtils::json_in_file("/migrations/entities.json", "Create entity <$entity>", $properties, value: $entity);

    // On ajoute les propriétés via l'utilisateur avec la méthode add_properties
    $properties = $this->add_properties($properties);

    // On applique les changements
    ConsoleUtils::json_in_file("/migrations/entities.json", "Update entity <$entity>", $properties, value: $entity);

    return true;
  }

  private function prepare_entity() {

    // On vérifie si le fichier regroupant les informations des entités existe
    // S'il n'existe pas on renvoie un message prévenant du problème et on annule
    // l'action.
    // S'il existe on continue l'exécution de la méthode.
    if(!file_exists(CoreUtils::get_project_root()."/migrations/entities.json")) {
      ConsoleUtils::prompt_message("Fichier migrations/entities.json n'existe pas, la migration est impossible.", 'error');
      return false;
    }

    // On récupère les informations des entités enregistrer via la commande entity 
    $entities = json_decode(file_get_contents(CoreUtils::get_project_root()."/migrations/entities.json"), true);

    // On récupère les informations des tables dans la base de données
    $tables = [];
    if(DatabaseUtils::db_available()){
      foreach(DatabaseUtils::sql("SHOW TABLES", respond: true) as $result) {
        $key = $result[array_key_first($result)];
        $data = DatabaseUtils::sql("DESCRIBE $key", respond: true);

        $tables[$key] = $data;
      }
    } else {
      ConsoleUtils::prompt_message("La Base de Données '$_ENV[DB_NAME]' n'existe pas. database --create pour créer la base de données.", 'error');
      return false;
    }

    // On crée un id unique avec la date à laquelle la commande est utilisée
    // On concatène cet id avec le nom du fichier de classe de la migrations
    // Cet id sera utile pour savoir dans quel ordre appelé les méthodes upgrade de chaque 
    // migrations.
    $id = date("YmdHms");
    $class_name = "MV".$id;
    $file_name = $class_name.".php";

    // On crée les ligne de commande sql à préparer est exécuter
    // 
    $sql_commands = [];
    // On regarde si toutes les table sont à jour du côté back
    foreach($entities as $key => $entity) {
      // On vérifie si l'entité est une table dans la db sinon 
      if(!isset($tables[strtolower($key)])) {
        $sql_command = "CREATE TABLE $key (";
        foreach($entity as $index => $property) {
          $n = $property['Null'] === "YES" ? "" : " NOT NULL";
          $d = is_null($property['Default']) ? "" : " DEFAULT $property[Default]";
          $k = $property['Key'] === "UNI" ? " UNIQUE" : "";
          $k = $property['Key'] === "PRI" ? " PRIMARY KEY" : "";
          $e = " ".strtoupper($property['Extra']);
          $sql_command .= ($index !== 0 ? ", " : "") . "$property[Field] $property[Type]$n$d$k$e";
        }
        $sql_commands[] = $sql_command.");";
      } 
      if(isset($tables[strtolower($key)])) {
        foreach($entity as $index => $property) {
          // Est ce que la propiété existe dans la table
          // Si oui vérifier le type , nullable, ...
          // Si non Alter table add column et ajoute la colonne
          $property_table_list = [];
          foreach($tables[strtolower($key)] as $table_property) {
            $property_table_list[] = $table_property['Field'];
          }

          if(in_array($property['Field'], $property_table_list)) {
            foreach($tables[strtolower($key)] as $table_property) {
              if($property['Field'] === $table_property['Field']) {
                $type_change = strtolower($property['Type']) === $table_property['Type'] ? false : true;
                $null_change = $property['Null'] === $table_property['Null'] ? false : true;
                $key_change = $property['Key'] === $table_property['Key'] ? false : true;
                $default_change = $property['Default'] === $table_property['Default'] ? false : true;
                $extra_change = $property['Extra'] === $table_property['Extra'] ? false : true;

                $change = $type_change || $null_change || $key_change || $default_change || $extra_change;
    
                $t = $type_change ? " ".$property['Type'] : "";
                $n = $null_change ? ($property['Null'] === "YES" ? "" : " NOT NULL" ) : "";
                $d = $default_change ? (is_null($property['Default']) ? "" : " DEFAULT $property[Default]") : "";
                $k = $key_change ? ($property['Key'] === "UNI" ? " UNIQUE" : "") : "";
                $e = $extra_change ? " ".$property['Extra'] : "";
    
                if ($change) {
                  $sql_command = "ALTER TABLE $key MODIFY $property[Field]$t$n$d$k$e;";
                  $sql_commands[] = $sql_command;
                }
              }
            }
          } else {
            $sql_command = "ALTER TABLE $key ADD ";
            $n = $property['Null'] === "YES" ? "" : " NOT NULL";
            $d = is_null($property['Default']) ? "" : " DEFAULT $property[Default]";
            $k = $property['Key'] === "UNI" ? " UNIQUE" : "";
            $e = " ".$property['Extra'];
            $sql_command .= "$property[Field] $property[Type]$n$d$k$e;";

            $sql_commands[] = $sql_command;
          }

        }
      }
    }

    foreach($tables as $key => $table) {
      if(!isset($entities[ucfirst($key)])) {
        $sql_command = "DROP TABLE $key;";
        $sql_commands[] = $sql_command;
      }
    }

    $sql = "\t\tDatabaseUtils::sql(\n\t\t\t'" . implode("\n\t\t\t", $sql_commands) . "'\n\t\t);\n";

    // On crée le squelette de la classe
    $file_content = $this->generate_by_skeleton('migrations.skl.php', ['sql' => $sql, 'class' => $class_name]);

    // Informe l'utilisateur que le fichier de migrations a été crée avec
    // la référence $class_name (MV_"date")
    ConsoleUtils::write_in_file('/migrations/'.$file_name, "Created Reference: $class_name", $file_content);

    $path_data = "/var/ostyna/orm/";
    if(!is_dir(CoreUtils::get_project_root().$path_data)) {
      mkdir(CoreUtils::get_project_root().$path_data, 0777, true);
    }

    $file_data = "migration.json";
    $content_data = [
      'last' => $id
    ];

    // On supprime le contenu du fichier monitoring
    file_put_contents(CoreUtils::get_project_root()."/var/ostyna/orm/monitoring.md", "");

    ConsoleUtils::json_in_file($path_data.$file_data, "Update Last version", $content_data, true);

    return true;
  }

  private function migrate_entity() {
    // Quel version ? last by default 
    // Purger la base de données ? (recommandé)

    if(!file_exists(CoreUtils::get_project_root().'/var/ostyna/orm/migration.json')) {
      ConsoleUtils::prompt_message("Un problème est survenu lors de la récupération de version.", 'danger');
      return false;
    }
    $id = json_decode(file_get_contents(CoreUtils::get_project_root().'/var/ostyna/orm/migration.json'), true);
    if(!isset($id['last'])) {
      ConsoleUtils::prompt_message("Un problème est survenu lors de la lecture de version.", 'danger');
      return false;
    }

    $version = $id['last'];
    $class = "\Migrations\MV$version";

    require_once CoreUtils::get_project_root()."/migrations/MV$version.php";
    (new $class())->upgrade();
    return true;
  }

  private function class_entity(string $name) {

    if(!file_exists(CoreUtils::get_project_root()."/migrations/entities.json")) {
      ConsoleUtils::prompt_message("Fichier migrations/entities.json n'existe pas, la migration est impossible.", 'error');
      return false;
    }

    $properties = json_decode(file_get_contents(CoreUtils::get_project_root()."/migrations/entities.json"), true)[$name];

    $file_name = ucfirst($name).".php";

    $attributes = "";
    $variables = [];
    $methods = "";
    $affectation = "";
    $use = "";

    foreach($properties as $property) {

      $type = $this->get_type($property['Type']);

      if($type === "DateTimeImmutable") {
        $use .= "use DateTimeImmutable;\n";
      }

      $variables[] = $property['Field'];

      $line = "\tprivate $type $$property[Field];\n";
      $attributes .= $line;
    }

    foreach($variables as $variable) {
      $line = "\t\t\t\$this->$variable = \$entity['$variable'];\n";
      $affectation .= $line;
    }

    foreach($properties as $property) {

      $type = $this->get_type($property['Type']);

      $n = ucfirst($property['Field']);
      $line = "\tpublic function  set$n($type $$property[Field]) { \n\t\t\$this->$property[Field] = $$property[Field]; \n\t}\n\n";
      $line .= "\tpublic function  get$n(): $type { \n\t\treturn \$this->$property[Field]; \n\t}\n";

      $methods .= $line;
      
    }

    $file_content = <<<PHP
    <?php

    namespace App\Entity;

    use Ostyna\ORM\Base\BaseEntity;
    use Ostyna\ORM\Utils\DatabaseUtils;
    $use

    class $name extends BaseEntity{

    $attributes

      public function __construct(?int \$identifier){
        if(!is_null(\$identifier) && is_int(\$identifier)) {
          \$entity = DatabaseUtils::get_entity(\$identifier);
    $affectation
        }
      }

    $methods

    }
    PHP;

    ConsoleUtils::write_in_file('/src/Entity/'.$file_name, "Created Reference: $name", $file_content);
    return true;
  }

  private function modify_entity() {
    $entity = "";

    // On récupère tous les noms d'entité déjà existants
    $entities_names = self::get_entity_names();

    $help = "";
    if(count($entities_names) > 0) {
      $help = "Noms d'Entité enregistrées :\n";
      foreach($entities_names as $entity) {
        $help .= "[ $entity ] ";
      }
    }


    // On demande à l'utilisateur de sélectionner une entité et on vérifie si elle existe
    $entity = ConsoleUtils::prompt_response("Nom de la Classe de l'entité", function (string $response, $data) {
      if(strlen($response) <= 3) {
        return false;
      } 
      if(!in_array($response, $data)) {
        return false;
      }
      return true;
    }, "", $help, "Nom de classe inconnu.", "", $entities_names);

    $properties = $this->get_properties($entity);
    
    $add = ConsoleUtils::prompt_response("Voulez-vous ajouter ou supprimer une propriété (ajouter/supprimer)", function (string $response) {
      if(!in_array(strtolower($response), ['ajouter', 'supprimer', 'a', 's'])) {
        return false;
      }
      return true;
    }, "", "", "Donnez une réponse valide.", "ajouter");

    $add = ($add === "ajouter" || $add === "a") ? "add" : "drop"; 

    if($add === "add") {
      $properties = $this->add_properties($properties);
    } elseif($add === "drop") {
      $properties = $this->remove_property($properties);
    }

    ConsoleUtils::json_in_file("/migrations/entities.json", "Modify entity", $properties, value: $entity);
    return true;
  }

  private function remove_entity() {
    $entity = "";

    // On récupère tous les noms d'entité déjà existants
    $entities_names = $this->get_entity_names();
    $entities = $this->get_entities();

    $help = "";
    if(count($entities_names) > 0) {
      $help = "Noms d'Entité enregistrées :\n";
      foreach($entities_names as $entity) {
        $help .= "[ $entity ] ";
      }
    }

    $entity = ConsoleUtils::prompt_response("Nom de la Classe de l'entité", function (string $response, $data) {
      if(strlen($response) <= 3) {
        return false;
      } 
      if(!in_array($response, $data)) {
        return false;
      }
      return true;
    }, "", $help, "Nom de classe inconnu", "", $entities_names);

    if(!is_null($entity)) {
      unset($entities[$entity]);
    }

    file_put_contents(CoreUtils::get_project_root()."/migrations/entities.json", json_encode($entities, JSON_PRETTY_PRINT));
    return true;
  }

  private function add_properties(array $properties = []): array{

    do {

      // On assigne des valeurs par défaut aux variables
      $name = "";
      $type = "";
      $length = 0;
      $null = "";
      $relation = "";
      $default = null;

      // On récupère le nom de chaque propriétés déjà existante
      $properties_names = [];
      foreach($properties as $p) {
        array_push($properties_names, $p['Field']);
      }

      // On demande le nom de la propriété à l'utilisateur
      // et on la formate en lowercase
      $name = ConsoleUtils::prompt_response("Nom de la nouvelle propriété", function (string $response, $data){
        if(strlen($response) === 0) return true;
        if(strlen($response) <= 2) return false;
        if(in_array($response, $data)) return false;
        return true; 
      }, "appuyer sur entrée pour arréter l'ajout de propriétés.", "", 
      "Donner un nom de propriété valide (nom trop court[5] ou déjà existant)", "", $properties_names);
      $name = strtolower($name);

      if(strlen($name) > 0) {

        $help = 
          "\n\e[92mNombres\e[39m" . 
          "\n\t*\e[93m INT\e[39m" .
          "\n\t*\e[93m SMALLINT\e[39m" .
          "\n\t*\e[93m BIGINT\e[39m" .
          "\n\t*\e[93m TINYINT\e[39m" .
          "\n\t*\e[93m FLOAT\e[39m" .
          "\n\t*\e[93m DOUBLE\e[39m" .
          "\n" .
          "\n\e[92mChaînes de caractère\e[39m" .
          "\n\t*\e[93m VARCHAR\e[39m" .
          "\n\t*\e[93m TEXT\e[39m" .
          "\n" .
          "\n\e[92mDates et Heures\e[39m" .
          "\n\t*\e[93m DATE\e[39m" .
          "\n\t*\e[93m TIME\e[39m" .
          "\n\t*\e[93m DATETIME\e[39m" .
          "\n\t*\e[93m TIMESTAMP\e[39m" .
          "\n" .
          "\n\e[92mBooléen\e[39m" .
          "\n\t*\e[93m BOOL\e[39m" .
          "\n" .
          "\n\e[92mBinaire\e[39m" .
          "\n\t*\e[93m BLOB\e[39m" .
          "\n\t*\e[93m BINARY\e[39m" .
          "\n" .
          "\n\e[92mAssociations\e[39m" .
          "\n\t*\e[93m MANYTOONE\e[39m" .
          "\n\t*\e[93m ONETOMANY\e[39m" .
          "\n\t*\e[93m ONETOONE\e[39m" .
          "\n\t*\e[93m MANYTOMANY\e[39m"
          ;


        // On demande le type de la propriété à l'utilisateur
        // et on la formate en uppercase
        $type = ConsoleUtils::prompt_response("Type de l'entrée", function (string $response){
          if(!in_array(strtoupper($response), ['INT', 'SMALLINT', 'BIGINT', 'TINYINT', 'FLOAT', 'DOUBLE', 'VARCHAR', 'TEXT', 'DATE',
          'TIME', 'DATETIME', 'TIMESTAMP', 'BOOL', 'BLOB', 'BINARY', 'MANYTOONE', 'ONETOMANY', 'ONETOONE', 'MANYTOMANY']))
          {
            return false;
          }
          return true;
        }, "entrez ? pour voir tous les types possibles", $help, "Entrez un type de données valide.", "");
        $type = strtoupper($type);


        switch($type) {
          case 'INT':
            $length = 11;
            break;
          case 'BINARY':
            $length = 64;
            break;
          default:
            break;
        }

        // On vérifie la réponse de l'utilisateur
        // Si c'est une relation alors on demande avec quelle entité la relation doit être lié
        // Sinon si c'est un varchar on demande la taille de la propiété et on demande si la propriété peut être null  
        // et si elle a une valeur par défaut
        if(in_array(strtoupper($type), ['MANYTOONE', 'ONETOMANY', 'ONETOONE', 'MANYTOMANY'])){
          $relation = ConsoleUtils::prompt_response("Avec quelle entité la relation doit être liée", function (string $response) {
            if(!array_key_exists(strtolower($response), json_decode(file_get_contents(CoreUtils::get_project_root()."/migrations/entities.json"), true))){
              return false;
            }
            return true;
          }, "", "", "Entité non existante", "");
        } else {
          if(strtoupper($type) === "VARCHAR"){
            $length = ConsoleUtils::prompt_response("Longueur max de l'entrée", function (string $response) {
              if(!is_int(intval($response))){
                return false;
              }
              return true;
            }, "", "", "Entrez une valeur entière", "255");
          }

          $null = ConsoleUtils::prompt_response("La valeur peut être nulle (oui/non)", function (string $response) {
            if(!in_array(strtolower($response), ['yes', 'no', 'oui', 'non'])) {
              return false;
            }
            return true;
          }, "", "", "Donnez une réponse valide.", "no");
  
          $default = ConsoleUtils::prompt_response("Quelle valeur par défaut ?", function (string $response) {
            return true;
          }, "Laisser vide pour passer", "", "Donnez une réponse valide.");

          $default = strlen($default) > 0 ? $default : null;
  
          $null = in_array($null, ['oui', 'yes']) ? true : false;
        }

        // On ajoute cette nouvelle propriété au tableau
        $properties[] = [
          'Field' => $name,
          'Type' => $type.($length > 0 ? "($length)" : ""),
          'Null' => $null ? "YES" : "NO",
          'Key' => $relation,
          'Default' => $default,
          'Extra' => ""
        ];
      }
    }while(strlen($name) > 0);

    // On retourne le tableau de toutes les propriétés
    return $properties;
  }

  private function remove_property(array $properties): array {

    do{

      $properties_names = [];
      foreach($properties as $p) {
        array_push($properties_names, $p['Field']);
      }
  
      $name = ConsoleUtils::prompt_response("Nom de la nouvelle propriété", function (string $response, $data){
        if(strlen($response) === 0) return true;
        if(strlen($response) <= 2) return false;
        if(!in_array($response, $data)) return false;
        return true; 
      }, "appuyer sur entrée pour arréter la suppression de propriété.", "", 
      "Nom de propriété inconnu", "", $properties_names);

      foreach($properties as $index => $property) {
        if($name === $property['Field']) {
          unset($properties[$index]);
        }
      }

      

      ConsoleUtils::prompt_message("Suppression de la propriété $name pris en compte", 'success');

    }while(strlen($name) > 0);

    return $properties;

  }

  private function check_db_availability() {
    return DatabaseUtils::get_PDO() instanceof PDO;
  }

  private function get_entity_names(){
    $entities_names = [];
    if(!file_exists(CoreUtils::get_project_root()."/migrations/entities.json")) {
      ConsoleUtils::prompt_message("Un problème est survenu lors de la lecture du fichier entities.", 'danger');
      ConsoleUtils::abort_prompt("Aborted");
      exit;
    }
    $json_entities = json_decode(file_get_contents(CoreUtils::get_project_root()."/migrations/entities.json"), true);
    if(!is_null($json_entities) && count($json_entities) > 0){
      foreach($json_entities as $key => $e) {
        array_push($entities_names, $key);
      }
    }

    return $entities_names;
  }

  private function get_properties(string $entity_name) {
    if(!file_exists(CoreUtils::get_project_root()."/migrations/entities.json")) {
      ConsoleUtils::prompt_message("Un problème est survenu lors de la lecture du fichier entities.", 'danger');
      ConsoleUtils::abort_prompt("Aborted");
      exit;
    }
    if(!isset(json_decode(file_get_contents(CoreUtils::get_project_root()."/migrations/entities.json"), true)[$entity_name])) {
      ConsoleUtils::prompt_message("Un problème est survenu lors de la lecture du fichier entities.", 'danger');
      ConsoleUtils::abort_prompt("Aborted");
      exit;
    }
    return json_decode(file_get_contents(CoreUtils::get_project_root()."/migrations/entities.json"), true)[$entity_name];
  }

  private function get_entities() {
    if(!file_exists(CoreUtils::get_project_root()."/migrations/entities.json")) {
      ConsoleUtils::prompt_message("Un problème est survenu lors de la lecture du fichier entities.", 'danger');
      ConsoleUtils::abort_prompt("Aborted");
      exit;
    }
    return json_decode(file_get_contents(CoreUtils::get_project_root()."/migrations/entities.json"), true);
  }

  private function get_type(string $type) {

    preg_match("/^(\w+)/", $type, $matches);
    $php_type = "";
    if(in_array($matches[1], ['INT', 'TINYINT', 'SMALLINT', 'BIGINT', 'TIMESTAMP'])) 
      $php_type = "int";
    elseif (in_array($matches[1], ['VARCHAR', 'TEXT']))
      $php_type = "string";
    elseif ($matches[1] === "FLOAT")
      $php_type = "float";
    elseif ($matches[1] === "DOUBLE")
      $php_type = "double";
    elseif (in_array($matches[1],['DATE', 'TIME', 'DATETIME']))
      $php_type = "DateTimeImmutable";
    elseif ($matches[1] === "BOOL") 
      $php_type = "bool";
    else 
      $php_type = "int";

    return $php_type;
    
  }

}