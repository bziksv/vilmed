<?php
if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED!==true) { die(); }

use Yandex\Market\Ui\UserField\Helper\Attributes;
use Bitrix\Main\Localization\Loc;

/** @var array $arResult */
/** @var array $arParams */

foreach ($arResult['BUTTONS'] as $button)
{
	$button += [
		'BEHAVIOR' => null,
		'NAME' => null,
		'ATTRIBUTES' => [],
	];

	switch ($button['BEHAVIOR'])
	{
		case 'previous':
			if ($arResult['STEP'] === 0)
			{
				$button['NAME'] = $button['NAME'] ?: Loc::getMessage('YANDEX_MARKET_T_ADMIN_FORM_EDIT_BTN_CANCEL');
				$button['ATTRIBUTES'] += [
					'name' => 'cancel',
					'value' => 'Y',
				];
			}
			else
			{
				$button['NAME'] = $button['NAME'] ?: Loc::getMessage('YANDEX_MARKET_T_ADMIN_FORM_EDIT_BTN_PREV_STEP');
				$button['ATTRIBUTES'] += [
					'name' => 'stepAction',
					'value' => 'previous',
				];
			}
		break;

		case 'next':
			if ($arResult['STEP_FINAL'])
			{
				$button['NAME'] = $button['NAME'] ?: $arParams['BTN_SAVE'] ?: Loc::getMessage('YANDEX_MARKET_T_ADMIN_FORM_EDIT_BTN_SAVE');
				$button['ATTRIBUTES'] += [
					'class' => 'adm-btn adm-btn-save ' . ($arParams['ALLOW_SAVE'] ? '' : 'adm-btn-disabled'),
					'name' => 'save',
					'value' => 'Y',
					'disabled' => !$arParams['ALLOW_SAVE'],
				];
			}
			else
			{
				$button['NAME'] = $button['NAME'] ?: Loc::getMessage('YANDEX_MARKET_T_ADMIN_FORM_EDIT_BTN_NEXT_STEP');
				$button['ATTRIBUTES'] += [
					'class' => 'adm-btn adm-btn-save',
					'name' => 'stepAction',
					'value' => 'next',
				];
			}
		break;

		case 'save':
			$button['NAME'] = $button['NAME'] ?: $arParams['BTN_SAVE'] ?: Loc::getMessage('YANDEX_MARKET_T_ADMIN_FORM_EDIT_BTN_SAVE');
			$button['ATTRIBUTES'] += [
				'class' => 'adm-btn adm-btn-save ' . ($arParams['ALLOW_SAVE'] ? '' : 'adm-btn-disabled'),
				'name' => 'save',
				'value' => 'Y',
				'disabled' => !$arParams['ALLOW_SAVE'],
			];
		break;

		case 'apply':
			$button['NAME'] = $button['NAME'] ?: $arParams['BTN_APPLY'] ?: Loc::getMessage('YANDEX_MARKET_T_ADMIN_FORM_EDIT_BTN_APPLY');
			$button['ATTRIBUTES'] += [
				'class' => 'adm-btn ' . ($arParams['ALLOW_SAVE'] ? '' : 'adm-btn-disabled'),
				'name' => 'apply',
				'value' => 'Y',
				'disabled' => !$arParams['ALLOW_SAVE'],
			];
		break;

		case 'reset':
			$button['NAME'] = $button['NAME'] ?: Loc::getMessage('YANDEX_MARKET_T_ADMIN_FORM_EDIT_BTN_RESET');
			$button['ATTRIBUTES'] += [
				'class' => 'adm-btn',
				'type' => 'reset',
			];
		break;
	}

	$button['ATTRIBUTES'] += [
		'class' => 'adm-btn',
		'type' => 'submit',
	];

	?>
	<button <?= Attributes::stringify($button['ATTRIBUTES']) ?>><?= $button['NAME'] ?></button>
	<?php
}