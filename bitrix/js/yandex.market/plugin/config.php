<?php
if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) { die(); }

return [
    'js' => [
        'base.js',
        'manager.js',
    ],
    'rel' => [
        '@lib.jquery',
        '@utils',
    ],
    'variable' => 'BX.YandexMarket.Plugin.Base',
];