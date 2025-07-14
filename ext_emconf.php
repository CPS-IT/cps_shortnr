<?php

$EM_CONF['cps_shortnr'] = [
  'title' => 'CPS ShortNr Resolver',
  'description' => 'Resolves Short Alias Uri',
  'category' => 'plugin',
  'author' => 'Benjamin Rannow',
  'author_email' => 'b.rannow@familie-redlich.de',
  'state' => 'alpha',
  'version' => '12.0.0',
    'constraints' => [
        'depends' => [
            'typo3' => '12.4.0-13.4.99',
            'br-toolkit' => '2.0.4-2.99.99',
            'br_toolkit' => '11.0.0-11.99.99'
        ],
        'conflicts' => [],
        'suggests' => []
    ],
];

