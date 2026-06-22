<?php
if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED!==true) { die(); }

/** @var array $arResult */

$waitHidden = null;
$filledTab = null;

foreach ($arResult['TABS'] as $tabKey => &$tab)
{
	if (!empty($tab['FIELDS']))
	{
		$filledTab = $tabKey;

		if ($waitHidden !== null)
		{
			$tab['HIDDEN'] = array_merge($tab['HIDDEN'], $arResult['TABS'][$waitHidden]['HIDDEN']);
			unset($arResult['TABS'][$waitHidden]);
			$waitHidden = null;
		}

		continue;
	}

	$waitHidden = $tabKey;
}
unset($tab);

$arResult['TABS'] = array_values($arResult['TABS']);
