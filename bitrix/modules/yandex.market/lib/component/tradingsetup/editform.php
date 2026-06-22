<?php
namespace Yandex\Market\Component\TradingSetup;

use Yandex\Market\Config;
use Yandex\Market\Component;
use Yandex\Market\Ui;
use Yandex\Market\Data;
use Yandex\Market\Utils;
use Yandex\Market\Reference\Concerns;
use Yandex\Market\Trading\Business;
use Yandex\Market\Trading\Campaign;
use Yandex\Market\Trading\Setup;
use Bitrix\Main;

class EditForm extends Component\Plain\EditForm
{
	use Concerns\HasMessage;

	const SESSION_KEY = 'yamarket_connect_campaign';

	public function getFields(array $select = [], array $item = null)
	{
		$businesses = $this->businesses();
		$campaignGroups = $this->campaignGroups($businesses);
		$fields = $this->businessFields($businesses, $campaignGroups);
		$fields += $this->campaignFields($campaignGroups);
		$fields += $this->siteFields($businesses);

		return $this->prepareFields($fields);
	}

	private function businesses()
	{
		$businesses = Business\Model::loadList([
			'filter' => Ui\Trading\Menu::businessFilter($this->getComponentParam('BUSINESS_ID'), 'ID'),
		]);

		if (empty($businesses))
		{
			throw new Main\SystemException(self::getMessage('BUSINESS_MISSING', [
				'#URL#' => Ui\Admin\Path::getModuleUrl('trading_connect'),
			]));
		}

		return $businesses;
	}

	private function businessFields(array $businesses, array $campaignGroups)
	{
		$businessEnum = [];
		$businessIdParameter = (int)$this->getComponentParam('BUSINESS_ID');
		$hasOtherBusiness = false;

		/** @var Business\Model $business */
		foreach ($businesses as $business)
		{
			$id = $business->getId();

			if (!isset($campaignGroups[$id])) { continue; }

			if ($id !== $businessIdParameter)
			{
				$hasOtherBusiness = true;
			}

			$businessEnum[] = [
				'ID' => $id,
				'VALUE' => "{$business->getName()} [{$id}] ",
			];
		}

		return [
			'BUSINESS_ID' => [
				'TYPE' => 'enumeration',
				'NAME' => self::getMessage('BUSINESS_ID'),
				'HIDDEN' => $hasOtherBusiness ? 'N' : 'Y',
				'VALUES' => $businessEnum,
				'MANDATORY' => 'Y',
			],
		];
	}

	private function campaignGroups(array $businesses)
	{
		$lastException = null;
		$groups = [];

		Utils\HttpConfiguration::stamp();
		Utils\HttpConfiguration::setGlobalTimeout(5, 10);

		/** @var Business\Model $business */
		foreach ($businesses as $business)
		{
			try
			{
				$groups[$business->getId()] = $business->getCampaignRepository()->getSynchronizedCollection();
 			}
			catch (Main\SystemException $exception)
			{
				$lastException = $exception;
			}
		}

		Utils\HttpConfiguration::restore();

		if ($lastException !== null && empty($groups))
		{
			throw $lastException;
		}

		return $groups;
	}

	private function campaignFields(array $campaignGroups)
	{
		$result = [];

		/** @var Campaign\Collection $campaignGroup */
		foreach ($campaignGroups as $businessId => $campaignGroup)
		{
			$campaignEnum = [];

			/** @var Campaign\Model $campaign */
			foreach ($campaignGroup as $campaign)
			{
				$campaignEnum[] = [
					'ID' => $campaign->getId(),
					'VALUE' => $campaign->getTitle(),
				];
			}

			$result["CAMPAIGN_ID_{$businessId}"] = [
				'TYPE' => 'enumeration',
				'NAME' => self::getMessage('CAMPAIGN_ID'),
				'VALUES' => $campaignEnum,
				'MANDATORY' => 'Y',
				'DEPEND' => [
					'BUSINESS_ID' => [
						'RULE' => Utils\UserField\DependField::RULE_ANY,
						'VALUE' => (int)$businessId,
					],
				],
			];
		}

		return $result;
	}

	private function siteFields(array $businesses)
	{
		$sites = Data\Site::getSortedEnum();

		return array_diff_key([
			'SITE_ID' => [
				'TYPE' => 'enumeration',
				'NAME' => self::getMessage('SITE_ID'),
				'HIDDEN' => count($sites) <= 1,
				'VALUES' => $sites,
				'SETTINGS' => [
					'DEFAULT_VALUE' => $this->siteDefault($businesses, $sites),
					'ALLOW_NO_VALUE' => 'N',
				],
			],
			'URL_ID' => [
				'TYPE' => 'string',
				'NAME' => self::getMessage('URL_ID'),
				'SETTINGS' => [
					'MAX_LENGTH' => 10,
				],
			],
		], array_filter([
			'URL_ID' => (Config::getOption('trading_yandex_mode') !== 'Y'),
		]));
	}

	private function siteDefault(array $businesses, array $sites)
	{
		$siteMap = array_column($sites, 'ID', 'ID');

		/** @var Business\Model $business */
		foreach ($businesses as $business)
		{
			$siteId = $business->getSiteId();

			if (isset($siteMap[$siteId]))
			{
				return $siteId;
			}
		}

		$firstSite = reset($siteMap);

		return $firstSite !== false ? $firstSite : null;
	}

	public function load($primary, array $select = [], $isCopy = false)
	{
		throw new Main\NotImplementedException('load campaign for edit unsupported');
	}

	public function initial(array $select = [])
	{
		$businessId = (int)$this->getComponentParam('BUSINESS_ID');

		if ($businessId > 0)
		{
			return [ 'BUSINESS_ID' => $businessId ];
		}

		return [];
	}

	public function add(array $data)
	{
		try
		{
			$businessId = (int)$data['BUSINESS_ID'];
			$campaignId = (int)$data['CAMPAIGN_ID_' . $businessId];

			$this->checkExists($businessId, $campaignId);

			$business = Business\Model::loadById($data['BUSINESS_ID']);

			$this->checkBusinessCampaign($business, $campaignId);

			return $this->saveToSession([
				'BUSINESS_ID' => $businessId,
				'CAMPAIGN_ID' => $campaignId,
			] + array_diff_key($data, [
				'CAMPAIGN_ID_' . $businessId => true,
			]));
		}
		catch (Main\SystemException $exception)
		{
			$result = new Main\Entity\AddResult();
			$result->addError(new Main\Error(
				$exception->getMessage()
			));

			return $result;
		}
	}

	private function checkExists($businessId, $campaignId)
	{
		$exists = Setup\Table::getRow([
			'filter' => [
				'=BUSINESS_ID' => $businessId,
				'=CAMPAIGN_ID' => $campaignId,
			],
		]);

		if ($exists !== null)
		{
			throw new Main\SystemException(self::getMessage('CAMPAIGN_ALREADY_EXISTS', [
				'#EDIT_URL#' => Ui\Admin\Path::getModuleUrl('trading_edit', [ 'id' => $exists['ID'] ]),
			]));
		}
	}

	private function checkBusinessCampaign(Business\Model $business, $campaignId)
	{
		$campaign = $business->getCampaignCollection()->getItemById($campaignId);

		if ($campaign === null)
		{
			throw new Main\SystemException(self::getMessage('CAMPAIGN_NOT_LOADED', [
				'#CAMPAIGN_ID#' => $campaignId,
			]));
		}
	}

	private function saveToSession(array $data)
	{
		/** @noinspection PhpDeprecationInspection */
		$primary = randString(3);

		$_SESSION[self::SESSION_KEY][$primary] = $data;

		$addResult = new Main\Entity\AddResult();
		$addResult->setId($primary);

		return $addResult;
	}

	public function update($primary, array $data)
	{
		throw new Main\NotImplementedException('update campaign unsupported');
	}
}