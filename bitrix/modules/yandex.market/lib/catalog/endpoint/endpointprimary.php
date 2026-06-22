<?php
namespace Yandex\Market\Catalog\Endpoint;

use Yandex\Market\Catalog\Run\Storage\HashTable;

class EndpointPrimary
{
	/** @var string */
	private $type;
	/** @var int */
	private $campaignId;
	/** @var string|null */
	private $part;

	public function __construct($type, $campaignId = 0, $part = null)
	{
		$this->type = $type;
		$this->campaignId = (int)$campaignId;
		$this->part = $part;
	}

	public function getType()
	{
		return $this->type;
	}

	public function getCampaignId()
	{
		return $this->campaignId;
	}

	public function getPart()
	{
		return $this->part !== null ? $this->part : HashTable::PART_COMMON;
	}

	public function __toString()
	{
		$partials = [
			$this->type,
			$this->campaignId,
			$this->getPart(),
		];

		if ($partials[2] !== HashTable::PART_COMMON) { return implode(':', $partials); }

		array_pop($partials);

		if ($partials[1] > 0) { return implode(':', $partials); }

		array_pop($partials);

		return implode(':', $partials);
	}
}