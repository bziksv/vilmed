<?php
namespace Yandex\Market\Export\Filter;

use Yandex\Market;

class Model extends Market\Reference\Storage\Model
{
	/** @var array|null */
	protected $plainFilter;
	/** @var array|null */
	protected $plainData;

	public static function makeForAll()
	{
		return new static([
			'FILTER_CONDITION' => [],
			'DELIVERY' => [],
		]);
	}

	public static function getDataClass()
	{
		return Table::class;
	}

	public function getPlainFilter()
	{
		return $this->plainFilter;
	}

	public function setPlainFilter(array $plainFilter)
	{
		$this->plainFilter = $plainFilter;
	}

	public function getPlainData()
	{
		return $this->plainData;
	}

	public function setPlainData(array $data)
	{
		$this->plainData = $data;
	}

	public function getSourceFilter()
	{
		if ($this->plainFilter !== null)
		{
			$result = $this->plainFilter;
		}
		else
		{
			$result = [];

			/** @var \Yandex\Market\Export\FilterCondition\Model $condition */
			foreach ($this->getConditionCollection() as $condition)
			{
				if ($condition->isValid())
				{
					$conditionCompare = $condition->getQueryCompare();
					$conditionField = $condition->getQueryField();
					$conditionValue = $condition->getQueryValue();
					$conditionSource = $condition->getSourceName();

					if (!isset($result[$conditionSource]))
					{
						$result[$conditionSource] = [];
					}

					$result[$conditionSource][] = [
						'FIELD' => $conditionField,
						'COMPARE' => $conditionCompare,
						'VALUE' => $conditionValue,
						'STRICT' => $condition->isQueryCompareStrict(),
					];
				}
			}
		}

		return $result;
	}

	public function getUsedSources()
	{
		$sourceFilter = $this->getSourceFilter();
		$usedSources = $this->getFilterUsedSources($sourceFilter);

		return array_keys($usedSources);
	}

	/**
	 * @param $sourceFilter
	 *
	 * @return array
	 */
	protected function getFilterUsedSources($sourceFilter)
	{
		$result = [];

		foreach ($sourceFilter as $sourceName => $filter)
		{
			if ($sourceName === 'LOGIC')
			{
				// nothing
			}
			else if (is_numeric($sourceName))
			{
				$result += $this->getFilterUsedSources($filter);
			}
			else
			{
				$result[$sourceName] = true;
			}
		}

		return $result;
	}

	public function getContext()
	{
		$result = [
			'FILTER_ID' => $this->getId(),
		];

		// sales notes

		$salesNotes = $this->getSalesNotes();

		if ($salesNotes !== '')
		{
			$result['SALES_NOTES'] = $salesNotes;
		}

		// delivery options

		$deliveryOptions = $this->getDeliveryOptions();

		if (!empty($deliveryOptions))
		{
			$result['DELIVERY_OPTIONS'] = $deliveryOptions;
		}

		return $result;
	}

	public function getDeliveryOptions()
	{
		return $this->getDeliveryCollection()->getDeliveryOptions();
	}

	public function getSalesNotes()
	{
		return trim($this->getField('SALES_NOTES'));
	}

	public function getConditionCollection()
	{
		return $this->getCollection('FILTER_CONDITION', Market\Export\FilterCondition\Collection::class);
	}

	public function getDeliveryCollection()
	{
		return $this->getCollection('DELIVERY', Market\Export\Delivery\Collection::class);
	}
}
