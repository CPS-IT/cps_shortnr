<?php

return [
    'ctrl' => [
        'title' => 'ShortNr test record',
        'label' => 'title',
        'delete' => 'deleted',
        'languageField' => 'sys_language_uid',
        'transOrigPointerField' => 'l10n_parent',
        'versioningWS' => true,
        // editors create these on standard pages (like news records), DataHandler refuses otherwise
        'security' => ['ignorePageTypeRestriction' => true],
        'enablecolumns' => [
            'disabled' => 'hidden',
            'starttime' => 'starttime',
            'endtime' => 'endtime',
        ],
    ],
    'columns' => [
        'title' => [
            'label' => 'Title',
            'config' => ['type' => 'input'],
        ],
        'hidden' => [
            'label' => 'Hidden',
            'config' => ['type' => 'check'],
        ],
        'record_type' => [
            'label' => 'Type',
            'config' => ['type' => 'number'],
        ],
    ],
    'types' => [
        '0' => ['showitem' => 'title, record_type'],
    ],
];
