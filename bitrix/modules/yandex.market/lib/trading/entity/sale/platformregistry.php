<?php
namespace Yandex\Market\Trading\Entity\Sale;

use Yandex\Market\Trading\Entity\Reference;

class PlatformRegistry extends Reference\PlatformRegistry
{
	protected function createPlatform($businessId)
	{
		return new Platform($this->environment, $businessId);
	}
}