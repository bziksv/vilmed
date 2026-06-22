<?php
namespace Yandex\Market\Trading\Setup;

use Yandex\Market\Trading\Business;
use Yandex\Market\Trading\Entity\Reference\Environment;

abstract class TradingContext
{
	/** @var Business\Model */
	private $business;
	/** @var Environment */
	private $environment;
	/** @var string */
	private $siteId;
	/** @var int|null */
	private $setupId;

	public function __construct(
		Business\Model $business,
		Environment $environment,
		$siteId,
		$setupId = null
	)
	{
		$this->business = $business;
		$this->environment = $environment;
		$this->setupId = $setupId !== null ? (int)$setupId : null;
		$this->siteId = (string)$siteId;
	}

	public function getEnvironment()
	{
		return $this->environment;
	}

	public function getBusiness()
	{
		return $this->business;
	}

	public function getSiteId()
	{
		return $this->siteId;
	}

	public function getSetupId()
	{
		return $this->setupId;
	}
}