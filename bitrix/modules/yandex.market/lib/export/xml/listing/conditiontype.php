<?php

namespace Yandex\Market\Export\Xml\Listing;

use Yandex\Market\Data\TextString;
use Yandex\Market\Reference\Concerns;
use Yandex\Market\Type;

class ConditionType
	implements Listing, ListingWithMigration
{
	use Concerns\HasMessage;

	const NEW_TYPE = 'new'; // NEW is reserved word
	const PREOWNED = 'preowned';
	const SHOWCASE_SAMPLE = 'showcasesample';
	const REFURBISHED = 'refurbished';
	const REDUCTION = 'reduction';
	const RENOVATED = 'renovated';
    /** @deprecated */
	const FASHION_PREOWNED = 'fashionpreowned';

	public function values()
	{
		return [
			static::NEW_TYPE,
			static::PREOWNED,
			static::SHOWCASE_SAMPLE,
			static::REDUCTION,
			static::RENOVATED,
            static::REFURBISHED,
		];
	}

	public function display($value)
	{
		return self::getMessage(mb_strtoupper($value), null, $value);
	}

	/** @noinspection PhpDeprecationInspection */
	public function migrate($value)
	{
		if ($value === Type\ConditionType::TYPE_USED)
		{
            return static::PREOWNED;
		}

        if ($value === Type\ConditionType::TYPE_LIKE_NEW)
		{
			return static::REDUCTION;
		}

		if ($value === static::FASHION_PREOWNED)
		{
			return static::PREOWNED;
		}

		return null;
	}

	public function synonyms($value)
	{
		$message = (string)self::getMessage(TextString::toUpper($value) . '_SYNONYM', null, '');

		if ($message === '') { return []; }

		return explode(',', $message);
	}
}