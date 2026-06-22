<?php
namespace Yandex\Market\Catalog\Endpoint;

use Yandex\Market\Data\Type\CanonicalDateTime;

interface DriverWithExpiryDate extends Driver
{
	/** @return CanonicalDateTime */
	public function expiryDate();
}