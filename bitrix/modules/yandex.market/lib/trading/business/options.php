<?php
namespace Yandex\Market\Trading\Business;

use Yandex\Market\Api;
use Yandex\Market\Data;
use Yandex\Market\Trading\Settings;
use Yandex\Market\Reference\Concerns;
use Yandex\Market\Utils\UserField\DependField;
use Yandex\Market\Api\OAuth2;

class Options extends Settings\Options
	implements Api\Reference\HasAuth
{
	use Concerns\HasMessage;
	use Concerns\HasOnce;

	public function getApiAuth()
	{
		$apiKey = trim($this->getValue('API_KEY'));

		if ($apiKey !== '')
		{
			return new Api\Reference\ApiKey($apiKey);
		}

		$accessToken = $this->getOAuthAccessToken();

		if ($accessToken !== null)
		{
			return new Api\Reference\OAuth($accessToken);
		}

		throw new Settings\Options\RequiredValueException('API_KEY');
	}

	/** @return string|null */
	private function getOAuthAccessToken()
	{
		$tokenId = (int)$this->getValue('OAUTH_TOKEN');

		if ($tokenId <= 0) { return null; }

		return $this->once('getOAuthAccessToken', [ $tokenId ], function($tokenId) {
			return OAuth2\Token\Model::loadById($tokenId)->getAccessToken();
		});
	}

	public function getSkuMap()
	{
		$prefix = $this->booleanValue('PRODUCT_USE_SKU_PREFIX') ? $this->getValue('PRODUCT_SKU_PREFIX') : null;

		return new Settings\Options\SkuMap($this->getValue('PRODUCT_SKU_FIELD'), $prefix);
	}

	public function getConnectFields()
	{
		return
			$this->getApiFields()
			+ $this->getSiteFields();
	}

	public function getFields()
	{
		return
			$this->extendOAuthFields($this->getApiFields())
			+ $this->getSiteFields()
			+ $this->getProductSkuFields();
	}

	private function getApiFields()
	{
		return [
			'API_KEY' => [
				'TYPE' => 'string',
				'NAME' => self::getMessage('API_KEY'),
				'DESCRIPTION' => self::getMessage('API_KEY_DESCRIPTION'),
				'MANDATORY' => 'Y',
				'SORT' => 1000,
				'SETTINGS' => [],
			],
		];
	}

	private function extendOAuthFields(array $fields)
	{
		$tokenId = (int)$this->getValue('OAUTH_TOKEN');

		if ($tokenId <= 0) { return $fields; }

		$token = OAuth2\Token\Table::getRow([
			'filter' => [ '=ID' => $tokenId ],
			'select' => [ 'USER_LOGIN' ],
		]);

		if ($token === null) { return $fields; }

		$fields['API_KEY']['MANDATORY'] = 'N';
		$fields['API_KEY']['SETTINGS']['PLACEHOLDER'] = $token['USER_LOGIN'];

		return $fields + [
			'OAUTH_CLIENT_ID' => [
				'TYPE' => 'string',
				'HIDDEN' => 'Y',
				'SORT' => 1001,
			],
			'OAUTH_CLIENT_PASSWORD' => [
				'TYPE' => 'string',
				'HIDDEN' => 'Y',
				'SORT' => 1001,
			],
			'OAUTH_TOKEN' => [
				'TYPE' => 'number',
				'HIDDEN' => 'Y',
				'SORT' => 1001,
			],
		];
	}

	private function getSiteFields()
	{
		$sites = Data\Site::getSortedEnum();
		$firstSite = reset($sites);

		return [
			'SITE_ID' => [
				'TYPE' => 'enumeration',
				'NAME' => self::getMessage('SITE_ID'),
				'HIDDEN' => count($sites) <= 1,
				'VALUES' => $sites,
				'SETTINGS' => [
					'DEFAULT_VALUE' => $firstSite !== false ? $firstSite['ID'] : null,
					'ALLOW_NO_VALUE' => 'N',
				],
			],
		];
	}

	private function getProductSkuFields()
	{
		return [
			'PRODUCT_SKU_FIELD' => [
				'TYPE' => 'skuField',
				'MULTIPLE' => 'Y',
				'NAME' => self::getMessage('PRODUCT_SKU_FIELD'),
				'INTRO' => self::getMessage('PRODUCT_SKU_FIELD_DESCRIPTION'),
				'SORT' => 1100,
				'SETTINGS' => [
					'VALIGN_PUSH' => true,
				],
			],
			'PRODUCT_USE_SKU_PREFIX' => [
				'TYPE' => 'boolean',
				'NAME' => self::getMessage('PRODUCT_USE_SKU_PREFIX'),
				'HELP_MESSAGE' => self::getMessage('PRODUCT_USE_SKU_PREFIX_HELP'),
				'SORT' => 1101,
			],
			'PRODUCT_SKU_PREFIX' => [
				'TYPE' => 'string',
				'NAME' => self::getMessage('PRODUCT_SKU_PREFIX'),
				'SORT' => 1102,
				'DEPEND' => [
					'PRODUCT_USE_SKU_PREFIX' => [
						'RULE' => DependField::RULE_EMPTY,
						'VALUE' => false,
					],
				],
			],
		];
	}
}