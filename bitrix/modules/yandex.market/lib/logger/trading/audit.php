<?php
namespace Yandex\Market\Logger\Trading;

use Yandex\Market\Glossary;
use Yandex\Market\Reference\Concerns;

class Audit
{
	use Concerns\HasMessage;

	const INCOMING_REQUEST = 'incoming_request';
	const INCOMING_RESPONSE = 'incoming_response';
	const OUTGOING_REQUEST = 'outgoing_request';
	const OUTGOING_RESPONSE = 'outgoing_response';
	const CART = 'cart';
	const ORDER_ACCEPT = 'order_accept';
	const ORDER_STATUS = 'order_status';
	const SEND_STATUS = 'send_status';
	const SEND_BOXES = 'send_boxes';
	/** @deprecated */
	const SEND_ITEMS = 'send_items';
	/** @deprecated */
	const SEND_CIS = 'send_cis';
	const SEND_TRACK = 'send_track';
	const SEND_CANCELLATION_ACCEPT = 'send_cancellation_accept';
	const SEND_DELIVERY_DATE = 'send_delivery_date';
	const SEND_DELIVERY_STORAGE_LIMIT = 'send_delivery_storage_limit';
	const SEND_SHIPMENT_CONFIRM = 'send_shipment_confirm';
	const SEND_SHIPMENT_EXCLUDE_ORDERS = 'send_shipment_exclude';
	const SALES_BOOST = 'boost';
	const PROCEDURE = 'procedure';
	const AGENT = 'agent';
	const INTERNAL = 'internal';

	const CATALOG_OFFER = 'catalog_offer';
	const CATALOG_PRICE = 'catalog_price';
	const CATALOG_STOCKS = 'catalog_stocks';
	const CATALOG_CARD = 'catalog_card';
	const CATALOG_TERMS = 'catalog_terms';
	const CATALOG_ARCHIVE = 'catalog_archive';

	public static function getVariants()
	{
		return [
			Glossary::SERVICE_CATALOG => [
				static::CATALOG_OFFER,
				static::CATALOG_PRICE,
				static::CATALOG_STOCKS,
				static::CATALOG_CARD,
				static::CATALOG_TERMS,
				static::CATALOG_ARCHIVE,
				static::AGENT,
			],
			Glossary::SERVICE_TRADING => [
				static::CART,
				static::ORDER_ACCEPT,
				static::ORDER_STATUS,
				static::SEND_STATUS,
				static::SEND_BOXES,
				static::SEND_TRACK,
				static::SEND_CANCELLATION_ACCEPT,
				static::SEND_DELIVERY_DATE,
				static::SEND_DELIVERY_STORAGE_LIMIT,
				static::SEND_SHIPMENT_CONFIRM,
				static::SEND_SHIPMENT_EXCLUDE_ORDERS,
				static::INCOMING_REQUEST,
				static::INCOMING_RESPONSE,
				static::OUTGOING_REQUEST,
				static::OUTGOING_RESPONSE,
				static::PROCEDURE,
			],
			Glossary::SERVICE_SALES_BOOST => [
				static::SALES_BOOST,
			],
		];
	}

	public static function getTitle($variant)
	{
		return self::getMessage(mb_strtoupper($variant), null, $variant);
	}

	public static function getGroupTitle($group)
	{
		return self::getMessage('GROUP_' . mb_strtoupper($group), null, $group);
	}
}