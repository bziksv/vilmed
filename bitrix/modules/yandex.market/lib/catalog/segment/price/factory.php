<?php
namespace Yandex\Market\Catalog\Segment\Price;

use Yandex\Market\Catalog\Segment;
use Yandex\Market\Catalog\Endpoint;
use Yandex\Market\Export\Param;
use Yandex\Market\Trading\Business;
use Yandex\Market\Trading\Campaign;

class Factory implements Segment\Factory
{
	public function businessConfig(Business\Model $business)
	{
		$onlyDefaultPrice = $business->getExternalSettings()->onlyDefaultPrice();

		return new Segment\BusinessConfig(new BusinessFormat($onlyDefaultPrice));
	}

	public function campaignConfigs(Business\Model $business)
	{
		$result = [];

		/** @var Campaign\Model $campaign */
		foreach ($business->getCampaignCollection() as $campaign)
		{
			$format = $business->getExternalSettings()->onlyDefaultPrice()
				? new CampaignTermsFormat()
				: new CampaignPriceFormat();

			$result[] = new Segment\CampaignConfig($format, $campaign->getId(), $campaign->getName(), $campaign->getPlacement());
		}

		return $result;
	}

	public function endpoints(Business\Model $business, Segment\Collection $segmentCollection)
	{
		$businessSegment = $segmentCollection->getBusinessItem();
		$campaignSegments = $segmentCollection->getCampaignItems();

		if ($business->getExternalSettings()->onlyDefaultPrice())
		{
			return array_merge(
				array_filter([
					$this->offerEndpoint($business->getId(), $businessSegment, true),
					$this->priceBusinessEndpoint($business->getId(), $businessSegment)
				]),
				$this->termsEndpoints($campaignSegments, true)
			);
		}

		return array_merge(
			array_filter([
				$this->offerEndpoint($business->getId(), $businessSegment),
                $this->priceBusinessEndpoint($business->getId(), $businessSegment)
			]),
			$this->priceCampaignEndpoints($campaignSegments, $businessSegment),
			$this->termsEndpoints($campaignSegments)
		);
	}

	private function offerEndpoint($businessId, Segment\Model $businessSegment = null, $onlyDefaultPrice = false)
	{
		if ($businessSegment === null) { return null; }

		$map = $businessSegment->getParamCollection()->getTagMap();
		$required = array_keys(array_filter([
			'purchasePrice' => true,
			'cofinancePrice' => true,
			'additionalExpenses' => true,
		]));

		if (!$map->hasAny($required)) { return null; }

		$names = array_merge($required, [ 'currencyId' ]);

		return new Endpoint\Endpoint(
			new Endpoint\Offer($businessId),
			new Param\TagBundle(
				(new BusinessFormat($onlyDefaultPrice, true))->getTag()->cloneOnly($names),
				$map->cloneOnly($names)
			),
			'price'
		);
	}

	private function priceBusinessEndpoint($businessId, Segment\Model $businessSegment = null)
	{
		if ($businessSegment === null) { return null; }

		$map = $businessSegment->getParamCollection()->getTagMap();

		if (!$map->has('basicPrice')) { return null; }

		$names = [ 'basicPrice', 'discountBase', 'currencyId' ];

		return new Endpoint\Endpoint(
			new Endpoint\PriceBusiness($businessId),
			new Param\TagBundle(
				(new BusinessFormat(true, true))->getTag()->cloneOnly($names),
				$map->cloneOnly($names)
			)
		);
	}

	private function termsEndpoints(Segment\Collection $campaignSegments, $onlyDefaultPrice = false)
	{
		$result = [];

		/** @var Segment\Model $campaignSegment */
		foreach ($campaignSegments as $campaignSegment)
		{
			$map = $campaignSegment->getParamCollection()->getTagMap();

			if (!$map->has('vat')) { continue; }
			if (!$onlyDefaultPrice && $map->has('price')) { continue; }

			$names = [ 'vat' ];

			$result[] = new Endpoint\Endpoint(
				new Endpoint\Terms($campaignSegment->getCampaignId()),
				new Param\TagBundle(
					(new CampaignTermsFormat())->getTag()->cloneOnly($names),
					$map->cloneOnly($names)
				),
				'vat'
			);
		}

		return $result;
	}

	private function priceCampaignEndpoints(Segment\Collection $campaignSegments, Segment\Model $businessSegment = null)
	{
		$result = [];
		$defaultMap = null;

		if ($businessSegment !== null)
		{
			$defaultMap = $businessSegment->getParamCollection()->getTagMap()->cloneOnly([ 'currencyId' ]);
		}

		/** @var Segment\Model $campaignSegment */
		foreach ($campaignSegments as $campaignSegment)
		{
			$map = $campaignSegment->getParamCollection()->getTagMap();

			if (!$map->has('price')) { continue; }

			$tag = (new CampaignPriceFormat(true))->getTag();

			if ($defaultMap !== null) { $map = $map->merge($defaultMap); }

			$result[] = new Endpoint\Endpoint(
				new Endpoint\PriceCampaign($campaignSegment->getCampaignId()),
				new Param\TagBundle($tag, $map)
			);
		}

		return $result;
	}
}