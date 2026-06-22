<?php
namespace Yandex\Market\Api\Reference\Validator;

use Yandex\Market\Api;
use Yandex\Market\Utils\Field;

class RequiredKeys implements Validator
{
	const POSITIVE_NUMBER = 'positiveNumber';
	const NON_EMPTY_STRING = 'nonEmptyString';

	private $constrains;

	public function __construct(array $constrains)
	{
		$this->constrains = $constrains;
	}

	public function check($data, $httpStatus)
	{
		if (!is_array($data)) { throw new Api\Exception\ResponseError('Unknown format: ' . $this->cutValue($data)); }

		foreach ($this->constrains as $key => $rule)
		{
			if (is_numeric($key))
			{
				$key = $rule;
				$rule = null;
			}

			if (!Field::hasChainValue($data, $key))
			{
				throw new Api\Exception\ResponseError(sprintf('Response %s missing', $key));
			}

			if ($rule === null) { continue; }

			$value = Field::getChainValue($data, $key);

			if ($rule === self::POSITIVE_NUMBER)
			{
				if (!is_numeric($value))
				{
					throw new Api\Exception\ResponseError(sprintf('Response %s %s must be numeric', $key, $this->cutValue($value)));
				}

				$value = (int)$value;

				if ($value <= 0)
				{
					throw new Api\Exception\ResponseError(sprintf('Response %s %s must be positive number', $key, $this->cutValue($value)));
				}

				continue;
			}

			if ($rule === self::NON_EMPTY_STRING)
			{
				if (!is_string($value))
				{
					throw new Api\Exception\ResponseError(sprintf('Response %s %s must be a string', $key, $this->cutValue($value)));
				}

				if ($value === '')
				{
					throw new Api\Exception\ResponseError(sprintf('Response %s must be not empty string', $key));
				}

				continue;
			}

			if (is_array($rule))
			{
				if (!in_array($value, $rule, true))
				{
					throw new Api\Exception\ResponseError(sprintf(
						'Response %s %s must be one of %s',
						$key,
						$this->cutValue($value),
						implode(',', $rule)
					));
				}

				continue;
			}

			if (is_callable($rule))
			{
				/** @noinspection VariableFunctionsUsageInspection */
				call_user_func($rule, $value, $data);
			}
		}
	}

	private function cutValue($value)
	{
		return (is_scalar($value) ? mb_substr($value, 0, 30) : gettype($value));
	}
}