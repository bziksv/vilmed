<?php
namespace Yandex\Market\Catalog\Segment;

use Bitrix\Main;
use Yandex\Market\Catalog;

class Registry
{
	/**
	 * @param string $type
	 * @return Factory
	 */
	public static function factory($type)
	{
		if ($type === Catalog\Glossary::SEGMENT_PRICE)
		{
			return new Price\Factory();
		}

		if ($type === Catalog\Glossary::SEGMENT_STOCKS)
		{
			return new Stocks\Factory();
		}

		if ($type === Catalog\Glossary::SEGMENT_OFFER)
		{
			return new Offer\Factory();
		}

		if ($type === Catalog\Glossary::SEGMENT_CARD)
		{
			return new Catalog\Card\SegmentFactory();
		}

		throw new Main\ArgumentException('unknown segment %s', $type);
	}
}