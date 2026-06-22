<?php
if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) { die(); }

use Bitrix\Main;

$encoding = Main\Application::isUtfMode() ? 'utf8' : 'cp1251';

return [
	'js' => array_filter([
        'select2.js',
        LANGUAGE_ID === 'ru' ? 'ru.' . $encoding . '.js' : null,
    ]),
	'css' => [ 'select2.css', 'select2.theme.css' ],
	'rel' => [ '@lib.jquery' ],
	'variable' => 'jQuery.fn.select2',
];