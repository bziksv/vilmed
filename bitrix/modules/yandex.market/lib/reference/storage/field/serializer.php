<?php

namespace Yandex\Market\Reference\Storage\Field;

class Serializer
{
	public static function getParameters($nullable = false)
	{
		$result = [
			'save_data_modification' => [static::class, 'getSaveModification'],
			'fetch_data_modification' => [static::class, 'getFetchModification'],
		];

        if (!$nullable)
        {
            $result['default_value'] = ''; // initialize modifiers for sql_mode=STRICT
        }

        return $result;
	}

	public static function getSaveModification()
	{
		return [
			[static::class, 'serialize']
		];
	}

	public static function getFetchModification()
	{
		return [
			[static::class, 'unserialize'],
		];
	}

	public static function serialize($value)
	{
		if (is_array($value))
		{
			$result = serialize($value);
		}
		else
		{
			$result = '';
		}

		return $result;
	}

	public static function unserialize($value)
	{
		if ((string)$value !== '')
		{
			$result = unserialize($value);
		}
		else
		{
			$result = null;
		}

		return $result;
	}
}