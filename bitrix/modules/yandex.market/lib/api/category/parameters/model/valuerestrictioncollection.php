<?php
namespace Yandex\Market\Api\Category\Parameters\Model;

use Yandex\Market\Api\Reference\Collection;

/** @property ValueRestriction[] $collection */
class ValueRestrictionCollection extends Collection
{
    public static function getItemReference()
    {
        return ValueRestriction::class;
    }

	public function getDependsOn($valueId)
	{
		$valueId = (int)$valueId;
		$result = [];

		foreach ($this->collection as $valueRestriction)
		{
			$parameterId = $valueRestriction->getLimitingParameterId();

			/** @var LimitedValue $limitedValue*/
			foreach ($valueRestriction->getLimitedValues() as $limitedValue)
			{
				if (in_array($valueId, $limitedValue->getLimitedValues(), true))
				{
					if (!isset($result[$parameterId])) { $result[$parameterId] = []; }

					$result[$parameterId][] = $limitedValue->getLimitingOptionValueId();
				}
			}
		}

		return $result;
	}

	public function getRestricted(array $parameterValues)
	{
		if (empty($parameterValues)) { return null; }

		$result = null;

		foreach ($this->collection as $restriction)
		{
			$selected = array_filter($parameterValues, static function(array $parameterValue) use ($restriction) {
				return $parameterValue['parameterId'] === $restriction->getLimitingParameterId();
			});

			foreach ($selected as $parameterValue)
			{
				if (!isset($parameterValue['valueId'])) { continue; }

				/** @var LimitedValue $limitedValue */
				foreach ($restriction->getLimitedValues() as $limitedValue)
				{
					if ($limitedValue->getLimitingOptionValueId() !== $parameterValue['valueId']) { continue; }

					if ($result !== null)
					{
						$result = array_intersect($result, $limitedValue->getLimitedValues());
						break;
					}

					$result = $limitedValue->getLimitedValues();
					break;
				}
			}
		}

		return $result;
	}
}