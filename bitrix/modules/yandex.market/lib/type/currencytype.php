<?php
namespace Yandex\Market\Type;

use Yandex\Market\Config;
use Yandex\Market\Error;
use Yandex\Market\Reference\Concerns;

class CurrencyType extends AbstractType
{
    use Concerns\HasMessage;

	protected static $convertMap = [
		'RUB' => 'RUR'
	];
	protected static $availableValues = [
		'RUR' => true,
		'USD' => true,
		'EUR' => true,
		'UAH' => true,
		'KZT' => true,
		'BYN' => true
	];
	protected static $baseValues = [
		'RUR' => true,
		'BYN' => true,
		'UAH' => true,
		'KZT' => true
	];

    public function type()
    {
        return Manager::TYPE_CURRENCY;
    }

    public function sanitize($value, array $context = [], array $settings = null)
	{
		$value = $this->convertValue($value);

		if (!isset(static::$availableValues[$value]))
		{
            return new Error\XmlNode(self::getMessage('ERROR_INVALID'), 'INVALID');
		}

		return $value;
	}

	public function revert($value)
	{
        $result = mb_strtoupper($value);
        $mapKey = array_search($result, static::$convertMap, true);

        if ($mapKey !== false)
        {
            $result = $mapKey;
        }

        return $result;
	}

	public function getAvailableList()
	{
		return static::$availableValues;
	}

	public function getDefaultBase()
	{
		return Config::getOption('type_currency_default_base', 'RUR');
	}

	public function isBase($value)
	{
		$value = $this->convertValue($value);

		return isset(static::$baseValues[$value]);
	}

	public function getBaseList()
	{
		return static::$baseValues;
	}

	protected function convertValue($value)
	{
		$valueUpper = mb_strtoupper($value);

		if (isset(static::$convertMap[$valueUpper]))
		{
			return static::$convertMap[$valueUpper];
		}

		return $valueUpper;
	}
}