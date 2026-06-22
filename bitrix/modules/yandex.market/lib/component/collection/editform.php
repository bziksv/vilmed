<?php
namespace Yandex\Market\Component\Collection;

use Bitrix\Main;
use Yandex\Market;
use Yandex\Market\Export;
use Yandex\Market\Component;
use Yandex\Market\Component\Molecules;
use Yandex\Market\Reference\Concerns;

class EditForm extends Market\Component\Model\EditForm
{
	use Concerns\HasMessage;

	protected $exportLink;
	protected $productLink;
	protected $productFilter;
	protected $userFields;

	public function __construct(\CBitrixComponent $component, array $componentParameters = [])
	{
		parent::__construct($component, $componentParameters);

		$this->exportLink = new Molecules\ExportLink();
		$this->productLink = new Molecules\ProductLink([
			'COLLECTION_PRODUCT',
		]);
		$this->productFilter = new Molecules\ProductFilter([
			'COLLECTION_PRODUCT.FILTER',
		]);
		$this->userFields = new Molecules\UserFields(Molecules\UserFields::FIELDS_KNOWN);
	}

	public function modifyRequest(array $request, array $fields)
	{
		$request = parent::modifyRequest($request, $fields);
		$request = $this->exportLink->sanitize($request);
		$request = $this->productLink->sanitizeIblock($request, $this->exportLink->usedIblockIds($request));
		$request = $this->productFilter->sanitizeFilter($request, $fields);
		$request = $this->userFields->sanitize($request, $fields);

		return $request;
	}

	public function validate(array $data, array $fields)
	{
	    $tableData = $data;

		$result = parent::validate($tableData, $fields);
		$this->exportLink->validate($result, $data, $fields);
		$this->productFilter->validate($result, $data, $fields);
		$this->userFields->validate($result, $data, $fields);

		return $result;
	}

	public function getFields(array $select = [], array $item = null)
	{
		$result = parent::getFields($select, $item);
		$result = $this->extendStrategyFields($result, $select, $item);
		$result = $this->extendLimitFields($result, $select, $item);

		return $result;
	}

	protected function extendStrategyFields(array $fields, array $select = [], $item = null)
	{
		$fields = array_diff_key($fields, [ 'STRATEGY_SETTINGS' => true ]);

		if (!empty($select) && !in_array('STRATEGY_SETTINGS', $select, true)) { return $fields; }

		$type = $this->selectedStrategy($item);
		$usedIblockIds = null;
		$strategy = Export\Collection\Strategy\Registry::createStrategy($type);

		foreach ($strategy->getFields() as $name => $field)
		{
			$settingName = sprintf('STRATEGY_SETTINGS[%s]', $name);

			$fields[$settingName] = Market\Ui\UserField\Helper\Field::extend($field, $settingName);

			if (isset($field['TYPE']) && mb_strpos($field['TYPE'], 'iblock') === 0)
			{
				if ($usedIblockIds === null) { $usedIblockIds = $this->exportLink->usedIblockIds($item); }

				$fields[$settingName]['SETTINGS']['IBLOCK_ID'] = $usedIblockIds;
			}

			$this->userFields->know($settingName);
		}

		if (!($strategy instanceof Export\Collection\Strategy\StrategyFilterable))
		{
			foreach ($fields as $name => $field)
			{
				if (!isset($field['FIELD_GROUP']) || $field['FIELD_GROUP'] !== 'COLLECTION_PRODUCT.FILTER') { continue; }

				unset($fields[$name]);
			}
		}

		return $fields;
	}

	protected function selectedStrategy($item)
	{
		if (!empty($item['STRATEGY']))
		{
			return $item['STRATEGY'];
		}

		$storedFields = $this->getComponentResult('FIELDS');

		if (isset($storedFields['STRATEGY']['VALUES'][0]['ID']))
		{
			return $storedFields['STRATEGY']['VALUES'][0]['ID'];
		}

		throw new Main\ArgumentException('cant find selected strategy');
	}

	protected function extendLimitFields(array $fields, array $select = [], $item = null)
	{
		$fields = array_diff_key($fields, [ 'LIMIT_SETTINGS' => true ]);

		if (!empty($select) && !in_array('LIMIT_SETTINGS', $select, true)) { return $fields; }

		$usedIblockIds = null;
		$limit = new Export\Collection\Limit();
		$first = true;
		$hasProductFilter = array_reduce($fields, static function($carry, array $field) {
			return $carry || (isset($field['FIELD_GROUP']) && $field['FIELD_GROUP'] === 'COLLECTION_PRODUCT.FILTER');
		}, false);

		foreach ($limit->getFields() as $name => $field)
		{
			if ($first && !$hasProductFilter && !isset($field['GROUP']))
			{
				$field['GROUP'] = self::getMessage('LIMIT_GROUP');
			}

			$settingName = sprintf('LIMIT_SETTINGS[%s]', $name);

			if (isset($field['DEPEND']))
			{
				$newDepend = [];

				foreach ($field['DEPEND'] as $depend => $rule)
				{
					$newDepend[sprintf('LIMIT_SETTINGS[%s]', $depend)] = $rule;
				}

				$field['DEPEND'] = $newDepend;
			}

			$fields[$settingName] = Market\Ui\UserField\Helper\Field::extend($field, $settingName);

			if (isset($field['TYPE']) && $field['TYPE'] === 'exportParam')
			{
				if ($usedIblockIds === null) { $usedIblockIds = $this->exportLink->usedIblockIds($item); }

				$fields[$settingName]['SETTINGS']['IBLOCK_ID'] = $usedIblockIds;
			}

			$this->userFields->know($settingName);

			$first = false;
		}

		return $fields;
	}

	public function extend(array $data, array $fields)
	{
		return $this->productLink->extend($data);
	}

	public function add(array $data)
	{
		$data = $this->applyUserFieldsOnBeforeSave($data);

		return parent::add($data);
	}

	public function update($primary, array $data)
	{
		$data = $this->applyUserFieldsOnBeforeSave($data);

		return parent::update($primary, $data);
	}

	protected function applyUserFieldsOnBeforeSave($values)
	{
		$fields = $this->getComponentResult('FIELDS');

		return $this->userFields->beforeSave(
			$fields,
			$values,
			$this->getComponentParam('PRIMARY') ?: null,
			array_map(function(array $field) { return $this->component->getOriginalValue($field); }, $fields)
		);
	}
}