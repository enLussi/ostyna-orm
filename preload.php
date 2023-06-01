<?php

use Ostyna\Component\Utils\ConsoleUtils;

ConsoleUtils::add_commands([
  'name' => 'entity',
  'option' => [
    'new', 'modify', 'prepare', 'migrate', 'remove', 'generate'
  ],
  'method' => 'Ostyna\\ORM\\Commands\EntityCommand::execute'
]);