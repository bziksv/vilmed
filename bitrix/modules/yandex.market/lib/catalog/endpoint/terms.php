<?php
namespace Yandex\Market\Catalog\Endpoint;

use Yandex\Market\Api;
use Yandex\Market\Api\Reference\Auth;
use Yandex\Market\Catalog;
use Yandex\Market\Data\Vat;
use Yandex\Market\Logger\Trading\Audit;
use Yandex\Market\Reference\Concerns;
use Yandex\Market\Result;
use Yandex\Market\Psr\Log\LoggerInterface;

class Terms implements Driver
{
	use Concerns\HasMessage;

	/** @var int */
	private $campaignId;

	public function __construct($campaignId)
	{
		$this->campaignId = $campaignId;
	}

	public function type()
	{
		return Catalog\Glossary::ENDPOINT_TERMS;
	}

	public function campaignId()
	{
		return $this->campaignId;
	}

	public function audit()
	{
		return Audit::CATALOG_TERMS;
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
		return 500;
	}

	public function submit(array $payloadBag, Auth $auth, LoggerInterface $logger)
	{
		$offers = $this->compileOffers($payloadBag);

		$request = new Api\Campaigns\Offers\Update\Request($this->campaignId, $auth, $logger);
		$request->setOffers($offers);
		$request->execute();

		$this->logSubmitted($offers, $logger);

		return array_fill_keys(array_keys($payloadBag), new Result\Base());
	}

	private function compileOffers(array $bag)
	{
		$result = [];

		foreach ($bag as $sku => $data)
		{
			$result[] = [ 'offerId' => (string)$sku ] + $this->sanitize($data);
		}

		return $result;
	}

    private function sanitize(array $data)
    {
        $sanitized = [];

        if (isset($data['min-quantity']))
        {
            $sanitized['quantum']['minQuantity'] = (int)$data['min-quantity'];
        }

        if (isset($data['step-quantity']))
        {
            $sanitized['quantum']['stepQuantity'] = (int)$data['step-quantity'];
        }

        if (isset($data['available']))
        {
            $sanitized['available'] = (bool)$data['available'];
        }

        if (isset($data['vat']))
        {
            $sanitized['vat'] = (int)$data['vat'];
        }

        return $sanitized;
    }

	private function logSubmitted(array $offers, LoggerInterface $logger)
	{
		foreach ($offers as $offer)
		{
			$partials = [];

			if (isset($offer['quantum']['minQuantity']))
			{
				$partials[] = isset($offer['quantum']['stepQuantity'])
					? self::getMessage('MIN_QUANTITY_WITH_STEP', [
						'#MIN_QUANTITY#' => $offer['quantum']['minQuantity'],
						'#STEP_QUANTITY#' => $offer['quantum']['stepQuantity'],
					])
					: self::getMessage('MIN_QUANTITY', [
						'#MIN_QUANTITY#' => $offer['quantum']['minQuantity'],
					]);
			}
			else if (isset($offer['quantum']['stepQuantity']))
			{
				$partials[] = self::getMessage('STEP_QUANTITY', [
					'#STEP_QUANTITY#' => $offer['quantum']['stepQuantity'],
				]);
			}

			if (isset($offer['available']))
			{
				$partials[] = self::getMessage($offer['available'] ? 'AVAILABLE' : 'UNAVAILABLE');
			}

			if (isset($offer['vat']))
			{
				$partials[] = Vat::getTitle($offer['vat']);
			}

			if (empty($partials)) { return; }

			$logger->info(implode(', ', $partials), [
				'ENTITY_TYPE' => Catalog\Glossary::ENTITY_SKU,
				'ENTITY_ID' => $offer['offerId'],
			]);
		}
	}
}