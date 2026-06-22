<?php
namespace Yandex\Market\Api\Reports\Documents\Labels\Generate;

use Bitrix\Main\Web\HttpClient;
use Yandex\Market\Api\Partner\Reference\BusinessRequest;
use Yandex\Market\Api\Reports\GenerateResponse;

/** @method GenerateResponse execute() */
class Request extends BusinessRequest
{
	/** @noinspection PhpUnused */
	const SORT_BY_GIVEN_ORDER = 'SORT_BY_GIVEN_ORDER';
	/** @noinspection PhpUnused */
	const SORT_BY_ORDER_CREATED_AT = 'SORT_BY_ORDER_CREATED_AT';

	public function getPath()
	{
		return '/reports/documents/labels/generate';
	}

	public function getMethod()
	{
		return HttpClient::HTTP_POST;
	}

	public function getQueryFormat()
	{
		return static::DATA_TYPE_JSON;
	}

	public function getQuery()
	{
		$result = [
			'businessId' => (int)$this->getBusinessId(),
		];
		$result += parent::getQuery();

		return $result;
	}

	public function setFormat($format)
	{
		$this->urlQuery['format'] = (string)$format;
	}

	public function setOrderIds(array $ids)
	{
		$this->query['orderIds'] = array_map(
			static function($id) { return (int)$id; },
			$ids
		);
	}

	/** @noinspection PhpUnused */
	public function setSortingType($sortingType)
	{
		$this->query['sortingType'] = $sortingType;
	}

	public function buildResponse($data)
	{
		return new GenerateResponse($data);
	}
}