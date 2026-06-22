<?php

namespace Yandex\Market\Trading\Entity\Reference;

use Yandex\Market;
use Yandex\Market\Trading\Business;
use Bitrix\Main;

abstract class Platform
{
	const XML_ID_CAMPAIGN_PREFIX = 'C';

	protected $environment;
	protected $businessId;
	protected $setupId;
	protected $groupSetupIds;

	public function __construct(Environment $environment, $businessId)
	{
		$this->environment = $environment;
		$this->businessId = $businessId;
	}

	public function setSetupId($setupId)
	{
		$this->setupId = $setupId !== null ? (int)$setupId : null;
	}

	/** @return int|null */
	public function getSetupId()
	{
		return $this->setupId;
	}

	public function setGroupSetupIds(array $setupIds = null)
	{
		$this->groupSetupIds = $setupIds;
	}

	/** @return array|null */
	public function getGroupSetupIds()
	{
		return $this->groupSetupIds;
	}

	/**
	 * @return int|string|null
	 */
	public function getId()
	{
		throw new Market\Exceptions\NotImplementedMethod(static::class, 'getId');
	}

	/**
	 * @return bool
	 */
	public function isInstalled()
	{
		throw new Market\Exceptions\NotImplementedMethod(static::class, 'isInstalled');
	}

	/** @return int */
	public function install(Business\Model $business)
	{
		throw new Market\Exceptions\NotImplementedMethod(static::class, 'install');
	}

	/**
	 * @return Main\Result
	 */
	public function uninstall()
	{
		throw new Market\Exceptions\NotImplementedMethod(static::class, 'uninstall');
	}

	public function migrate($platformId, Business\Model $business)
	{
		throw new Market\Exceptions\NotImplementedMethod(static::class, 'migrate');
	}

	/**
	 * @return bool
	 */
	public function isActive()
	{
		throw new Market\Exceptions\NotImplementedMethod(static::class, 'isActive');
	}

	/**
	 * @return Main\Result
	 */
	public function activate()
	{
		throw new Market\Exceptions\NotImplementedMethod(static::class, 'activate');
	}

	/**
	 * @return Main\Result
	 */
	public function deactivate()
	{
		throw new Market\Exceptions\NotImplementedMethod(static::class, 'deactivate');
	}

	/**
	 * @param string $xmlId
	 *
	 * @return array{CAMPAIGN_ID: int, SETUP_ID: int|null, ORDER_ID: string|null}|null
	 */
	public static function parseOrderXmlId($xmlId)
	{
		$pattern = sprintf('/^YAMARKET_(%s?\d+)_([^_]+)(?:_(\d+))?$/', self::XML_ID_CAMPAIGN_PREFIX);

		if (!preg_match($pattern, $xmlId, $matches)) { return null; }

		$common = [
			'ORDER_ID' => $matches[2] !== 'CART' ? $matches[2] : null,
			'SETUP_ID' => isset($matches[3]) ? (int)$matches[3] : null,
		];

		// compatability
		if (mb_strpos($matches[1], self::XML_ID_CAMPAIGN_PREFIX) !== 0)
		{
			return $common + [
				'PLATFORM_ID' => (int)$matches[1], // old behavior
				'TRADING_PLATFORM_ID' => (int)$matches[1],
			];
		}

		return $common + [
			'CAMPAIGN_ID' => (int)mb_substr($matches[1], 1),
		];
	}

	/**
	 * @param string|int $orderId
	 * @param int|null $campaignId
	 *
	 * @return string
	 */
	public function getOrderXmlId($orderId, $campaignId)
	{
		if ($orderId === null)
		{
			$orderId = 'CART';
		}

		if ($campaignId !== null)
		{
			$campaignPlace = self::XML_ID_CAMPAIGN_PREFIX . $campaignId;
		}
		else
		{
			$campaignPlace = $this->getId();
		}

		return 'YAMARKET_' . $campaignPlace . '_' . $orderId . '_' . $this->getSetupId();
	}

	/** @deprecated */
	public function getOrderXmlIdSuffix($setupId)
	{
		return '_' . $setupId;
	}
}