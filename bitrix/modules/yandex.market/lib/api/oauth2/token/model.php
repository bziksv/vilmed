<?php
namespace Yandex\Market\Api\OAuth2\Token;

use Yandex\Market;

class Model extends Market\Reference\Storage\Model
{
	public static function getDataClass()
	{
		return Table::class;
	}

	public function getClientId()
	{
		return $this->getField('CLIENT_ID');
	}

	public function getLogin()
	{
		return $this->getField('USER_LOGIN');
	}

	public function getAccessToken()
	{
		return $this->getField('ACCESS_TOKEN');
	}

	public function getRefreshToken()
	{
		return $this->getField('REFRESH_TOKEN');
	}

	/** @noinspection PhpUnused */
	public function getExpiresDate()
	{
		return $this->getField('EXPIRES_AT');
	}

	public function canRefresh()
	{
		return $this->getRefreshCount() <= Market\Api\OAuth2\RefreshToken\Agent::getRefreshLimit();
	}

	public function incrementRefreshCount()
	{
		$current = $this->getRefreshCount();

		$this->setField('REFRESH_COUNT', $current + 1);
	}

	public function getRefreshCount()
	{
		return (int)$this->getField('REFRESH_COUNT');
	}

	/** @noinspection PhpUnused */
	public function getRefreshMessage()
	{
		return (string)$this->getField('REFRESH_MESSAGE');
	}
}
