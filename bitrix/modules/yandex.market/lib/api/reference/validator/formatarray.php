<?php
namespace Yandex\Market\Api\Reference\Validator;

use Yandex\Market\Api;

class FormatArray implements Validator
{
	public function check($data, $httpStatus)
	{
		if (!is_array($data))
		{
			$dataPreview = (is_scalar($data) ? mb_substr($data, 0, 30) : gettype($data));

			throw new Api\Exception\ResponseError('Unknown format: ' . $dataPreview);
		}
	}
}