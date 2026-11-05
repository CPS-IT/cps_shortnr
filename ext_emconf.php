<?php

$EM_CONF['cps_shortnr'] = [
  'title' => 'CPS ShortNr Resolver',
  'description' => 'Resolves Short Alias Uri',
  'category' => 'plugin',
  'author' => 'Benjamin Rannow',
  'author_email' => 'b.rannow@familie-redlich.de',
  'state' => 'stable',
  'version' => '4.1.2',
    'constraints' => [
        'depends' => [
            'typo3' => '13.4.0-13.4.99',
            'php' => '8.2.0-8.5.99',
        ],
        'conflicts' => [],
        'suggests' => []
    ],
];

