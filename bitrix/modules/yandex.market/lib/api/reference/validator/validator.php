<?php
namespace Yandex\Market\Api\Reference\Validator;

interface Validator
{
	/** @return void */
	public function check($data, $httpStatus);
}