<?php

namespace Ostyna\ORM\Migrations;

abstract class AbstractMigrations {

  public function upgrade(): void{}

  public function downgrade(): void{}

} 