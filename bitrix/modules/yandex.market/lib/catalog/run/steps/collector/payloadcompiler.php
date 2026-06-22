<?php
namespace Yandex\Market\Catalog\Run\Steps\Collector;

use Bitrix\Main;
use Yandex\Market\Config;
use Yandex\Market\Result;
use Yandex\Market\Catalog;
use Yandex\Market\Catalog\Run\Storage;
use Yandex\Market\Data\Run\Waterfall;

class PayloadCompiler
{
	public function __invoke(Waterfall $waterfall, State $state)
	{
        $endpointCollection = $state->catalogProduct->getEndpointCollection();
        $elementIds = array_keys(array_filter($state->offers, static function(array $offer) { return $offer['STATUS'] === Storage\OfferTable::STATUS_SUCCESS; }));

		$tagValues = $this->extractValues($elementIds, $state->sourceValues, $endpointCollection, $state->context);
		$tagValues = $this->extendValues($tagValues, $state->elements, $endpointCollection, $state->context);

		$tagNodes = $this->compileNodes($tagValues, $endpointCollection, $state->context);
		$tagNodes = $this->extendNodes($tagNodes, $state->elements, $endpointCollection, $state->context);

        $state = clone $state;
        $state->tagNodes = $tagNodes;

		$waterfall->next($state);
	}

	private function extractValues(array $elementIds, array $sourceValues, Catalog\Endpoint\EndpointCollection $endpointCollection, array $context)
	{
		$result = [];

		/** @var Catalog\Endpoint\Endpoint $endpoint */
		foreach ($endpointCollection as $endpointKey => $endpoint)
		{
			$tagBundle = $endpoint->getTagBundle();
            $endpointValues = [];

            foreach ($elementIds as $elementId)
            {
                $elementValues = isset($sourceValues[$elementId]) ? $sourceValues[$elementId] : [];

                $endpointValues[$elementId] = $tagBundle->extract($elementValues, $context);
            }

			$result[$endpointKey] = $endpointValues;
		}

		return $result;
	}

	private function extendValues(array $tagValues, array $elements, Catalog\Endpoint\EndpointCollection $endpointCollection, array $context)
	{
		/** @var Catalog\Endpoint\Endpoint $endpoint */
		foreach ($endpointCollection as $endpointKey => $endpoint)
		{
			if (empty($tagValues[$endpointKey])) { continue; }

			$endpointValues = $tagValues[$endpointKey];
			$primary = $endpoint->getPrimary();

			$moduleName = Config::getModuleName();
			$eventName = 'onCatalog' . ucfirst($primary->getType()) . 'ExtendData';
			$eventData = [
				'TAG_VALUE_LIST' => $endpointValues,
				'ELEMENT_LIST' => $elements,
				'CONTEXT' => $context + [
					'ENDPOINT_PART' => $primary->getPart(),
					'CAMPAIGN_ID' => $primary->getCampaignId(),
				],
			];

			$event = new Main\Event($moduleName, $eventName, $eventData);
			$event->send();
		}

		return $tagValues;
	}

	private function compileNodes(array $tagValues, Catalog\Endpoint\EndpointCollection $endpointCollection, array $context)
	{
		$result = [];

		/** @var Catalog\Endpoint\Endpoint $endpoint */
		foreach ($tagValues as $endpointKey => $endpointValues)
		{
			$formatTag = $endpointCollection->requireItem($endpointKey)->getTagBundle()->getTag();
			$endpointNodes = [];

			/** @var Result\XmlValue $tagValue */
			foreach ($endpointValues as $elementId => $tagValue)
			{
                if (!$tagValue->isSuccess())
                {
                    $endpointNodes[$elementId] = Result\Facade::merge([new Result\XmlNode(), $tagValue]);
                    continue;
                }

                $endpointNodes[$elementId] = $formatTag->exportJson($tagValue->getTagData(), $context);
			}

			$result[$endpointKey] = $endpointNodes;
		}

		return $result;
	}

	private function extendNodes(array $tagNodes, array $elements, Catalog\Endpoint\EndpointCollection $endpointCollection, array $context)
	{
		/** @var Catalog\Endpoint\Endpoint $endpoint */
		foreach ($endpointCollection as $endpointKey => $endpoint)
		{
			if (empty($tagNodes[$endpointKey])) { continue; }

			$endpointNodes = $tagNodes[$endpointKey];
			$primary = $endpoint->getPrimary();

			$moduleName = Config::getModuleName();
			$eventName = 'onCatalog' . ucfirst($primary->getType()) . 'WriteData';
			$eventData = [
				'TAG_RESULT_LIST' => $endpointNodes,
				'ELEMENT_LIST' => $elements,
				'CONTEXT' => $context + [
					'ENDPOINT_PART' => $primary->getPart(),
					'CAMPAIGN_ID' => $primary->getCampaignId(),
				],
			];

			$event = new Main\Event($moduleName, $eventName, $eventData);
			$event->send();
		}

		return $tagNodes;
	}
}