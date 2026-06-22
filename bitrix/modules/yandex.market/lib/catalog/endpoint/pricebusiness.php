<?php
namespace Yandex\Market\Catalog\Endpoint;

use Yandex\Market\Api\Reference\Auth;
use Yandex\Market\Catalog;
use Yandex\Market\Config;
use Yandex\Market\Logger\Trading\Audit;
use Yandex\Market\Reference\Concerns;
use Yandex\Market\Data;
use Yandex\Market\Result;
use Yandex\Market\Trading\Service\Marketplace\Api as MarketApi;
use Yandex\Market\Psr\Log\LoggerInterface;
use Yandex\Market\Trading\State\PushAgent;

class PriceBusiness implements Driver, DriverWithExpiryDate
{
	use Concerns\HasMessage;

	private $businessId;

	public function __construct($businessId)
	{
		$this->businessId = (int)$businessId;
	}

	public function type()
	{
		return Catalog\Glossary::ENDPOINT_PRICE;
	}

	public function campaignId()
	{
		return 0;
	}

	public function audit()
	{
		return Audit::CATALOG_PRICE;
	}

	public function expiryDate()
	{
		$expire = new Data\Type\CanonicalDateTime();
		$expire->add(sprintf(
			'-P%sD',
			max(1, (int)Config::getOption(PushAgent::optionName('expire_days'), 1))
		));

		return $expire;
	}

	public function priority($placementStatus, array $prepared, array $submitted = null)
	{
        if (!PriorityDictionary::wasPublished($placementStatus))
        {
            return PriorityDictionary::PRICE_NEW;
        }

        if (PriorityDictionary::willMarkUp($prepared, $submitted) && !PriorityDictionary::willOutOfStock($prepared, $submitted))
        {
            return PriorityDictionary::PRICE_PUBLISHED - PriorityDictionary::MODIFIER;
        }

		return PriorityDictionary::PRICE_PUBLISHED;
	}

	public function limit()
	{
		return 500;
	}

	public function submit(array $payloadBag, Auth $auth, LoggerInterface $logger)
	{
		$offers = $this->compileOffers($payloadBag);

		$request = new MarketApi\SendPrices\Business\Request($this->businessId, $auth, $logger);
		$request->setOffers($offers);
		$request->execute(); // todo parse item bad_request

		$this->logSubmitted($offers, $logger);

		return array_fill_keys(array_keys($payloadBag), new Result\Base());
	}

	private function compileOffers(array $bag)
	{
		$result = [];

		foreach ($bag as $sku => $data)
		{
            $sanitized = [
                'value' => (int)$data['basicPrice'],
                'currencyId' => (string)$data['currencyId'],
            ];

            if (isset($data['discountBase']))
            {
                $sanitized['discountBase'] = (int)$data['discountBase'];
            }

			$result[] = [
				'offerId' => (string)$sku,
				'price' => $sanitized,
			];
		}

		return $result;
	}

	private function logSubmitted(array $offers, LoggerInterface $logger)
	{
		foreach ($offers as $offer)
		{
			$logger->info(self::getMessage('SUBMITTED', [
				'#PRICE#' => isset($offer['price']['discountBase'])
					? "{$offer['price']['value']} {$offer['price']['currencyId']} ({$offer['price']['discountBase']} {$offer['price']['currencyId']})"
					: "{$offer['price']['value']} {$offer['price']['currencyId']}",
			]), [
				'ENTITY_TYPE' => Catalog\Glossary::ENTITY_SKU,
				'ENTITY_ID' => $offer['offerId'],
			]);
		}
	}
}