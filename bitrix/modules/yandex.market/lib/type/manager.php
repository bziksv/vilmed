<?php
namespace Yandex\Market\Type;

use Yandex\Market\Reference\Assert;

class Manager
{
	const TYPE_STRING = 'string';
	const TYPE_NUMBER = 'number';
	const TYPE_HTML = 'html';
	const TYPE_DATE = 'date';
	const TYPE_PERIOD = 'period';
	const TYPE_DATEPERIOD = 'dateperiod';
	const TYPE_CURRENCY = 'currency';
	const TYPE_URL = 'url';
	const TYPE_FILE = 'file';
	const TYPE_BOOLEAN = 'boolean';
	const TYPE_CATEGORY = 'category';
	const TYPE_VAT = 'vat';
	const TYPE_BARCODE = 'barcode';
	const TYPE_AGE = 'age';
	const TYPE_DIMENSIONS = 'dimensions';
	const TYPE_RECORDING_LENGTH = 'recordinglength';
	const TYPE_ENUM = 'enum';
	/** @deprecated */
	const TYPE_CONDITION = 'condition';
    /** @deprecated */
	const TYPE_DELIVERY_OPTIONS = 'deliveryoptions';
	const TYPE_DAYS = 'days';
	const TYPE_COUNT = 'count';
	const TYPE_TN_VED_CODE = 'tnVedCode';
	const TYPE_WEEKDAY = 'weekday';
	const TYPE_CARD_PARAMETERS = 'cardParameters';

	/** @return AbstractType */
	public static function getType($type)
	{
        /** @var class-string<AbstractType> $className */
        $className = __NAMESPACE__ . '\\' . $type . 'Type';

        Assert::isSubclassOf($className, AbstractType::class);

		return new $className;
	}

    /** @deprecated */
    public static function release() {}
}