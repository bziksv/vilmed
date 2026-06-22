<?php
namespace Yandex\Market\Api\Reference\Validator;

use Yandex\Market\Api;

class ResponseError implements Validator
{
	private $skipStatusErrorWithFields;

	public function __construct(array $skipStatusErrorWithFields = null)
	{
		$this->skipStatusErrorWithFields = $skipStatusErrorWithFields;
	}

	public function check($data, $httpStatus)
	{
		if (!is_array($data)) { return; }

		if (!empty($data['errors']))
		{
			$errors = (array)$data['errors'];

			throw $this->makeException(reset($errors), $httpStatus);
		}

		if (!empty($data['error']))
		{
			throw $this->makeException($data['error'], $httpStatus);
		}

		if (isset($data['status']) && $data['status'] === Api\Reference\ResponseWithResult::STATUS_ERROR)
		{
			if ($this->hasSkipStatusErrorFields($data)) { return; }

			throw $this->makeException('status: error', $httpStatus);
		}
	}

	private function hasSkipStatusErrorFields(array $data)
	{
		if ($this->skipStatusErrorWithFields === null) { return false; }

		foreach ($this->skipStatusErrorWithFields as $name)
		{
			if (isset($data[$name]))
			{
				return true;
			}
		}

		return false;
	}

	private function makeException($error, $httpStatus)
	{
		if (is_scalar($error))
		{
			$message = (string)$error;
			$code = (string)$error;
		}
		else if (is_array($error))
		{
			$message = isset($error['message']) ? trim($error['message']) : '';
			$code = isset($error['code']) ? (string)$error['code'] : '';
		}
		else
		{
			$message = '';
			$code = '';
		}

		if ($message === '')
		{
			$message = $code !== '' ? $code : 'Undefined response error';
		}

		if ((int)$httpStatus !== 200)
		{
			return Api\Exception\HttpExceptionFactory::make($httpStatus, $message, $code);
		}

		return new Api\Exception\ResponseError($message, $code);
	}
}