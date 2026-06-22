<?php
namespace Yandex\Market\Catalog\Endpoint;

use Bitrix\Main;
use Yandex\Market\Catalog;

class Registry
{
	/** @return Driver */
	public static function restore($type, $businessId, $campaignId)
	{
		if ($type === Catalog\Glossary::ENDPOINT_PRICE)
		{
			return $campaignId > 0
                ? new PriceCampaign($campaignId)
                : new PriceBusiness($businessId);
		}

		if ($type === Catalog\Glossary::ENDPOINT_STOCKS)
		{
			return new Stocks($campaignId);
		}

		if ($type === Catalog\Glossary::ENDPOINT_OFFER)
		{
			return new Offer($businessId);
		}

		if ($type === Catalog\Glossary::ENDPOINT_TERMS)
		{
			return new Terms($campaignId);
		}

        if ($type === Catalog\Glossary::ENDPOINT_ARCHIVE)
        {
            return $campaignId > 0
                ? new Hide($campaignId)
                : new Archive($businessId);
        }

        if ($type === Catalog\Glossary::ENDPOINT_CARD)
        {
            return new Card($businessId);
        }

		throw new Main\ArgumentException(sprintf('unknown endpoint %s', $type));
	}
}