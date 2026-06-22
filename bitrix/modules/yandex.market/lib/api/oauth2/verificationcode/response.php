<?php
namespace Yandex\Market\Api\OAuth2\VerificationCode;

use Yandex\Market;

class Response extends Market\Api\Reference\Response
{
	public function getVerificationCode()
	{
		return $this->getField('code');
	}

	public function getState($key)
	{
		$stateList = $this->getStateList();

		return isset($stateList[$key]) ? $stateList[$key] : null;
	}

	public function getStateList()
	{
		parse_str($this->getField('state'), $state);

		return $state;
	}
}