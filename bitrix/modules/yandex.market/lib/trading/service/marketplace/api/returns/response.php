<?php
namespace Yandex\Market\Trading\Service\Marketplace\Api\Returns;

use Yandex\Market;
use Yandex\Market\Trading\Service as TradingService;

class Response extends Market\Api\Reference\ResponseWithResult
{
	use Market\Reference\Concerns\HasOnce;

	/** @return TradingService\Marketplace\Model\ReturnCollection */
	public function getReturnCollection()
	{
		return $this->once('loadReturnCollection');
	}

	/** @noinspection PhpUnused */
	protected function loadReturnCollection()
	{
		$serviceResult = $this->getResult();

		Market\Reference\Assert::notNull($serviceResult['returns'], 'response["result"]["returns"]');
		Market\Reference\Assert::isArray($serviceResult['returns'], 'response["result"]["returns"]');

		$collection = TradingService\Marketplace\Model\ReturnCollection::initialize($serviceResult['returns']);
		$collection->setPaging($this->getPaging());

		return $collection;
	}

	/** @return Market\Api\Model\Paging */
	public function getPaging()
	{
		return $this->once('loadPaging');
	}

	/** @noinspection PhpUnused */
	protected function loadPaging()
	{
		$serviceResult = $this->getResult();
		$data = (array)$serviceResult['paging'];

		return new Market\Api\Model\Paging($data);
	}

	protected function getResult()
	{
		$serviceResult = $this->getField('result');

		Market\Reference\Assert::notNull($serviceResult, 'response["result"]');
		Market\Reference\Assert::isArray($serviceResult, 'response["result"]');

		return $serviceResult;
	}
}
