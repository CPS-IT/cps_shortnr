<?php

use CPSIT\Shortnr\Middleware\ShortNumberMiddleware;
return [
    'frontend' => [
        'CPSIT/ShortNr/ShortNrResolver' => [
            'target' => ShortNumberMiddleware::class,
            'before' => [
                'typo3/cms-frontend/backend-user-authentication',
                'typo3/cms-adminpanel/sql-logging',
                'typo3/cms-frontend/site',
                'typo3/cms-core/normalized-params-attribute'
            ],
        ]
    ]
];
