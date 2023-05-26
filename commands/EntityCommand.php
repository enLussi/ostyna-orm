<?php


namespace Ostyna\ORM\Commands;

use App\Core;
use Ostyna\Component\Commands\AbstractCommand;
use Ostyna\Component\Utils\ConsoleUtils;
use Ostyna\Component\Utils\CoreUtils;

class EntityCommand extends AbstractCommand {


  public function execute(array $options = []){
    
    while (count($options) > 0) {
      switch($options[0]) {
        case 'new':
          $options = $this->delete_same_category_options($options, ['modify, prepare']);
          $this->new_entity();
          $options = array_diff($options, ['new']);
          break;
        case 'modify':
          $options = $this->delete_same_category_options($options, ['new, prepare']);
          $this->modify_entity();
          $options = array_diff($options, ['modify']);
          break;
        case 'prepare':
          $options = $this->delete_same_category_options($options, ['modify, new']);
          $this->prepare_entity();
          $options = array_diff($options, ['prepare']);
          break;
        default:
          break;
      }
    }

  }

  private function new_entity() {

    // Class name -> create php file
    // New property -> name, types(voir sql type), length, nullable + relation
    $entity = "";
    $properties = [];

    $entity = ConsoleUtils::prompt_response("Nom de la Classe de l'entité", function (string $response) {
      if(strlen($response) <= 3) {
        echo strlen($response);
        return false;
      }
      return true;
    }, "", "", "Donner un nom de Classe avec au moins 3 caractères", "");

    do {

      $name = "";
      $type = "";
      $length = 0;
      $null = "";

      $name = ConsoleUtils::prompt_response("Nom de la nouvelle propriété", function (string $response){
        if(strlen($response) === 0) return true;
        if(strlen($response) <= 2) return false;
        return true; 
      }, "appuyer sur entrée pour arréter l'ajout de propriétés.", "", "Donner un nom de propriété avec au moins 5 caractères", "");

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

        $type = ConsoleUtils::prompt_response("Type de l'entrée", function (string $response){
          if(!in_array(strtoupper($response), ['INT', 'SMALLINT', 'BIGINT', 'TINYINT', 'FLOAT', 'DOUBLE', 'VARCHAR', 'TEXT', 'DATE',
          'TIME', 'DATETIME', 'TIMESTAMP', 'BOOL', 'BLOB', 'BINARY', 'MANYTOONE', 'ONETOMANY', 'ONETOONE', 'MANYTOMANY']))
          {
            return false;
          }
          return true;
        }, "entrez ? pour voir tous les types possibles", $help, "Entrez un type de données valide.", "");

        switch(strtoupper($type)) {
          case 'INT':
            $length = 11;
            break;
          case 'BINARY':
            $length = 64;
            break;
          default:
            break;
        }


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

        $null = in_array($null, ['oui', 'yes']) ? true : false;

        $properties[] = [
          'name' => $name,
          'type' => $type,
          'length' => $length,
          'null' => $null,
        ];
      }
    }while(strlen($name) > 0);

    $uniqidReal = CoreUtils::uniqidReal(5);

    $json_entities = [
        'name' => $entity,
        'properties' => $properties
    ];
    ConsoleUtils::json_in_file('/migrations/entities.json', "Update entities", $json_entities);

    ConsoleUtils::success_prompt("Success");

  }

  private function prepare_entity() {

    // add created value in each entities to see if  

    if(!file_exists(CoreUtils::get_project_root().'/migrations/entities.json')) {
      ConsoleUtils::prompt_message("Fichier migrations/entities.json n'existe pas, la migration est impossible.", 'error');
      exit;
    }

    $uniqidReal = CoreUtils::uniqidReal(5);
    $file_name = "migrations_".date('dmY')."_".$uniqidReal.".php";
    $sql_commands = "";

    $entities = json_decode(file_get_contents(CoreUtils::get_project_root().'/migrations/entities.json'), true);

    foreach($entities as $entity) {
      $sql_command = "CREATE TABLE $entity[name] (id int(11) not null primary key auto_increment";

      foreach($entity['properties'] as $property) {
        $l = $property['length'] > 0 ? "($property[length])" : '';
        $n = $property['null'] ? '' : 'not null';
        $sql_command .= ", $property[name] $property[type]$l $n";
      }

      $sql_command .= ");\n";
      $sql_commands .= $sql_command;
    }

    ConsoleUtils::write_in_file('/migrations/'.$file_name, "Created Reference: $uniqidReal", $sql_commands);
    ConsoleUtils::success_prompt("Success");
  }

  private function modify_entity() {
    echo 'Modifier Entité';
  }

}