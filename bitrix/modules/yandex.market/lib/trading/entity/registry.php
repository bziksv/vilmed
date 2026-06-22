<?php
namespace Yandex\Market\Trading\Entity;

use Yandex\Market\Trading\Glossary;

class Registry
{
	const ENTITY_TYPE_PRICE = 'price';
	const ENTITY_TYPE_STOCKS = 'stocks';

	const ENTITY_TYPE_LOGISTIC_SHIPMENT = 'logisticShipment';
	const ENTITY_TYPE_OUTLET = 'outlet';

	const ENTITY_TYPE_ORDER = Glossary::ENTITY_ORDER;
	const ENTITY_TYPE_SHIPMENT = 'shipment';
	const ENTITY_TYPE_BOX = 'box';
	const ENTITY_TYPE_NONE = 'none';
}