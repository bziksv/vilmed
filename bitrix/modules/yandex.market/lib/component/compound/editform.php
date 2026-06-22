<?php
namespace Yandex\Market\Component\Compound;

use Bitrix\Main;
use Yandex\Market\Component;
use Yandex\Market\Components\AdminFormEdit;
use Yandex\Market\Reference\Assert;
use Yandex\Market\Result;
use Yandex\Market\Utils\ArrayHelper;
use Yandex\Market\Utils\Field;

class EditForm extends Component\Base\EditForm
{
	/** @var array<string, Component\Base\EditForm> */
	private $children;

	public function __construct(\CBitrixComponent $component, array $componentParams = [])
	{
		parent::__construct($component, $componentParams);

		$this->children = $this->initChildren($componentParams['CHILDREN']);
	}

	private function initChildren(array $childrenParameters)
	{
		$children = [];

		foreach ($childrenParameters as $childName => $childParameters)
		{
			/** @var class-string<Component\Base\EditForm> $className */
			$className = $childParameters['PROVIDER'];

			Assert::nonEmptyString($className, "arParams[{$childName}][PROVIDER]");
			Assert::isSubclassOf($className, Component\Base\EditForm::class);

			$childComponent = new AdminFormEdit();
			$child = new $className($childComponent, $childParameters);

			$childComponent->arResult = [];
			$childComponent->arParams = $child->prepareComponentParams($childParameters);

			$children[$childName] = $child;
		}

		return $children;
	}

	public function prepareComponentParams(array $componentParameters)
	{
		return $this->mergeChildrenTabs($componentParameters);
	}

	private function mergeChildrenTabs(array $params)
	{
		if (!empty($params['TABS'])) { return $params; }

		$params['TABS'] = [];

		foreach ($this->children as $childName => $child)
		{
			$childTabs = $child->getComponentParam('TABS');

			if (empty($childTabs)) { continue; }

			foreach ($childTabs as $tab)
			{
				if (!isset($tab['fields']))
				{
					$tab['fields'] = array_keys($child->getFields());
				}

				if (empty($tab['fields'])) { continue; }

				$tab['fields'] = $this->globalSelect($childName, $tab['fields']);
				$sameTabs = array_filter($params['TABS'], static function(array $siblingTab) use ($tab) {
					return ($tab['name'] === $siblingTab['name']);
				});
				$sameTabKey = key($sameTabs);

				if ($sameTabKey !== null)
				{
					$params['TABS'][$sameTabKey]['fields'] = array_merge($params['TABS'][$sameTabKey]['fields'], $tab['fields']);
					continue;
				}

				$params['TABS'][] = $tab;
			}
		}

		usort($params['TABS'], static function($tabA, $tabB) {
			$sortA = isset($tabA['sort']) ? $tabA['sort'] : 5000;
			$sortB = isset($tabB['sort']) ? $tabB['sort'] : 5000;

			if ($sortA === $sortB) { return 0; }

			return $sortA < $sortB ? -1 : 1;
		});

		return $params;
	}

	public function getFields(array $select = [], array $item = null)
	{
		$result = [];

		foreach ($this->children as $childName => $child)
		{
			$childSelect = $this->childSelect($childName, $select);

			if ($childSelect === null) { continue; }

			list($childRow, $siblingRows) = $this->childRow($childName, $item);

			$childFields = $child->getFields($childSelect, $childRow + $siblingRows);

			foreach ($childFields as $fieldName => &$field)
			{
				$field['FIELD_NAME'] = $this->toGlobalFieldKey($childName, $field['FIELD_NAME']);
				$field['COMPOUND_KEY'] = $childName;

				if (isset($field['FIELD_GROUP']))
				{
					$field['FIELD_GROUP'] = $this->toGlobalFieldKey($childName, $field['FIELD_GROUP'], Field::GLUE_DOT);
				}

				if (!empty($field['DEPEND']))
				{
					$field['DEPEND'] = $this->globalizeDepend($childName, $field['DEPEND']);
				}

				$result[$childName . '_' . $fieldName] = $field;
			}
			unset($field);
		}

		return $result;
	}

	private function globalizeDepend($childName, array $depend)
	{
		$newDepend = [];

		foreach ($depend as $name => $rule)
		{
			if ($name === 'LOGIC')
			{
				$newDepend[$name] = $rule;
				continue;
			}

			$newDepend[$this->toGlobalFieldKey($childName, $name)] = $rule;
		}

		return $newDepend;
	}

	public function load($primary, array $select = [], $isCopy = false)
	{
		$result = [];

		foreach ($this->children as $childName => $child)
		{
			$childPrimary = isset($primary[$childName]) ? $primary[$childName] : null;
			$childSelect = $this->childSelect($childName, $select);

			if ($childSelect === null) { continue; }

			if (!empty($childPrimary))
			{
				$result[$childName] = $child->load($childPrimary, $childSelect, $isCopy);
			}
			else
			{
				$result[$childName] = $child->initial($childSelect);
			}
		}

		return $result;
	}

	public function initial(array $select = [])
	{
		$result = [];

		foreach ($this->children as $childName => $child)
		{
			$childSelect = $this->childSelect($childName, $select);

			if ($childSelect === null) { continue; }

			$result[$childName] = $child->initial($childSelect);
		}

		return $result;
	}

	public function modifyRequest(array $request, array $fields)
	{
		foreach ($this->children as $childName => $child)
		{
			$childFields = $this->childFields($childName, $fields);

			if (empty($childFields)) { continue; }

			list($childRow, $siblingRows) = $this->childRow($childName, $request);

			$request[$childName] = array_diff_key(
				$child->modifyRequest($childRow + $siblingRows, $childFields),
				$siblingRows
			);
		}

		return $request;
	}

	public function extend(array $data, array $fields)
	{
		foreach ($this->children as $childName => $child)
		{
			$childFields = $this->childFields($childName, $fields);

			if (empty($childFields)) { continue; }

			list($childRow, $siblingRows) = $this->childRow($childName, $data);

			$data[$childName] = array_diff_key(
				$child->extend($childRow + $siblingRows, $childFields),
				$siblingRows
			);
		}

		return $data;
	}

	public function validate(array $data, array $fields)
	{
		$validated = [];

		foreach ($this->children as $childName => $child)
		{
			$childFields = $this->childFields($childName, $fields);

			if (empty($fields)) { continue; }

			$childData = isset($data[$childName]) ? $data[$childName] : [];

			$validated[] = $child->validate($childData, $childFields);
		}

		return Result\Facade::merge($validated);
	}

	public function add(array $data)
	{
		return $this->save(new Main\Entity\AddResult(), null, $data);
	}

	public function update($primary, array $data)
	{
		return $this->save(new Main\Entity\UpdateResult(), $primary, $data);
	}

	private function save(Main\Entity\Result $saveResult, $primary, array $data)
	{
		$newPrimary = array_fill_keys(array_keys($this->children), null);

		if (is_array($primary)) { $newPrimary = array_merge($newPrimary, $primary); }

		foreach ($this->children as $childName => $child)
		{
			$childData = isset($data[$childName]) ? $data[$childName] : [];

			if (!empty($primary[$childName]))
			{
				$updateResult = $child->update($primary[$childName], $childData);

				if (!$updateResult->isSuccess())
				{
					$saveResult->addErrors($updateResult->getErrors());
				}

				continue;
			}

			$addResult = $child->add($childData);

			if (!$addResult->isSuccess())
			{
				$saveResult->addErrors($addResult->getErrors());
			}

			$newPrimary[$childName] = $addResult->getId();
		}

		if (
			$saveResult instanceof Main\Entity\AddResult
			|| $saveResult instanceof Main\Entity\UpdateResult
		)
		{
			$saveResult->setPrimary($newPrimary);
		}

		return $saveResult;
	}

	private function globalSelect($childName, array $select)
	{
		$result = [];

		foreach ($select as $fieldName)
		{
			$result[] = $this->toGlobalFieldKey($childName, $fieldName, Field::GLUE_DOT);
		}

		return $result;
	}

	private function childSelect($childName, array $select)
	{
		if (empty($select)) { return []; }

		$result = [];

		foreach ($select as $fieldName)
		{
			if (!$this->isChildFieldKey($childName, $fieldName, '.')) { continue; }

			$result[] = $this->toLocalFieldKey($fieldName, Field::GLUE_DOT);
		}

		if (empty($result)) { return null; }

		return $result;
	}

	private function childFields($childName, array $fields)
	{
		$result = [];

		foreach ($fields as $fieldName => $field)
		{
			if (!$this->isChildFieldKey($childName, $fieldName, '_')) { continue; }

			$result[$this->cutLocalFieldKey($fieldName)] = $field;
		}

		return $result;
	}

	private function childRow($childName, array $data = null)
	{
		if ($data === null) { return null; }

		$childRow = isset($data[$childName]) && is_array($data[$childName]) ? $data[$childName] : [];
		$siblingRows = ArrayHelper::prefixKeys(array_diff_key($data, [
			$childName => true,
		]), '@');

		return [ $childRow, $siblingRows ];
	}

	private function isChildFieldKey($childName, $fieldName, $glue)
	{
		return mb_strpos($fieldName, $childName . $glue) === 0;
	}

	private function toLocalFieldKey($fieldName, $glue = Field::GLUE_BRACKET)
	{
		$fields = Field::splitKey($fieldName, $glue);
		array_shift($fields);

		return Field::implodeKey($fields, $glue);
	}

	private function cutLocalFieldKey($fieldName)
	{
		list(, $localName) = explode('_', $fieldName, 2);

		return $localName;
	}

	private function toGlobalFieldKey($childName, $fieldName, $glue = Field::GLUE_BRACKET)
	{
		$partials = Field::splitKey($fieldName, $glue);
		array_unshift($partials, $childName);

		return Field::implodeKey($partials, $glue);
	}
}