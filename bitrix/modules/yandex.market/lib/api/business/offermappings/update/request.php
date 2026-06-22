<?php
namespace Yandex\Market\Api\Business\OfferMappings\Update;

use Bitrix\Main\Web\HttpClient;
use Yandex\Market\Api;
use Yandex\Market\Reference\Assert;

/** @method Response execute() */
class Request extends Api\Partner\Reference\BusinessRequest
{
	public function getPath()
	{
		return "/businesses/{$this->getBusinessId()}/offer-mappings/update";
	}

	public function getMethod()
	{
		return HttpClient::HTTP_POST;
	}

	public function getQuery()
	{
		Assert::notNull($this->query['offerMappings'], 'offerMappings');

		return $this->query;
	}

	public function getQueryFormat()
	{
		return static::DATA_TYPE_JSON;
	}

	public function setOfferMappings(array $offerMappings)
	{
		$this->query['offerMappings'] = $offerMappings;
	}

	public function setOnlyPartnerMediaContent($onlyPartnerMediaContent)
	{
		$this->query['onlyPartnerMediaContent'] = (bool)$onlyPartnerMediaContent;
	}

	protected function validationQueue()
	{
		return (new Api\Reference\Validator\Queue())
			->add(new Api\Reference\Validator\ResponseError([ 'results' ]))
			->add(new Api\Reference\Validator\HttpError())
			->add(new Api\Reference\Validator\FormatArray());
	}

	public function buildResponse($data)
	{
		return new Response($data);
	}
}