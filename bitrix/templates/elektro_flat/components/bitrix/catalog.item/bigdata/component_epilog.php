<?php
if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) {
	die();
}

\Bitrix\Main\Page\Asset::getInstance()->addJs($templateFolder . '/script.js');
