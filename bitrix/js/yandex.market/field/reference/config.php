<?php
if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) { die(); }

return [
    'js' => [
        'base.js',
        'collection.js',
        'complex.js',
        'summary.js',
    ],
    'rel' => [
        'window',
        '@plugin',
        '@lib.editDialog',
        '@lib.coreSpeedup',
    ],
    'variable' => 'BX.YandexMarket.Field.Reference',
];
