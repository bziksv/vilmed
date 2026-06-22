<?php
namespace Yandex\Market\Trading\Entity\Reference;

use Yandex\Market\Exceptions;

abstract class PlatformRegistry
{
	protected $environment;
	protected $platforms = [];

	public function __construct(Environment $environment)
	{
		$this->environment = $environment;
	}

	/** @return Platform */
	public function getPlatform($businessId)
	{
		$businessId = (int)$businessId;

		if (!isset($this->platforms[$businessId]))
		{
			$this->platforms[$businessId] = $this->createPlatform($businessId);
		}

		return $this->platforms[$businessId];
	}

	/** @return Platform */
	protected function createPlatform($businessId)
	{
		throw new Exceptions\NotImplementedEntity(static::class, 'Platform');
	}
}