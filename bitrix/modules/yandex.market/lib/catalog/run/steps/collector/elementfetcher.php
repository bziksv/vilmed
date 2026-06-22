<?php
namespace Yandex\Market\Catalog\Run\Steps\Collector;

use Yandex\Market\Export;
use Yandex\Market\Data;
use Yandex\Market\Glossary;
use Yandex\Market\Catalog;

class ElementFetcher
{
	protected $processor;
	protected $firstFilter = true;

	public function __construct(Catalog\Run\Processor $processor)
	{
		$this->processor = $processor;
	}

	public function __invoke(Data\Run\Waterfall $waterfall, State $state, Data\Run\Offset $offset)
	{
		(new Data\Run\Waterfall())
			->add([$this, 'iterateProductCollection'])
			->add([$this, 'iterateFilterCollection'])
			->add([$this, 'loadElements'])
			->add($waterfall)
			->run($state, $offset);
	}

	public function iterateProductCollection(Data\Run\Waterfall $waterfall, State $state, Data\Run\Offset $offset)
	{
		/** @var Catalog\Product\Model $catalogProduct */
		foreach ($state->catalog->getProductCollection() as $catalogProduct)
		{
			if (!$offset->tick('product')) { continue; }

			$selectBuilder = new Export\Routine\QueryBuilder\Select();

			$productState = clone $state;
			$productState->catalogProduct = $catalogProduct;
			$productState->context += $catalogProduct->getContext();

            $productState->sourceMapGroup = $this->tagMapGroup($catalogProduct->getEndpointCollection(), $productState->context);

			$productState->sourceSelect = $productState->sourceMapGroup->getSourceSelect();
			$productState->sourceSelect = $selectBuilder->boot($productState->sourceSelect, $productState->context);
			$productState->querySelect = $selectBuilder->compile($productState->sourceSelect, $productState->context);

			$waterfall->next($productState, $offset);

			$selectBuilder->release($productState->sourceSelect, $productState->context);

			if ($offset->interrupted()) { break; }
		}
	}

	private function tagMapGroup(Catalog\Endpoint\EndpointCollection $endpointCollection, array $context)
	{
		$group = [];

		/** @var Catalog\Endpoint\Endpoint $endpoint */
		foreach ($endpointCollection as $key => $endpoint)
		{
			$tagBundle = $endpoint->getTagBundle();
			$tagMap = $tagBundle->getMap();
			$rawMap = $tagMap->getRaw();
			$siblingMaps = [];

			/** @var Catalog\Endpoint\Endpoint $siblingEndpoint */
			foreach ($endpointCollection as $siblingKey => $siblingEndpoint)
			{
				if ($siblingKey === $key) { continue; }

				$siblingMaps[] = $siblingEndpoint->getTagBundle()->getMap()->getRaw();
			}

			$tagBundle->getTag()->extendTagDescriptionList($rawMap, $context + [
				'CAMPAIGN_ID' => $endpoint->getPrimary()->getCampaignId(),
				'SIBLING_TAG_MAP' => $siblingMaps,
			]);

			$tagMap->setRaw($rawMap);

			$group[$key] = $tagMap;
		}

		return new Export\Param\TagMapGroup($group);
	}

	public function iterateFilterCollection(Data\Run\Waterfall $waterfall, State $state, Data\Run\Offset $offset)
	{
		$this->firstFilter = true;

		/** @var Export\Filter\Model $exportFilter */
		foreach ($state->catalogProduct->getFilterCollection() as $exportFilter)
		{
			if (!$offset->tick('filter'))
			{
				$this->firstFilter = false;
				continue;
			}

			$changesFilter = null;

			if ($state->runAction === Data\Run\Processor::ACTION_CHANGE)
			{
				$changesFilter = $this->makeQueryChangesFilter($state->changes, $state->context);

				if ($changesFilter === null) { continue; } // changed other entity
			}

			$filterBuilder = new Export\Routine\QueryBuilder\Filter();

			$filterState = clone $state;
			$filterState->context += $exportFilter->getContext();
			$sourceFilter = $exportFilter->getSourceFilter();
			$sourceFilter = $filterBuilder->boot($sourceFilter, $filterState->context);

			foreach ($filterBuilder->compile($sourceFilter, $filterState->sourceSelect, $filterState->context, $changesFilter) as $queryFilter)
			{
				if (!$offset->tick('query')) { continue; }

				$filterState->queryFilter = $queryFilter;

				$waterfall->next($filterState, $offset);

				if ($offset->interrupted()) { break; }
			}

			$filterBuilder->release($sourceFilter, $filterState->context);
			$this->firstFilter = false;

			if ($offset->interrupted()) { break; }
		}
	}

	protected function makeQueryChangesFilter(array $changes, array $context)
	{
        if (!empty($changes[Glossary::ENTITY_CURRENCY])) { return []; }

		if (empty($changes[Glossary::ENTITY_OFFER])) { return null; }

		$ids = (array)$changes[Glossary::ENTITY_OFFER];

		if ($context['HAS_OFFER'])
		{
			$idsMap = array_flip($ids);

			$queryOffers = \CIBlockElement::GetList(
				[],
				[
					'IBLOCK_ID' => $context['OFFER_IBLOCK_ID'],
					'ID' => array_keys($idsMap),
				],
				false,
				false,
				[
					'IBLOCK_ID',
					'ID',
					'PROPERTY_' . $context['OFFER_PROPERTY_ID'],
				]
			);

			while ($offer = $queryOffers->Fetch())
			{
				$offerId = (int)$offer['ID'];
				$parentId = (int)$offer['PROPERTY_' . $context['OFFER_PROPERTY_ID'] . '_VALUE'];

				if ($parentId > 0 && !isset($idsMap[$parentId]))
				{
					$idsMap[$parentId] = true;
				}

				if (isset($idsMap[$offerId]))
				{
					unset($idsMap[$offerId]);
				}
			}

			$ids = array_keys($idsMap);
		}

		return [
			'ELEMENT' => [ 'ID' => $ids ],
		];
	}

	public function loadElements(Data\Run\Waterfall $waterfall, State $state, Data\Run\Offset $offset)
	{
		do
		{
			$elementFetcher = new Export\Routine\QueryBuilder\ElementFetcher();

			if (!$this->firstFilter)
			{
				$readyFilter = [
					'=CATALOG_ID' => $state->catalog->getId(),
					'>=TIMESTAMP_X' => $state->initTime,
				];

				$elementFetcher->exclude(Catalog\Run\Storage\OfferTable::class, $readyFilter, 'ELEMENT_ID');
			}

			$queryResult = $elementFetcher->load($state->queryFilter, $state->querySelect, $state->context, $offset->get('element'));

			$sourceFetcher = new Export\Routine\QueryBuilder\SourceFetcher();
			$sourceValues = $sourceFetcher->load($state->sourceSelect, $queryResult['ELEMENT'], $queryResult['PARENT'], $state->context);

			foreach (array_chunk($queryResult['ELEMENT'], 500, true) as $elementsChunk)
			{
				$elementsState = clone $state;
				$elementsState->elements = $elementsChunk;
				$elementsState->sourceValues = array_intersect_key($sourceValues, $elementsChunk);
				$elementsState->logger = $this->makeLogger(array_keys($elementsChunk));

				$waterfall->next($elementsState);

				$elementsState->logger->flush();
			}

			$offset->set('element', $queryResult['OFFSET']);

			if (!$queryResult['HAS_NEXT']) { break; }

			if ($this->processor->isExpired())
			{
				$offset->interrupt();
				break;
			}
		}
		while (true);
	}

	private function makeLogger(array $elementIds)
	{
		$logger = $this->processor->makeLogger();
		$logger->registerElements(Glossary::ENTITY_OFFER, $elementIds);
		$logger->allowCheckExists();
		$logger->allowRelease();
		$logger->allowBatch();

		return $logger;
	}
}