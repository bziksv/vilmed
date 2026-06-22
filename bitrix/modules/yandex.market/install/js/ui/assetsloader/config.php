<?php
if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) { die(); }

use Bitrix\Main;

$mainVersion = Main\ModuleManager::getVersion('main');
$needDelay = (!$mainVersion || version_compare($mainVersion, '20.0') === -1);

return [
    'js' => array_filter([
        'index.js',
        $needDelay ? 'delay.js' : null,
    ]),
    'variable' => 'BX.YandexMarket.Ui.AssetsLoader',
];