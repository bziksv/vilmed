<?php
namespace Yandex\Market\Api\Business\OfferCards\Update;

use Bitrix\Main\Web\HttpClient;
use Yandex\Market\Api;
use Yandex\Market\Reference\Assert;

/** @method Response execute() */
class Request extends Api\Partner\Reference\BusinessRequest
{
	public function getPath()
	{
		return "/businesses/{$this->getBusinessId()}/offer-cards/update";
	}

	public function getMethod()
	{
		return HttpClient::HTTP_POST;
	}

	public function getQuery()
	{
		Assert::notNull($this->query['offersContent'], 'offersContent');

		return $this->query;
	}

	public function getQueryFormat()
	{
		return static::DATA_TYPE_JSON;
	}

	public function setOffersContent(array $offersContent)
	{
		$this->query['offersContent'] = $offersContent;
	}

    public function buildResponse($data)
    {
        return new Response($data);
    }

	protected function validationQueue()
	{
		return (new Api\Reference\Validator\Queue())
			->add(new Api\Reference\Validator\ResponseError([ 'results' ]))
			->add(new Api\Reference\Validator\HttpError())
			->add(new Api\Reference\Validator\FormatArray());
	}
}