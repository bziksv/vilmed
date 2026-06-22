<?php
namespace Yandex\Market\Ui\UserField;

use Yandex\Market\Trading\Facade;

/** @noinspection PhpUnused */
class TradingBusinessType extends ReferenceType
{
	protected static function fetchList($dataClass, array $userField)
	{
		$values = parent::fetchList($dataClass, $userField);

		if (!empty($values)) { return $values; }

		$changed = Facade\Business::synchronize();

		if (!$changed) { return []; }

		return parent::fetchList($dataClass, $userField);
	}

	protected static function fetchFilter(array $userField)
	{
		return [
			'=ACTIVE' => true,
		];
	}
}