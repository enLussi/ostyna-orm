<?= "<?php" ?>

namespace App\Entity;

use Ostyna\ORM\Base\BaseEntity;
use Ostyna\ORM\Utils\DatabaseUtils;
<?= $data['use'] ?>

class <?= $data['name'] ?> extends BaseEntity{

<?= $data['attributes'] ?>

  public function __construct(?int $identifier){
    if(!is_null($identifier) && is_int($identifier)) {
      $entity = DatabaseUtils::get_entity($identifier, '<?= $data['table'] ?>');
    <?= $data['affectation'] ?>
    }
  }

<?= $data['methods'] ?>
  
}