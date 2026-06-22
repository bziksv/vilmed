<?php
namespace Yandex\Market\Catalog\Segment;

use Yandex\Market\Export\Param;

class BusinessConfig
{
	private $format;

	public function __construct(Param\Format $format)
	{
		$this->format = $format;
	}

	public function format()
	{
		return $this->format;
	}
}