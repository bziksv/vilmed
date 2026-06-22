<?php
namespace Yandex\Market\Ui\Trading;

use Bitrix\Main;
use Yandex\Market\Data\Number;
use Yandex\Market\Trading\Business;
use Yandex\Market\Utils\PhpSerializer;

class Menu
{
	const OPTION_NAME = 'menu_business';

	public static function stored()
	{
		$option = \CUserOptions::GetOption('yandex.market', self::OPTION_NAME, 'unknown');

		if ($option === 'unknown') { return []; }

		$unserialized = PhpSerializer::decode($option);

		if (!is_array($unserialized)) { return []; }

		return $unserialized;
	}

	public static function store(array $items)
	{
		\CUserOptions::SetOption('yandex.market', self::OPTION_NAME, serialize($items));
	}

	public static function extractBusinessId(Main\Request $request = null)
	{
		if ($request === null) { $request = Main\Application::getInstance()->getContext()->getRequest(); }

		return Number::castInteger($request->get('business'));
	}

	public static function compileQuery($businessId)
	{
		return [
			'business' => self::castQueryBusiness($businessId),
		];
	}

	public static function castQueryBusiness($businessId)
	{
		if ($businessId instanceof Business\Model) { $businessId = $businessId->getId(); }

		$businessId = (int)$businessId;

		return self::isKnown($businessId) ? $businessId : 0;
	}

	public static function baseQuery($businessId)
	{
		if ($businessId === null) { return []; }

		return [
			'business' => $businessId,
		];
	}

	public static function knownBusinessIds()
	{
		return array_column(array_filter(self::stored(), static function(array $business) {
			return $business['ID'] > 0;
		}), 'ID');
	}

	public static function isKnown($businessId)
	{
		$businessId = Number::castInteger($businessId);

		if ($businessId === null || $businessId <= 0) { return false; }

		return in_array($businessId, self::knownBusinessIds(), true);
	}

	public static function businessFilter($businessId, $field = 'BUSINESS_ID')
	{
		if ((string)$businessId === '') { return []; }

		$businessId = (int)$businessId;
		$knownBusinessIds = self::knownBusinessIds();

		if (empty($knownBusinessIds)) { return []; }

		if (!in_array($businessId, $knownBusinessIds, true))
		{
			return [
				'!=' . $field => $knownBusinessIds,
			];
		}

		return [ '=' . $field => $businessId ];
	}
}