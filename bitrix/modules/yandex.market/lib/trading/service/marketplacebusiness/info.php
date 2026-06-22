<?php
namespace Yandex\Market\Trading\Service\MarketplaceBusiness;

use Bitrix\Main;
use Yandex\Market\Reference\Concerns;
use Yandex\Market\Trading\Service;

class Info extends Service\Reference\Info
{
	use Concerns\HasMessage { getMessage as private getLocale; }

	public function getTitle($version = '')
	{
		$suffix = $version !== '' ? '_' . $version : '';

		return self::getLocale("TITLE{$suffix}", null, $this->provider->getCode());
	}

	public function getDescription()
	{
		return self::getLocale('DESCRIPTION', null, '');
	}

	public function getMessage($code, $replaces = null, $fallback = null)
	{
		throw new Main\NotImplementedException(static::class . '::getMessage not implemented');
	}

	public function getProfileValues()
	{
		throw new Main\NotImplementedException(static::class . '::getProfileValues not implemented');
	}

	public function getUserGroupData()
	{
		throw new Main\NotImplementedException(static::class . '::getUserGroupData not implemented');
	}

	public function getAnonymousUserData()
	{
		throw new Main\NotImplementedException(static::class . '::getAnonymousUserData not implemented');
	}

	public function getCompanyData()
	{
		throw new Main\NotImplementedException(static::class . '::getCompanyData not implemented');
	}

	public function getContactData()
	{
		throw new Main\NotImplementedException(static::class . '::getContactData not implemented');
	}
}