<?php
namespace Yandex\Market\Catalog\Endpoint;

use Yandex\Market\Catalog\Glossary;
use Yandex\Market\Catalog\Run\Storage\PlacementTable;

class PriorityDictionary
{
    const ARCHIVE = 10;
    const HIDE = 10;
    const STOCKS_PUBLISHED = 20;
    const PRICE_PUBLISHED = 20;
    const OFFER_PUBLISHED = 30;
    const CARD_PUBLISHED = 40;
    const OFFER_NEW = 50;
    const STOCKS_NEW = 70;
    const PRICE_NEW = 70;
	const CARD_NEW = 80;
    const UNARCHIVE = 90;
	const UNHIDE = 91;

    const MODIFIER = 5;

    public static function wasPublished($placementStatus)
    {
        return ($placementStatus === PlacementTable::STATUS_PUBLISHED);
    }

    public static function isOutOfStock(array $submitted = null)
    {
        return (isset($submitted[Glossary::ENDPOINT_STOCKS]['count']) && (int)$submitted[Glossary::ENDPOINT_STOCKS]['count'] <= 0);
    }

    public static function willShip(array $prepared = null, array $submitted = null)
    {
        if (!self::isOutOfStock($submitted)) { return false; }

        return (isset($prepared[Glossary::ENDPOINT_STOCKS]['count']) && (int)$prepared[Glossary::ENDPOINT_STOCKS]['count'] > 0);
    }

    public static function willOutOfStock(array $prepared = null, array $submitted = null)
    {
        if (self::isOutOfStock($submitted)) { return false; }

        return (isset($prepared[Glossary::ENDPOINT_STOCKS]['count']) && (int)$prepared[Glossary::ENDPOINT_STOCKS]['count'] <= 0);
    }

    public static function willMarkUp(array $prepared = null, array $submitted = null)
    {
        return (
            isset($submitted[Glossary::ENDPOINT_PRICE]['price'], $prepared[Glossary::ENDPOINT_PRICE]['price'])
            && (int)$submitted[Glossary::ENDPOINT_PRICE]['price'] < (int)$prepared[Glossary::ENDPOINT_PRICE]['price']
        );
    }

    public static function willDiscount(array $prepared = null, array $submitted = null)
    {
        return (
            isset($submitted[Glossary::ENDPOINT_PRICE]['price'], $prepared[Glossary::ENDPOINT_PRICE]['price'])
            && (int)$submitted[Glossary::ENDPOINT_PRICE]['price'] > (int)$prepared[Glossary::ENDPOINT_PRICE]['price']
        );
    }
}