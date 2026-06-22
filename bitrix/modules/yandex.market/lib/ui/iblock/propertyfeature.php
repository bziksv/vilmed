<?php
namespace Yandex\Market\Ui\Iblock;

use Yandex\Market\Reference\Event;
use Yandex\Market\Reference\Concerns;
use Yandex\Market\Config;
use Bitrix\Main;
use Bitrix\Iblock;

class PropertyFeature extends Event\Regular
{
	use Concerns\HasMessage;

	const FEATURE_ID = 'YAMARKET_COMMON';
	/** @deprecated  */
	const FEATURE_ID_PREFIX = 'YAMARKET_';

	public static function getHandlers()
	{
		if ((int)Main\ModuleManager::getVersion('main') < 16) { return []; }

		return [
			[
				'module' => 'iblock',
				'event' => Iblock\Model\PropertyFeature::class . '::OnPropertyFeatureBuildList',
				'method' => 'onPropertyFeatureBuildList',
			],
		];
	}

	/** @noinspection PhpUnused */
	public static function onPropertyFeatureBuildList()
	{
		return new Main\EventResult(Main\EventResult::SUCCESS, [
			[
				'MODULE_ID' => Config::getModuleName(),
				'FEATURE_ID' => self::FEATURE_ID,
				'FEATURE_NAME' => self::getTitle(),
			],
		]);
	}

	public static function getTitle($version = null)
	{
		$suffix = $version !== null ? "_{$version}" : '';

		return self::getMessage('TITLE' . $suffix);
	}
}