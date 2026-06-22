<?php
if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) { die(); }

use Bitrix\Main\Loader;
use Yandex\Market\Ui\Extension;

$rel = 'jquery2';

if (Loader::includeModule('yandex.market'))
{
	$rel = Extension::getOne([ 'jquery2', 'jquery', 'jquery3' ], true);
}

return [
	'js' => 'noConflict.js',
	'rel' => $rel,
	'variable' => 'jQuery',
];