<?php
namespace Yandex\Market\Catalog\Endpoint;

use Yandex\Market\Api;
use Yandex\Market\Api\Reference\Auth;
use Yandex\Market\Config;
use Yandex\Market\Error;
use Yandex\Market\Catalog;
use Yandex\Market\Logger\Trading\Audit;
use Yandex\Market\Reference\Concerns;
use Yandex\Market\Result;
use Yandex\Market\Psr\Log\LoggerInterface;

class Offer implements Driver
{
	use Concerns\HasMessage;

	private $businessId;

	public function __construct($businessId)
	{
		$this->businessId = (int)$businessId;
	}

	public function type()
	{
		return Catalog\Glossary::ENDPOINT_OFFER;
	}

	public function campaignId()
	{
		return 0;
	}

	public function audit()
	{
		return Audit::CATALOG_OFFER;
	}

	public function priority($placementStatus, array $prepared, array $submitted = null)
	{
        if (!PriorityDictionary::wasPublished($placementStatus))
        {
            return PriorityDictionary::OFFER_NEW;
        }

		return PriorityDictionary::OFFER_PUBLISHED;
	}

	public function limit()
	{
		return max(1, Config::getOption('catalog_endpoint_offer_limit', 50)); // todo The request is too big
	}

	public function submit(array $payloadBag, Auth $auth, LoggerInterface $logger)
	{
        list($offers, $root) = $this->compileOfferMappings($payloadBag);

		$skus = array_keys($payloadBag);
		$submitResults = array_combine($skus, array_map(static function() { return new Result\Base(); }, $skus));

		$request = new Api\Business\OfferMappings\Update\Request($this->businessId, $auth, $logger);
		$request->setOfferMappings($offers);
        $this->passRequestRoot($request, $root);

		$response = $request->execute();

		$errorOfferIds = $this->fillResponseResult($submitResults, $response->getResults());
		$repeatOffers = $this->sliceErrorOffers($offers, $errorOfferIds);

		if (!empty($repeatOffers))
		{
			$request->setOfferMappings($repeatOffers);
			$response = $request->execute();

			$this->fillResponseResult($submitResults, $response->getResults());
		}

        $this->fillSubmitData($submitResults, $payloadBag, $logger);

		return $submitResults;
	}

    private function passRequestRoot(Api\Business\OfferMappings\Update\Request $request, array $root = null)
    {
        if (isset($root['onlyPartnerMediaContent']))
        {
            $request->setOnlyPartnerMediaContent($root['onlyPartnerMediaContent']);
        }
    }

	private function compileOfferMappings(array $bag)
	{
		$offers = [];
        $root = null;

		foreach ($bag as $sku => $data)
		{
            list($offer, $mapping, $root) = $this->splitData($data);

            $payload = [
                'offer' => [ 'offerId' => (string)$sku ] + $this->sanitize($offer),
            ];

            if (!empty($mapping))
            {
                $payload['mapping'] = $mapping;
            }

			$offers[] = $payload;
		}

		return [$offers, $root];
	}

    private function splitData(array $data)
    {
        $root = array_intersect_key($data, [
            'onlyPartnerMediaContent' => true,
        ]);
        $mapping = array_intersect_key($data, [
            'marketSku' => true,
        ]);
        $offer = array_diff_key($data, $root, $mapping);

        return [ $offer, $mapping, $root ];
    }

    private function sanitize(array $data)
    {
        $data = $this->sanitizePrices($data);
        $data = $this->sanitizeDiscount($data);
        $data = $this->sanitizeCurrency($data);

        return $data;
    }

    private function sanitizePrices(array $data)
    {
        $keys = [ 'basicPrice', 'purchasePrice', 'additionalExpenses', 'cofinancePrice' ];

        foreach ($keys as $key)
        {
            if (!isset($data[$key])) { continue; }

            $data[$key] = [
                'value' => $data[$key],
                'currencyId' => $data['currencyId'],
            ];
        }

        return $data;
    }

    private function sanitizeDiscount(array $data)
    {
        if (!isset($data['discountBase'])) { return $data; }

        if (isset($data['basicPrice']))
        {
            $data['basicPrice']['discountBase'] = $data['discountBase'];
        }

        unset($data['discountBase']);

        return $data;
    }

    private function sanitizeCurrency(array $data)
    {
        if (isset($data['currencyId']))
        {
            unset($data['currencyId']);
        }

        return $data;
    }

	private function sliceErrorOffers(array $offers, array $errorOfferIds)
	{
		if (empty($errorOfferIds)) { return null; }

		$errorOfferMap = array_flip($errorOfferIds);

		return array_values(array_filter($offers, static function(array $offer) use ($errorOfferMap) {
			return !isset($errorOfferMap[$offer['offer']['offerId']]);
		}));
	}

	private function fillResponseResult(array $submitResults, Api\Business\OfferMappings\Update\OfferResultCollection $responseResults)
	{
		$errorOfferIds = [];

		/** @var Api\Business\OfferMappings\Update\OfferResult $responseResult */
		foreach ($responseResults as $responseResult)
		{
			$offerId = $responseResult->getOfferId();

			if (!isset($submitResults[$offerId])) { continue; }

			/** @var Result\Base $submitResult */
			$submitResult = $submitResults[$offerId];

			/** @var Api\Business\OfferMappings\Update\OfferError $error */
			foreach ($responseResult->getErrors() as $error)
			{
				$errorOfferIds[$offerId] = $offerId;
				$submitResult->addError(new Error\Base($error->errorMessage()));
			}

			if ($submitResult->hasWarnings()) { continue; }

			/** @var Api\Business\OfferMappings\Update\OfferError $error */
			foreach ($responseResult->getWarnings() as $error)
			{
				$submitResult->addWarning(new Error\Base($error->errorMessage()));
			}
		}

		return array_values($errorOfferIds);
	}

	private function fillSubmitData(array $submitResults, array $payloadBag, LoggerInterface $logger)
    {
        /** @var Result\Base $submitResult */
        foreach ($submitResults as $sku => $submitResult)
        {
            if (!$submitResult->isSuccess()) { continue; }

            $payload = $payloadBag[$sku];

            $data = [
                'ASSORTMENT' => [
                    'STATUS' => Catalog\Run\Storage\AssortmentTable::STATUS_PLACED,
                ],
            ];

            if (isset($payload['marketCategoryId']))
            {
                $data['ASSORTMENT']['CATEGORY_ID'] = (int)$payload['marketCategoryId'];
            }

			$logger->info(self::getMessage('SUBMITTED', [
				'#KEYS#' => $this->submittedLogMessage($payloadBag[$sku]),
			]), [
				'ENTITY_TYPE' => Catalog\Glossary::ENTITY_SKU,
				'ENTITY_ID' => $sku,
			]);

            $submitResult->setData($data);
        }
    }

	private function submittedLogMessage(array $payload)
	{
		$partials = [];

		foreach ($payload as $key => $value)
		{
			if ($key === 'currencyId') { continue; }

			$partials[] = $key . (is_array($value) ? sprintf(" (%s)", count($value)) : '');
		}

		return implode(', ', $partials);
	}
}