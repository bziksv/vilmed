<?php
namespace Yandex\Market\Trading\Entity\Common;

use Yandex\Market;

class Pack extends Market\Trading\Entity\Reference\Pack
{
	protected $formatType;

	public function getRatio($productIds, array $context = [])
	{
		if (empty($context['SOURCES'])) { return []; }

		$fixedRatio = $this->fixedRatio($context);

		if ($fixedRatio !== null)
		{
			return array_fill_keys($productIds, $fixedRatio);
		}

		$exportContext = $this->buildExportContext($context);
		$sourceSelect = $this->buildExportSelect($context);

		$exportValues = Market\Export\Entity\Facade::loadValues($productIds, $sourceSelect, $exportContext);

		return $this->collectRatioValues($exportValues, $context);
	}

	protected function fixedRatio(array $context)
	{
		foreach ($context['SOURCES'] as list($source, $field))
		{
			if ($source !== Market\Export\Entity\Manager::TYPE_TEXT) { continue; }

			$ratio = (float)$field;

			if ($ratio > 0)
			{
				return $ratio;
			}
		}

		return null;
	}

	protected function buildExportSelect(array $context)
	{
		$result = [];

		foreach ($context['SOURCES'] as list($source, $field))
		{
			if (!isset($result[$source]))
			{
				$result[$source] = [];
			}

			$result[$source][] = $field;
		}

		return $result;
	}

	protected function buildExportContext(array $context)
	{
		return array_intersect_key($context, [
			'SITE_ID' => true,
		]);
	}

	protected function collectRatioValues(array $allValues, array $context)
	{
		$result = [];

		foreach ($allValues as $productId => $oneValues)
		{
			foreach ($context['SOURCES'] as list($source, $field))
			{
				if (!isset($oneValues[$source][$field])) { continue; }

				$value = $this->sanitizeRatioValue($oneValues[$source][$field]);

				if ($value === null || $value <= 0) { continue; }

				$result[$productId] = $value;
			}
		}

		return $result;
	}

	protected function sanitizeRatioValue($value)
	{
		if (is_array($value))
		{
			$value = reset($value);
		}

		if (empty($value)) { return null; }

		$value = $this->getFormatType()->sanitize($value);

		if ($value === null || $value instanceof Market\Error\Base) { return null; }

		return (float)$value;
	}

	protected function getFormatType()
	{
		if ($this->formatType === null)
		{
			$this->formatType = new Market\Type\NumberType([
				'value_precision' => 4,
                'value_positive' => true,
			]);
		}

		return $this->formatType;
	}
}