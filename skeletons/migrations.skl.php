<?= "<?php" ?>

namespace Migrations;

use Ostyna\ORM\Migrations\AbstractMigrations;
use Ostyna\ORM\Utils\DatabaseUtils;

final class <?= $data['class'] ?> extends AbstractMigrations
{
  public function upgrade(): void {
    <?= $data['sql'] ?> 
  }

  public function downgrade(): void{

  }
      
}
