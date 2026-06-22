<?php
namespace Yandex\Market\Catalog\Endpoint;

use Yandex\Market\Export\Param;

class Endpoint
{
	private $driver;
	private $tagBundle;
	/** @var EndpointPrimary */
	private $primary;

	public function __construct(Driver $driver, Param\TagBundle $tagBundle, $part = null)
	{
		$this->primary = new EndpointPrimary($driver->type(), $driver->campaignId(), $part);
		$this->driver = $driver;
		$this->tagBundle = $tagBundle;
	}

	/** @return EndpointPrimary */
	public function getPrimary()
	{
		return $this->primary;
	}

	public function getDriver()
	{
		return $this->driver;
	}

	public function getTagBundle()
	{
		return $this->tagBundle;
	}
}