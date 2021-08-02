<?php

return [
    'frontend' => [
        'cps-shortnr' => [
            'target' => \CPSIT\CpsShortnr\Middleware\ShortUrlMiddleware::class,
            'before' => [
                'typo3/cms-frontend/page-resolver',
            ],
        ],
    ],
];
