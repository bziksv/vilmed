<?php
namespace Yandex\Market\Api\Business\Settings;

use Yandex\Market;

class Response extends Market\Api\Reference\ResponseWithResult
{
	public function getSettings()
	{
		return $this->requireModel('result.settings', Model\Settings::class);
	}
}