<?php

namespace Ostyna\ORM\Commands;

use Ostyna\Component\Commands\AbstractCommand;
use Ostyna\Component\Utils\ConsoleUtils;
use Ostyna\Component\Utils\CoreUtils;

class InsertCommand extends AbstractCommand {


  public function execute(array $options = []){

    $executing = true;

    while (count($options) > 0 && $executing) {
      $option = $options[0];
      $value = "";

      if(count(explode('=', $options[0])) == 2) {
        $option = explode('=', $options[0])[0];
        $value = explode('=', $options[0])[1];
      }

      switch($option) {
        case 'unique':
          $executing = $this->insert_data($value);
          $options = array_diff($options, [$options[0]]);
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

  private function insert_data(string $unique) {
    $namespace = CoreUtils::get_config()['dev']['supplies'];
    $class = $namespace.'\\Supply'.ucfirst(strtolower($unique));

    if(!class_exists($class)) {
      ConsoleUtils::prompt_message("Classe de DataSupplies introuvable.", 'error');
      return false;
    }
    (new $class())->supply();

    return true;
  }

}