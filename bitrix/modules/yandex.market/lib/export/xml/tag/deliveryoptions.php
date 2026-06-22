<?php
namespace Yandex\Market\Export\Xml\Tag;

use Yandex\Market;

class DeliveryOptions extends Base
    implements Concerns\HasTagValueModifier
{
	protected $requiredAttributeNames;

    /** @noinspection PhpDeprecationInspection */
    public function getDefaultParameters()
	{
		return [
			'empty_value' => true,
			'name' => 'delivery-options',
            'item_name' => 'option',
			'value_type' => Market\Type\Manager::TYPE_DELIVERY_OPTIONS,
		];
	}

    protected function getDefaultOptions($context)
	{
		return !empty($context['DELIVERY_OPTIONS']['delivery']) ? $context['DELIVERY_OPTIONS']['delivery'] : null;
	}

    public function tune(array $context)
    {
        $this->hasEmptyValue = !Market\Config::isExpertMode();
    }

    public function modifyTagValues(array $tagValues, array $context)
    {
        $tagValues = $this->normalizeTagValues($tagValues);

        if ($this->hasFilledTagValuesForDeliveryOptions($tagValues))
        {
            return $tagValues;
        }

        $defaultOptions = $this->getDefaultOptions($context);

        return $this->convertOptionsToTagValues($defaultOptions);
    }

	protected function hasFilledTagValuesForDeliveryOptions($tagValues)
	{
		$requiredAttributes = $this->getRequiredAttributeNames();

        foreach ($tagValues as $tagValue)
        {
            $isValidTagValue = true;

            foreach ($requiredAttributes as $requiredAttribute)
            {
                if (
                    !isset($tagValue['ATTRIBUTES'][$requiredAttribute])
                    || $tagValue['ATTRIBUTES'][$requiredAttribute] === ''
                )
                {
                    $isValidTagValue = false;
                    break;
                }
            }

            if ($isValidTagValue)
            {
                return true;
            }
        }

		return false;
	}

	protected function normalizeTagValues($tagValues)
	{
		$result = [];

		foreach ($tagValues as $tagValue)
		{
			$hasConverted = false;

			if (isset($tagValue['VALUE']) && is_array($tagValue['VALUE']))
			{
				$convertedTagValue = $this->convertOptionToTagValue($tagValue['VALUE']);

				if ($convertedTagValue !== null)
				{
					$hasConverted = true;
					$result[] = $convertedTagValue;
				}
				else
				{
					foreach ($tagValue['VALUE'] as $innerValue)
					{
						$convertedInnerValue = $this->convertOptionToTagValue($innerValue);

						if ($convertedInnerValue !== null)
						{
							$hasConverted = true;
							$result[] = $convertedInnerValue;
						}
					}
				}
			}

			if (!$hasConverted)
			{
				$result[] = $tagValue;
			}
		}

		return $result;
	}

	protected function convertOptionsToTagValues($options)
	{
		$result = [];

		if (is_array($options))
		{
			foreach ($options as $option)
			{
				$tagValue = $this->convertOptionToTagValue($option);

				if ($tagValue !== null)
				{
					$result[] = $tagValue;
				}
			}
		}

		return $result;
	}

	protected function convertOptionToTagValue($option)
	{
		$result = null;

		if (isset($option['COST'], $option['DAYS']))
		{
			$result = [
				'VALUE' => null,
				'ATTRIBUTES' => [
					'cost' => $option['COST'],
					'days' => $option['DAYS'],
					'order-before' => isset($option['ORDER_BEFORE']) ? $option['ORDER_BEFORE'] : null
				]
			];
		}

		return $result;
	}

	protected function getRequiredAttributeNames()
	{
		if ($this->requiredAttributeNames === null)
		{
			$this->requiredAttributeNames = $this->loadRequiredAttributeNames();
		}

		return $this->requiredAttributeNames;
	}

	protected function loadRequiredAttributeNames()
	{
		$result = [];

		foreach ($this->getAttributes() as $attribute)
		{
			if ($attribute->isRequired())
			{
				$result[] = $attribute->getId();
			}
		}

		return $result;
	}
}