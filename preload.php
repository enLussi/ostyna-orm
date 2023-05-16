<?php

use Ostyna\Component\Utils\ConsoleUtils;

ConsoleUtils::add_commands([
  'name' => 'entity',
  'option' => [
    'new', 'modify'
  ],
  'method' => 'Ostyna\\Component\\Utils\\Commands::Entity'
]);