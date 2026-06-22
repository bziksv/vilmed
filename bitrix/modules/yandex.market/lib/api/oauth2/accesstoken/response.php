<?php
namespace Yandex\Market\Api\OAuth2\AccessToken;

use Bitrix\Main;
use Yandex\Market;

class Response extends Market\Api\Reference\Response
{
	const TOKEN_TYPE = 'bearer';

	/** @var Main\Type\DateTime */
	protected $initialDate;

	public function __construct($data)
	{
		parent::__construct($data);
		$this->initialDate = new Main\Type\DateTime();
	}

	public function getTokenType()
	{
		return (string)$this->requireField('token_type');
	}

	public function getAccessToken()
	{
		return (string)$this->requireField('access_token');
	}

	public function getRefreshToken()
	{
		return (string)$this->requireField('refresh_token');
	}

	public function getExpiresDate()
	{
		$result = clone $this->initialDate;
		$expireSeconds = $this->getExpiresSeconds();

		$result->add('T' . $expireSeconds . 'S');

		return $result;
	}

	public function getExpiresSeconds()
	{
		return (int)$this->getField('expires_in');
	}
}