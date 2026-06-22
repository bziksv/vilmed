<?php
namespace Yandex\Market\Api\Model;

use Yandex\Market\Api;
use Yandex\Market\Trading\Service\Common\Options;
use Yandex\Market\Psr\Log\LoggerInterface;

class OrderFacade
{
	public static function loadList(Options $options, array $parameters = null, LoggerInterface $logger = null)
	{
		$request = static::createLoadListRequest($options);
		$request->setLogger($logger);

		if ($parameters !== null)
		{
			$request->processParameters($parameters);
		}

		return $request->execute()->getOrderCollection();
	}

	protected static function createLoadListRequest(Options $options)
	{
		return new Api\Partner\Orders\Request($options->getCampaignId(), $options);
	}

	public static function load(Options $options, $orderId, LoggerInterface $logger = null)
	{
		$request = static::createLoadRequest($options);
		$request->setLogger($logger);
		$request->setOrderId($orderId);

		return $request->execute()->getOrder();
	}

	protected static function createLoadRequest(Options $options)
	{
		return new Api\Partner\Order\Request($options->getCampaignId(), $options);
	}

	public static function submitStatus(Options $options, $orderId, $status, $subStatus = null, LoggerInterface $logger = null, array $payload = [])
	{
		$request = static::createSubmitStatusRequest($options);
		$request->setLogger($logger);
		$request->setOrderId($orderId);
		$request->setStatus($status);
		$request->setSubStatus($subStatus);
		$request->setPayload($payload);

		return $request->execute()->getOrder();
	}

	protected static function createSubmitStatusRequest(Options $options)
	{
		return new Api\Partner\SendStatus\Request($options->getCampaignId(), $options);
	}
}