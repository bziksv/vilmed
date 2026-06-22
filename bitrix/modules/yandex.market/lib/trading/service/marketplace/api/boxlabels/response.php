<?php
namespace Yandex\Market\Trading\Service\Marketplace\Api\BoxLabels;

use Yandex\Market;

class Response extends Market\Api\Reference\ResponseWithResult
{
	public function getResult()
	{
		return $this->requireModel('result', ResponseResult::class);
	}
}