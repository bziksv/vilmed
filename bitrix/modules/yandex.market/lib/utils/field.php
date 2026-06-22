<?php
namespace Yandex\Market\Utils;

use Bitrix\Main;

class Field
{
	const GLUE_DOT = 'dot';
	const GLUE_BRACKET = 'bracket';

	public static function hasChainValue($values, $key, $glue = Field::GLUE_DOT)
	{
		$keyParts = static::splitKey($key, $glue);
		$lastKey = array_pop($keyParts);
		$lastLevel = static::getChainValue($values, $keyParts, $glue);

		return is_array($lastLevel) && array_key_exists($lastKey, $lastLevel);
	}

	public static function getChainValue($values, $key, $glue = Field::GLUE_DOT)
	{
		$keyParts = static::splitKey($key, $glue);
		$lastLevel = $values;

		foreach ($keyParts as $keyPart)
		{
			if (isset($lastLevel[$keyPart]))
			{
				$lastLevel = $lastLevel[$keyPart];
			}
			else
			{
				$lastLevel = null;
				break;
			}
		}

		return $lastLevel;
	}

	public static function setChainValue(&$values, $key, $value, $glue = Field::GLUE_DOT)
	{
		$keyParts = static::splitKey($key, $glue);
		$keyPartIndex = 0;
		$keyPartCount = count($keyParts);
		$lastLevel = &$values;

		foreach ($keyParts as $keyPart)
		{
			if ($keyPartCount === $keyPartIndex + 1)
			{
				$lastLevel[$keyPart] = $value;
			}
			else
			{
				if (!isset($lastLevel[$keyPart]) || !is_array($lastLevel[$keyPart]))
				{
					$lastLevel[$keyPart] = [];
				}

				$lastLevel = &$lastLevel[$keyPart];
			}

			$keyPartIndex++;
		}
	}

	public static function pushChainValue(&$values, $key, $value, $glue = Field::GLUE_DOT)
	{
		$keyParts = static::splitKey($key, $glue);
		$keyPartIndex = 0;
		$keyPartCount = count($keyParts);
		$lastLevel = &$values;

		foreach ($keyParts as $keyPart)
		{
			if ($keyPartCount === $keyPartIndex + 1)
			{
				if (!isset($lastLevel[$keyPart]))
				{
					$lastLevel[$keyPart] = [];
				}

				$lastLevel[$keyPart][] = $value;
			}
			else
			{
				if (!isset($lastLevel[$keyPart]) || !is_array($lastLevel[$keyPart]))
				{
					$lastLevel[$keyPart] = [];
				}

				$lastLevel = &$lastLevel[$keyPart];
			}

			$keyPartIndex++;
		}
	}

	public static function unsetChainValue(&$values, $key, $glue = Field::GLUE_DOT)
	{
		$keyParts = static::splitKey($key, $glue);
		$keyPartIndex = 0;
		$keyPartCount = count($keyParts);
		$lastLevel = &$values;

		foreach ($keyParts as $keyPart)
		{
			if (!isset($lastLevel[$keyPart]))
			{
				break;
			}

			if ($keyPartCount === $keyPartIndex + 1)
			{
				unset($lastLevel[$keyPart]);
			}
			else
			{
				$lastLevel = &$lastLevel[$keyPart];
			}

			$keyPartIndex++;
		}
	}

	public static function splitKey($key, $glue = Field::GLUE_DOT)
	{
		if (is_array($key))
		{
			return $key;
		}

		if ($glue === static::GLUE_DOT)
		{
			return explode('.', $key);
		}

		if ($glue === static::GLUE_BRACKET)
		{
			return static::splitKeyByBrackets($key);
		}

		throw new Main\ArgumentException(sprintf('unknown glue %s', $glue));
	}

	public static function implodeKey(array $partials, $glue = Field::GLUE_DOT)
	{
		if ($glue === static::GLUE_DOT)
		{
			return implode('.', $partials);
		}

		if ($glue === static::GLUE_BRACKET)
		{
			$base = array_shift($partials);
			$children = array_map(static function($part) { return "[{$part}]"; }, $partials);

			return $base . implode('', $children);
		}

		throw new Main\ArgumentException(sprintf('unknown glue %s', $glue));
	}

	protected static function splitKeyByBrackets($key)
	{
		$keyOffset = 0;
		$keyLength = mb_strlen($key);
		$keyChain = [];

		do
		{
			if ($keyOffset === 0)
			{
				$arrayEnd = mb_strpos($key, '[');

				if ($arrayEnd === false)
				{
					$keyPart = $key;
					$keyOffset = $keyLength;
				}
				else if ($arrayEnd === 0)
				{
					$keyOffset = 1;
					continue;
				}
				else
				{
					$keyPart = mb_substr($key, $keyOffset, $arrayEnd - $keyOffset);
					$keyOffset = $arrayEnd + 1;
				}
			}
			else
			{
				$arrayEnd = mb_strpos($key, ']', $keyOffset);

				if ($arrayEnd === false)
				{
					$keyPart = mb_substr($key, $keyOffset);
					$keyOffset = $keyLength;
				}
				else
				{
					$keyPart = mb_substr($key, $keyOffset, $arrayEnd - $keyOffset);
					$keyOffset = $arrayEnd + 2;
				}
			}

			if ((string)$keyPart !== '')
			{
				$keyChain[] = $keyPart;
			}
			else
			{
				break;
			}
		}
		while ($keyOffset < $keyLength);

		return $keyChain;
	}
}