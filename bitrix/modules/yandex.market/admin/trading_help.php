<?php

use Bitrix\Main;
use Bitrix\Main\Localization\Loc;
use Yandex\Market;

/** @var CMain $APPLICATION */

require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_admin_before.php';

try
{
	if (!Main\Loader::includeModule('yandex.market'))
	{
		throw new Main\SystemException('Module yandex.market required');
	}

	$business = Market\Trading\Business\Table::getRow([
		'select' => [ 'ID' ],
		'filter' => [ '=ACTIVE' => Market\Trading\Business\Table::BOOLEAN_Y ],
		'order' => [ 'ID' => 'ASC' ],
	]);

	$url = $business !== null
		? sprintf('https://partner.market.yandex.ru/business/%s/support', (int)$business['ID'])
		: 'https://marketplace.1c-bitrix.ru/solutions/yandex.market/#tab-support-link';

	LocalRedirect($url, true);
}
catch (Main\SystemException $exception)
{
	\CAdminMessage::ShowMessage([
		'TYPE' => 'ERROR',
		'MESSAGE' => $exception->getMessage(),
	]);
}

require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/epilog_admin_after.php';
