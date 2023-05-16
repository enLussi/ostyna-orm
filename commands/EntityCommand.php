<?php


namespace Ostyna\ORM\Commands;

use Ostyna\Component\Commands\AbstractCommand;

class EntityCommand extends AbstractCommand {


  public function execute(array $options = []){

    foreach ($options as $option) {
      switch($option) {
        case 'new':
          $this->delete_same_category_options($options, ['modify']);
          $this->new_entity();
          break;
        case 'modify':
          $this->delete_same_category_options($options, ['new']);
          $this->modify_entity();
          break;
        default:
          break;
      }
    }

  }

  private function new_entity() {
    echo 'Nouvelle Entité';
  }

  private function modify_entity() {
    echo 'Modifier Entité';
  }

}