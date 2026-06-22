<?php
namespace Yandex\Market\Trading\Service\Marketplace\Action\SendOrderBoxes;

use Yandex\Market\Trading\Service as TradingService;

class Request extends TradingService\Common\Action\SendRequest
{
	public function getBoxes()
	{
		return $this->getField('boxes');
	}

	public function getItems()
	{
		return $this->getField('items');
	}

	public function isAllowRemove()
	{
		return (bool)$this->getField('allowRemove');
	}
}