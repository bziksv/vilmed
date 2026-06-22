<?php
namespace Yandex\Market\Export\Entity\Iblock\Property\Feature\Set;

use Bitrix\Iblock;
use Yandex\Market;
use Yandex\Market\Data\TextString;
use Yandex\Market\Reference\Concerns;

class Feature extends Skeleton
{
	use Concerns\HasMessage;

	/** @var array */
	protected $feature;
	/** @var array */
	protected $context;
	/** @var array */
	protected $merged;
	/** @var bool */
	protected $deprecated;

	public function __construct(array $feature, array $context)
	{
		parent::__construct($context);

		$this->feature = $feature;
	}

	public function key()
	{
		return $this->feature['MODULE_ID'] . '.' . $this->feature['FEATURE_ID'];
	}

	public function title()
	{
		if ($this->feature['MODULE_ID'] === Market\Config::getModuleName())
		{
			return Market\Ui\Iblock\PropertyFeature::getTitle('GENETIVE');
		}

		$langKey = TextString::toUpper($this->feature['MODULE_ID']) . '_' . TextString::toUpper($this->feature['FEATURE_ID']);
		$fallback = $this->feature['FEATURE_NAME'] ?: $this->feature['FEATURE_ID'];

		return self::getMessage($langKey, null, $fallback);
	}

	public function deprecate($need = true)
	{
		$this->deprecated = $need;
	}

	public function deprecated()
	{
		return $this->deprecated;
	}

	public function merge(array $featureIds)
	{
		$this->merged = $featureIds;
	}

	public function properties()
	{
		$result = [];

		$sourcesMap = $this->sourcesMap();
		$iblockMap = array_flip($sourcesMap);
		$filter = $this->featureFilter();
		$filter['=IS_ENABLED'] = 'Y';
		$filter['=PROPERTY.IBLOCK_ID'] = array_values($sourcesMap);
		$filter['=PROPERTY.ACTIVE'] = 'Y';

		$queryProperties = Iblock\PropertyFeatureTable::getList([
			'select' => [
				'PROPERTY_ID',
				'IBLOCK_PROPERTY_IBLOCK_ID' => 'PROPERTY.IBLOCK_ID',
			],
			'filter' => $filter,
			'order' => [
				'PROPERTY.SORT' => 'ASC',
				'PROPERTY.ID' => 'ASC',
			],
		]);

		while ($property = $queryProperties->fetch())
		{
			$iblockId = (int)$property['IBLOCK_PROPERTY_IBLOCK_ID'];
			$propertyId = (int)$property['PROPERTY_ID'];
			$sourceType = $iblockMap[$iblockId];

			if (!isset($result[$sourceType]))
			{
				$result[$sourceType] = [];
			}

			$result[$sourceType][$propertyId] = $propertyId;
		}

		return $result;
	}

	protected function featureFilter()
	{
		$partials = [];
		$partials[] = [
			'=MODULE_ID' => $this->feature['MODULE_ID'],
			'=FEATURE_ID' => $this->feature['FEATURE_ID'],
		];

		foreach ($this->merged as $merged)
		{
			list($module, $feature) = explode('.', $merged, 2);

			$partials[] = [
				'=MODULE_ID' => $module,
				'=FEATURE_ID' => $feature,
			];
		}

		if (count($partials) === 1)
		{
			return reset($partials);
		}

		return [
			[ 'LOGIC' => 'OR' ] + $partials,
		];
	}
}