<?php
namespace Yandex\Market\Catalog\Run\Steps\Collector;

use Yandex\Market\Catalog\Glossary;
use Yandex\Market\Catalog\Run\Processor;
use Yandex\Market\Catalog\Run\Storage;
use Yandex\Market\Reference\Concerns;
use Yandex\Market\Logger\Trading\Audit;
use Yandex\Market\Data;
use Yandex\Market\Trading;
use Yandex\Market\Utils;

class OffersBuilder
{
	use Concerns\HasMessage;

	public function __invoke(Data\Run\Waterfall $waterfall, State $state)
	{
		$rows = $this->compileRows($state->elements, $state);
		$rows = $this->extendSku($rows, $state);
        $rows = $this->skuConflict($rows, $state);

		$elementsState = clone $state;
		$elementsState->offers = $rows;

		$waterfall->next($elementsState);
	}

	private function compileRows(array $elements, State $state)
	{
		$result = [];
		$catalogId = (int)$state->catalog->getId();
		$timestamp = new Data\Type\CanonicalDateTime();

		foreach ($elements as $elementId => $element)
		{
			$result[$elementId] = [
				'CATALOG_ID' => $catalogId,
				'ELEMENT_ID' => (int)$elementId,
				'PARENT_ID' => isset($element['PARENT_ID']) ? (int)$element['PARENT_ID'] : 0,
				'SKU' => '',
				'STATUS' => Storage\OfferTable::STATUS_SUCCESS,
				'TIMESTAMP_X' => $timestamp,
			];
		}

		return $result;
	}

	private function extendSku(array $rows, State $state)
	{
		$skuMap = $this->skuMap(array_column($rows, 'ELEMENT_ID'), $state);
		$used = [];

		foreach ($rows as &$row)
		{
			$sku = null;

			if ($skuMap === null)
			{
				$sku = $row['ELEMENT_ID'];
			}
			else if (isset($skuMap[$row['ELEMENT_ID']]))
			{
				$sku = $skuMap[$row['ELEMENT_ID']];
			}

            if ((string)$sku === '')
            {
                $row['STATUS'] = Storage\OfferTable::STATUS_ERROR;
	            $state->logger->warning(self::getMessage('SKU_EMPTY'), [
		            'ENTITY_TYPE' => Glossary::ENTITY_OFFER,
		            'ENTITY_ID' => $row['ELEMENT_ID'],
		            'AUDIT' => Audit::CATALOG_OFFER,
	            ]);
                continue;
            }

			if (isset($used[$sku]) && $used[$sku] !== $row['ELEMENT_ID'])
			{
				$row['STATUS'] = Storage\OfferTable::STATUS_DUPLICATE;
				$state->logger->warning(self::getMessage('SKU_DUPLICATE', [
					'#SKU#' => $sku,
					'#ORIGIN_ID#' => $used[$sku],
				]), [
					'ENTITY_TYPE' => Glossary::ENTITY_OFFER,
					'ENTITY_ID' => $row['ELEMENT_ID'],
					'AUDIT' => Audit::CATALOG_OFFER,
				]);
				continue;
			}

			$row['SKU'] = $sku;
			$used[$sku] = $row['ELEMENT_ID'];
		}

		return $rows;
	}

	private function skuMap(array $elementIds, State $state)
	{
        $trading = $state->catalog->getBusiness()->getPrimaryTrading();
		$command = $trading->wakeupService()->getContainer()->get(Trading\Service\Common\Command\SkuMap::class, [
            'environment' => $trading->getEnvironment(),
			'useDuplicates' => false,
        ]);

		return $command->make($elementIds);
	}

	private function skuConflict(array $rows, State $state)
	{
		$stored = $this->skuElements($rows, $state);

		foreach ($rows as &$row)
		{
			if (!isset($row['SKU'], $stored[$row['SKU']])) { continue; }

			if ((int)$stored[$row['SKU']] !== (int)$row['ELEMENT_ID'])
            {
                $row['SKU'] = '';
                $row['STATUS'] = Storage\OfferTable::STATUS_DUPLICATE;

	            $state->logger->warning(self::getMessage('SKU_DUPLICATE', [
		            '#SKU#' => $row['SKU'],
		            '#ORIGIN_ID#' => $stored[$row['SKU']],
	            ]), [
		            'ENTITY_TYPE' => Glossary::ENTITY_OFFER,
		            'ENTITY_ID' => $row['ELEMENT_ID'],
		            'AUDIT' => Audit::CATALOG_OFFER,
	            ]);
            }
		}
		unset($row);

		return $rows;
	}

	private function skuElements(array $rows, State $state)
	{
		$skus = Utils\ArrayHelper::column($rows, 'SKU');

		if (empty($skus)) { return []; }

		$result = [];
        $filter = [
            '=CATALOG_ID' => $state->catalog->getId(),
            '=SKU' => array_values($skus),
            '=STATUS' => [
                Storage\OfferTable::STATUS_SUCCESS,
                Storage\OfferTable::STATUS_ERROR,
            ],
        ];

        if ($state->runAction !== Processor::ACTION_CHANGE)
        {
            $filter['>=TIMESTAMP_X'] = $state->initTime;
        }

		$query = Storage\OfferTable::getList([
			'filter' => $filter,
			'select' => [ 'ELEMENT_ID', 'SKU' ],
		]);

		while ($row = $query->fetch())
		{
			$result[$row['SKU']] = (int)$row['ELEMENT_ID'];
		}

		return $result;
	}
}