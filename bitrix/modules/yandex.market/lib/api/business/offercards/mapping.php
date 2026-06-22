<?php
namespace Yandex\Market\Api\Business\OfferCards;

use Yandex\Market\Api\Reference\Model;

class Mapping extends Model
{
	public function getMarketSkuName()
	{
		return (string)$this->getField('marketSkuName');
	}
}