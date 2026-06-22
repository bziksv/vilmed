<?php

namespace Yandex\Market\Utils;

class ArrayHelper
{
	public static function column(array $array, $column)
	{
		$result = [];

		foreach ($array as $key => $values)
		{
			if (!isset($values[$column])) { continue; }

			$result[$key] = $values[$column];
		}

		return $result;
	}

	public static function columnToKey(array $array, $column)
	{
		$result = [];

		foreach ($array as $values)
		{
			if (!isset($values[$column])) { continue; }

			$value = $values[$column];

			if (isset($result[$value])) { continue; }

			$result[$value] = $values;
		}

		return $result;
	}

	public static function columnsHashKey(array $array, array $columns, $glue = ':')
	{
		$result = [];

		foreach ($array as $values)
		{
			$key = [];

			foreach ($columns as $column)
			{
				if (!isset($values[$column]))
				{
					$key = null;
					break;
				}

				$key[] = $values[$column];
			}

			if (empty($key)) { continue; }

			$result[implode($glue, $key)] = $values;
		}

		return $result;
	}

	public static function keysByColumn(array $array, $column)
	{
		$result = [];

		foreach ($array as $key => $values)
		{
			if (!isset($values[$column])) { continue; }

			$value = $values[$column];

			if (isset($result[$value])) { continue; }

			$result[$value] = $key;
		}

		return $result;
	}

	public static function groupByComposite(array $array, array $columns, array $fallback = null)
	{
		$result = [];

		foreach ($array as $key => $values)
		{
            $sign = [];
            $hasAny = false;

            foreach ($columns as $column)
            {
                $value = null;

                if (isset($values[$column]))
                {
                    $value = $values[$column];
                }
                else if (isset($fallback[$column]))
                {
                    $value = $fallback[$column];
                }

                if ($value !== null) { $hasAny = true; }

                $sign[] = $value;
            }

            if (!$hasAny) { continue; }

            $group = implode(':', $sign);

			if (!isset($result[$group])) { $result[$group] = []; }

			$result[$group][$key] = $values;
		}

		return $result;
	}

	public static function groupBy(array $array, $column, $fallback = null)
	{
		$result = [];

		foreach ($array as $key => $values)
		{
			$value = isset($values[$column]) ? $values[$column] : $fallback;

			if ($value === null) { continue; }

			if (!isset($result[$value])) { $result[$value] = []; }

			$result[$value][$key] = $values;
		}

		return $result;
	}

	public static function flipGroup(array $array)
	{
		$result = [];

		foreach ($array as $key => $value)
		{
			if (!isset($result[$value]))
			{
				$result[$value] = [];
			}

			$result[$value][] = $key;
		}

		return $result;
	}

	public static function flipMultidimensional(array $array)
	{
		$result = [];

		foreach ($array as $key => $children)
		{
			foreach ($children as $value)
			{
				$result[$value] = $key;
			}
		}

		return $result;
	}

	public static function firstColumn(array $array, $column)
	{
		$result = null;

		foreach ($array as $values)
		{
			if (!isset($values[$column])) { continue; }

			$result = $values[$column];
			break;
		}

		return $result;
	}

	public static function prefixKeys(array $array, $appendix)
	{
		$result = [];

		foreach ($array as $key => $value)
		{
			$result[$appendix . $key] = $value;
		}

		return $result;
	}

	public static function mergeList(array ...$parts)
	{
		if (empty($parts)) { return []; }

		$result = array_pop($parts);

		if (empty($parts)) { return $result; }

		foreach ($parts as $part)
		{
			foreach ($part as $key => $values)
			{
				if (!isset($result[$key]))
				{
					$result[$key] = $values;
				}
				else
				{
					$result[$key] += $values;
				}
			}
		}

		return $result;
	}
}