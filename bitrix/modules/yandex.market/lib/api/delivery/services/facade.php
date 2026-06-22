<?php
namespace Yandex\Market\Api\Delivery\Services;

use Yandex\Market\Psr\Log\LoggerInterface;

/** @deprecated */
class Facade
{
	public static function load($auth, LoggerInterface $logger = null)
	{
		return (new Request($auth, $logger))->execute()->getDeliveryServices();
	}
}