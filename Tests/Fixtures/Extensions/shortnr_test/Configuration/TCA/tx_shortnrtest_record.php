<?php

return [
    'ctrl' => [
        'title' => 'ShortNr test record',
        'label' => 'title',
        'delete' => 'deleted',
        'languageField' => 'sys_language_uid',
        'transOrigPointerField' => 'l10n_parent',
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
        'record_type' => [
            'label' => 'Type',
            'config' => ['type' => 'number'],
        ],
    ],
    'types' => [
        '0' => ['showitem' => 'title, record_type'],
    ],
];
