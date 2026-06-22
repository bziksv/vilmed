<?php
namespace Yandex\Market\Trading\Settings;

use Yandex\Market\Utils;
use Yandex\Market\Ui\UserField;

abstract class Options
{
	protected $values;

	abstract public function getFields();

	public function extendValues(array $values)
	{
		$values = array_merge((array)$this->values, $values);

		$this->setValues($values);
	}

	public function setValues(array $values)
	{
		$this->values = $values;
		$this->applyValues();
	}

	protected function applyValues()
	{
		// nothing by default
	}

	protected function booleanValue($key, $default = null)
	{
		if ($default === true) { $default = UserField\BooleanType::VALUE_Y; }
		if ($default === false) { $default = UserField\BooleanType::VALUE_N; }

		$value = $this->getValue($key, $default);

		return ((string)$value === UserField\BooleanType::VALUE_Y);
	}

	public function getValue($key, $default = null)
	{
		return isset($this->values[$key]) ? $this->values[$key] : $default;
	}

	public function requireValue($key, $default = null)
	{
		$value = $this->getValue($key, $default);

		if (Utils\Value::isEmpty($value))
		{
			throw new Options\RequiredValueException($key);
		}

		return $value;
	}

	public function getValues()
	{
		return $this->values;
	}
}