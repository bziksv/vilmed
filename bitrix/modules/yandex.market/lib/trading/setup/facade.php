<?php
namespace Yandex\Market\Trading\Setup;

class Facade
{
	public static function hasActiveSetupUsingServiceCode($serviceCode, $except = null)
	{
		return (bool)Table::getRow([
			'select' => [ 'ID' ],
			'filter' => [
				'=ACTIVE' => Table::BOOLEAN_Y,
				'=TRADING_SERVICE' => $serviceCode,
				'!=ID' => $except,
			],
		]);
	}
}