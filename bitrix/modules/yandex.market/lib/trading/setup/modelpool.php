<?php
namespace Yandex\Market\Trading\Setup;

use Yandex\Market\Trading\Campaign;

class ModelPool
{
	protected static $pool = [];

	public static function getById($id)
	{
		$id = (int)$id;
		$cacheKey = "id:{$id}";

		if (!isset(static::$pool[$cacheKey]))
		{
			static::$pool[$cacheKey] = Model::loadById($id);
		}

		return static::$pool[$cacheKey];
	}

	public static function getByTradingInfo(array $tradingInfo)
	{
		if (!empty($tradingInfo['CAMPAIGN_ID']))
		{
			return Campaign\ModelPool::getById($tradingInfo['CAMPAIGN_ID'])->getTrading();
		}

		if (!empty($tradingInfo['SETUP_ID']))
		{
			return self::getById($tradingInfo['SETUP_ID']);
		}

		$cacheKey = static::tradingInfoCacheKey($tradingInfo);

		if (!isset(static::$pool[$cacheKey]))
		{
			static::$pool[$cacheKey] = Model::loadByTradingInfo($tradingInfo);
		}

		return static::$pool[$cacheKey];
	}

	protected static function tradingInfoCacheKey(array $tradingInfo)
	{
		return 'platform:' . (int)$tradingInfo['TRADING_PLATFORM_ID'] . ':' . $tradingInfo['SITE_ID'];
	}
}