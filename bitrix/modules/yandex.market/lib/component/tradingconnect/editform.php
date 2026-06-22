<?php
namespace Yandex\Market\Component\TradingConnect;

use Bitrix\Main;
use Yandex\Market\Api;
use Yandex\Market\Api\Reference\ApiKey;
use Yandex\Market\Component;
use Yandex\Market\Logger\Trading\Logger;
use Yandex\Market\Reference\Concerns;
use Yandex\Market\Trading;

class EditForm extends Component\Plain\EditForm
{
	use Concerns\HasMessage;

	const SESSION_KEY = 'yamarket_connect';

	public function getFields(array $select = [], array $item = null)
	{
		$fields = (new Trading\Business\Options())->getConnectFields();
		$fields = $this->prepareFields($fields);

		return $fields;
	}

	public function add(array $data)
	{
		$apiKey = new ApiKey(trim($data['API_KEY']));
		$campaigns = $this->campaigns($apiKey);
		$business = $this->business($campaigns);

		if ($existResult = $this->checkExists($business))
		{
			return $existResult;
		}

		$overlay = new Api\Overlay\Business($business->getId(), $apiKey, new Logger());

		return $this->saveToSession($business, $campaigns, $overlay, $data);
	}

	private function campaigns(ApiKey $apiKey)
	{
		$campaigns = Api\Campaigns\Facade::campaigns($apiKey, new Logger());

		return $campaigns->filter(static function(Api\Campaigns\Model\Campaign $campaign) {
			return $campaign->getPlacementType() !== null;
		});
	}

	private function business(Api\Campaigns\Model\CampaignCollection $campaigns)
	{
		/** @var Api\Campaigns\Model\Campaign $campaign */
		$campaign = $campaigns->offsetGet(0);

		if ($campaign === null)
		{
			throw new Main\SystemException('No campaigns for business');
		}

		return $campaign->getBusiness();
	}

	private function checkExists(Api\Campaigns\Model\Business $business)
	{
		$setup = Trading\Setup\Table::getRow([
			'filter' => [
				'=TRADING_SERVICE' => Trading\Service\Manager::SERVICE_MARKETPLACE,
				'=TRADING_BEHAVIOR' => Trading\Service\Manager::BEHAVIOR_BUSINESS,
				'=BUSINESS_ID' => $business->getId(),
			],
			'select' => [ 'ID' ],
		]);

		if ($setup === null) { return null; }

		$addResult = new Main\Entity\AddResult();
		$addResult->addError(new Main\Error(self::getMessage('ALREADY_EXISTS', [
			'#NAME#' => "[{$business->getId()}] {$business->getName()}",
			'#URL#' => str_replace('#ID#', $setup['ID'], $this->getComponentParam('EDIT_URL')),
		])));

		return $addResult;
	}

	private function saveToSession(
		Api\Campaigns\Model\Business $business,
		Api\Campaigns\Model\CampaignCollection $campaignCollection,
		Api\Overlay\Business $overlay,
		array $fields
	)
	{
		/** @noinspection PhpDeprecationInspection */
		$primary = randString(3);

		$_SESSION[self::SESSION_KEY][$primary] = $fields + [
			'BUSINESS' => [
				'ID' => $business->getId(),
				'NAME' => $business->getName(),
			],
			'CAMPAIGN' => Trading\Campaign\Collection::fromApi($campaignCollection, $overlay->getWarehouses()->getWarehouseGroups())->toArray(),
			'EXTERNAL_SETTINGS' => Trading\Business\ExternalSettings::fromApi($overlay->getSettings())->getValues(),
		];

		$addResult = new Main\Entity\AddResult();
		$addResult->setId($primary);

		return $addResult;
	}

	public function load($primary, array $select = [], $isCopy = false)
	{
		throw new Main\NotImplementedException(self::class . '::load not implemented');
	}

	public function update($primary, array $data)
	{
		throw new Main\NotImplementedException(self::class . '::update not implemented');
	}
}