<?php
namespace Yandex\Market\Logger\Trading;

use Yandex\Market;

class Logger extends Market\Logger\Reference\Logger
{
	private static $tracingOn;

	protected $additionalGroupKeys = [
		'AUDIT',
		'CAMPAIGN_ID',
	];
	private $setupType;
	private $setupId;

	public function __construct($setupType = Market\Glossary::SERVICE_TRADING, $setupId = 0)
	{
		parent::__construct();

		$this->setupType = $setupType;
		$this->setupId = (int)$setupId;
	}

	public function getDataClass()
	{
		return Table::class;
	}

	/** @noinspection PhpUnused */
	public function getSetupType()
	{
		return $this->setupType;
	}

	public function getSetupId()
	{
		return $this->setupId;
	}

	/**
	 * @deprecated
	 * @noinspection PhpUnused
	 */
	public function setEntityParent($parentId)
	{
		$this->setupId = $parentId;
	}

	/** @deprecated */
	public function getEntityParent()
	{
		return $this->setupId;
	}

	protected function getRowDefaults()
	{
		return [
			'SETUP_TYPE' => $this->setupType,
			'SETUP_ID' => $this->setupId,
			'BUSINESS_ID' => 0,
			'CAMPAIGN_ID' => 0,
		];
	}

	protected function existsCommonFilter(array $rows)
	{
		$campaignId = $this->getContext('CAMPAIGN_ID');
		$audit = $this->getContext('AUDIT');
		$filter = [
			'=SETUP_TYPE' => $this->setupType,
			'=SETUP_ID' => $this->setupId,
		];

		if ($audit !== null)
		{
			$filter['=AUDIT'] = $audit;
		}

		if ($campaignId !== null)
		{
			$filter['=BUSINESS_ID'] = $this->getContext('BUSINESS_ID');
			$filter['=CAMPAIGN_ID'] = $campaignId;
		}

		return $filter;
	}

	protected function getContextFields()
	{
		return [
			'ENTITY_TYPE',
			'ENTITY_ID',
			'AUDIT',
			'URL',
			'TRACE',
			'BUSINESS_ID',
			'CAMPAIGN_ID',
		];
	}

	protected function isTracingOn()
	{
		if (self::$tracingOn === null)
		{
			self::$tracingOn = (Market\Config::getOption('trading_log_tracing', 'Y') !== 'N');
		}

		return self::$tracingOn;
	}
}