<?php

namespace Yandex\Market\Utils\UserField;

use Bitrix\Main;
use Yandex\Market;

class DependField
{
	const RULE_ANY = 'ANY';
	const RULE_EXCLUDE = 'EXCLUDE';
	const RULE_EMPTY = 'EMPTY';

	public static function test(array $rules, $values)
	{
		$logicMatchAny = (isset($rules['LOGIC']) && $rules['LOGIC'] === 'OR');
		$result = !$logicMatchAny;

		foreach ($rules as $fieldName => $rule)
		{
			if ($fieldName === 'LOGIC') { continue; }

			$isMatch = self::testItem($fieldName, $rule, $values);

			if ($logicMatchAny === $isMatch)
			{
				$result = $isMatch;
				break;
			}
		}

		return $result;
	}

	protected static function testItem($fieldName, array $rule, $values)
	{
		if (is_numeric($fieldName))
		{
			return self::test($rule, $values);
		}

		if (mb_strpos($fieldName, '@') === 0)
		{
			$parent = isset($values['PARENT_ROW']) ? $values['PARENT_ROW'] : [];

			$value = Market\Utils\Field::getChainValue($parent, mb_substr($fieldName, 1), Market\Utils\Field::GLUE_BRACKET);
		}
		else
		{
			$value = Market\Utils\Field::getChainValue($values, $fieldName, Market\Utils\Field::GLUE_BRACKET);
		}

		if ($rule['RULE'] === static::RULE_EMPTY)
		{
			return (static::testIsEmpty($value) === $rule['VALUE']);
		}

		if ($rule['RULE'] === static::RULE_ANY)
		{
			return static::applyRuleAny($rule['VALUE'], $value);
		}

		if ($rule['RULE'] === static::RULE_EXCLUDE)
		{
			return !static::applyRuleAny($rule['VALUE'], $value);
		}

		return true;
	}

	protected static function testIsEmpty($value)
	{
		$result = true;

		if (is_array($value))
		{
			foreach ($value as $one)
			{
				if (!static::testIsEmpty($one))
				{
					$result = false;
					break;
				}
			}
		}
		else
		{
			$result = Market\Utils\Value::isEmpty($value) || (is_scalar($value) && (string)$value === '0');
		}

		return $result;
	}

	protected static function applyRuleAny($ruleValue, $formValue)
	{
		$isRuleMultiple = is_array($ruleValue);
		$isFormMultiple = is_array($formValue);

		if ($isFormMultiple && $isRuleMultiple)
		{
			$intersect = array_intersect($ruleValue, $formValue);
			$result = !empty($intersect);
		}
		else if ($isFormMultiple)
		{
			/** @noinspection TypeUnsafeArraySearchInspection */
			$result = in_array($ruleValue, $formValue);
		}
		else if ($isRuleMultiple)
		{
			/** @noinspection TypeUnsafeArraySearchInspection */
			$result = in_array($formValue, $ruleValue);
		}
		else
		{
			/** @noinspection TypeUnsafeComparisonInspection */
			$result = ($formValue == $ruleValue);
		}

		return $result;
	}
}