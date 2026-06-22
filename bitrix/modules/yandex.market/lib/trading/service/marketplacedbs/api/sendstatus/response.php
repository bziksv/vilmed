<?php
namespace Yandex\Market\Trading\Service\MarketplaceDbs\Api\SendStatus;

use Yandex\Market;
use Yandex\Market\Trading\Service as TradingService;

class Response extends Market\Api\Partner\SendStatus\Response
{
    public function getOrder()
    {
        return $this->requireModel('order', TradingService\MarketplaceDbs\Model\Order::class);
    }
}