<?php
namespace Yandex\Market\Trading\Facade;

use Yandex\Market;
use Bitrix\Main;

class Oauth
{
    /** @deprecated */
	public static function getConfiguration(Market\Api\OAuth2\Token\Model $token)
	{
		$setup = static::getSetup($token);

		if ($setup === null) { return null; }

		$options = $setup->wakeupService()->getOptions();

		Market\Reference\Assert::typeOf(
			$options,
			Market\Api\Reference\HasOauthConfiguration::class,
			'$setup->getService()->getOptions()'
		);

		return $options;
	}

    /** @deprecated */
    public static function getSetup(Market\Api\OAuth2\Token\Model $token)
	{
		$result = null;

		$setupList = Market\Trading\Setup\Model::loadList([
			'filter' => [
				'=OAUTH_CLIENT_ID.VALUE' => $token->getClientId(),
				'=OAUTH_TOKEN.VALUE' => $token->getId(),
				'API_KEY.VALUE' => false,
			],
			'runtime' => [
				new Main\Entity\ReferenceField('OAUTH_CLIENT_ID', Market\Trading\Settings\Table::class, [
					'=this.ID' => 'ref.SETUP_ID',
					'=ref.NAME' => [ '?', 'OAUTH_CLIENT_ID' ],
				]),
				new Main\Entity\ReferenceField('OAUTH_TOKEN', Market\Trading\Settings\Table::class, [
					'=this.ID' => 'ref.SETUP_ID',
					'=ref.NAME' => [ '?', 'OAUTH_TOKEN' ],
				]),
				new Main\Entity\ReferenceField('API_KEY', Market\Trading\Settings\Table::class, [
					'=this.ID' => 'ref.SETUP_ID',
					'=ref.NAME' => [ '?', 'API_KEY' ],
				]),
			],
			'order' => [ 'ACTIVE' => 'desc' ],
		]);

		foreach ($setupList as $setup)
		{
			$options = $setup->getService()->getOptions();

			if (!($options instanceof Market\Api\Reference\HasOauthConfiguration)) { continue; }

			$result = $setup;
			break;
		}

		return $result;
	}
}