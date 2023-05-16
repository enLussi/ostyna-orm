<?php


namespace Ostyna\ORM\Commands;

use Ostyna\Component\Commands\AbstractCommand;

class EntityCommand extends AbstractCommand {


  public function execute(array $option = []){
   var_dump($option, 'entity') ;
  }

}