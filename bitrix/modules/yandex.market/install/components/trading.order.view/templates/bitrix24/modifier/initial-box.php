<?php
if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) { die(); }

if (empty($arResult['BOX'])) { return; }

$boxIndex = 0;

foreach ($arResult['BOX'] as &$box)
{
	if (empty($box['ITEMS'])) { continue; }

	foreach ($box['ITEMS'] as &$boxItem)
	{
		$boxItem['INITIAL_BOX'] = $boxIndex;
	}
	unset($boxItem);

	++$boxIndex;
}
unset($box);
