<?php
namespace Yandex\Market\Trading\Campaign;

use Yandex\Market\Trading\Service;

class Placement
{
	const FBS = 'FBS';
	const FBY = 'FBY';
	const DBS = 'DBS';

	public static function all()
	{
		return [
			self::FBS,
			self::FBY,
			self::DBS,
		];
	}

	public static function toBehavior($placement)
	{
		if ($placement === self::DBS)
		{
			return Service\Manager::BEHAVIOR_DBS;
		}

		if ($placement === self::FBS || $placement === self::FBY)
		{
			return Service\Manager::BEHAVIOR_FBS;
		}

		return null;
	}

	public static function toPlacement($tradingBehavior)
	{
		if ($tradingBehavior === Service\Manager::BEHAVIOR_DBS)
		{
			return self::DBS;
		}

		return self::FBS;
	}
}