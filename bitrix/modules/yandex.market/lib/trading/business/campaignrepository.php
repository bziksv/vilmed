<?php
namespace Yandex\Market\Trading\Business;

use Bitrix\Main;
use Yandex\Market\Api;
use Yandex\Market\Data;
use Yandex\Market\State;
use Yandex\Market\Utils;
use Yandex\Market\Trading\Campaign;
use Yandex\Market\Trading\Settings;
use Yandex\Market\Reference\Concerns;

class CampaignRepository
{
	use Concerns\HasMessage;

	const THING_SYNC = 'sync';
	const THING_FAIL = 'fail';

	private $business;

	public function __construct(Model $business)
	{
		$this->business = $business;
	}

	public function actualize()
	{
		$processed = new Main\Result();

		if ($this->wasRecently(self::THING_FAIL))
		{
			$processed->addError($this->storedError());
			return $processed;
		}

		Utils\HttpConfiguration::stamp();
		Utils\HttpConfiguration::setGlobalTimeout(5, 10);

		try
		{
			$this->synchronize();
		}
		catch (Settings\Options\RequiredValueException $exception)
		{
			$error = new Main\Error($exception->getMessage());

			$this->commitWas(self::THING_FAIL, 'PT30M');
			$this->commitError($error);

			$processed->addError($error);
		}
		catch (Api\Exception\TransportException $exception)
		{
			$error = new Main\Error($exception->getMessage());

			$this->commitWas(self::THING_FAIL, 'PT10M');
			$this->commitError($error);

			$processed->addError($error);
		}

		Utils\HttpConfiguration::restore();

		return $processed;
	}

	public function getSynchronizedCollection()
	{
		$this->synchronize();

		return $this->business->getCampaignCollection();
	}

	public function synchronize($force = false)
	{
		if (!$force && $this->wasRecently(self::THING_SYNC)) { return; }

		$overlay = $this->business->getOverlay();
		$apiCampaigns = $overlay->getCampaigns();
		$warehouseGroupCollection = $overlay->getWarehouses()->getWarehouseGroups();
		$campaignCollection = $this->business->getCampaignCollection();
		$found = [];
		$new = [];

		/** @var Campaign\Model $storedCampaign */
		foreach ($campaignCollection->asArray() as $storedCampaign)
		{
			$campaignId = $storedCampaign->getId();
			/** @var Api\Campaigns\Model\Campaign|null $apiCampaign */
			$apiCampaign = $apiCampaigns->getItemById($campaignId);

			if ($apiCampaign === null)
			{
				$storedCampaign->uninstall();
				$storedCampaign->delete();
				$campaignCollection->removeItem($storedCampaign);
				continue;
			}

			$warehouseGroup = $warehouseGroupCollection->getItemByCampaignId($apiCampaign->getId());

			$storedCampaign->configureByApi($apiCampaign, $warehouseGroup);
			$found[$campaignId] = true;
		}

		/** @var Api\Campaigns\Model\Campaign|null $apiCampaign */
		foreach ($apiCampaigns as $apiCampaign)
		{
			if ($apiCampaign->getPlacementType() === null) { continue; }

			$campaignId = $apiCampaign->getId();

			if (isset($found[$campaignId])) { continue; }

			$warehouseGroup = $warehouseGroupCollection->getItemByCampaignId($apiCampaign->getId());
			$campaignCollection->addItem(Campaign\Model::fromApi($apiCampaign, $warehouseGroup));

			$new[$campaignId] = true;
		}

		/** @var Api\Campaigns\Model\Campaign|null $firstApiCampaign */
		$firstApiCampaign = $apiCampaigns->offsetGet(0);

		if ($firstApiCampaign !== null)
		{
			$this->business->setField('NAME', $firstApiCampaign->getBusiness()->getName());
		}

		$this->business->setField('EXTERNAL_SETTINGS', ExternalSettings::fromApi($overlay->getSettings())->getValues());
		$this->business->setField('CAMPAIGN', $campaignCollection->toArray());
		$this->business->save();

		foreach ($this->business->getCampaignCollection() as $storedCampaign)
		{
			$campaignId = $storedCampaign->getId();

			if (!isset($new[$campaignId])) { continue; }

			$storedCampaign->install();
		}

		$this->commitWas(self::THING_SYNC, 'PT1H');
		$this->resetWas(self::THING_FAIL);
		$this->resetError();
	}

	private function wasRecently($thing)
	{
		$wasString = (string)State::get("business_campaign_{$thing}_{$this->business->getId()}");

		if ($wasString === '') { return false; }

		$was = new Main\Type\DateTime($wasString, \DateTime::ATOM);

		return Data\DateTime::compare($was, new Main\Type\DateTime()) !== -1;
	}

	private function resetWas($thing)
	{
		State::remove("business_campaign_{$thing}_{$this->business->getId()}");
	}

	private function commitWas($thing, $shift)
	{
		$date = new Main\Type\DateTime();
		$date->add($shift);

		State::set("business_campaign_{$thing}_{$this->business->getId()}", $date->format(\DateTime::ATOM));
	}

	private function storedError()
	{
		$message = State::get("business_campaign_error_{$this->business->getId()}");

		return new Main\Error($message ?: self::getMessage('UNKNOWN_ERROR'));
	}

	private function resetError()
	{
		State::remove("business_campaign_error_{$this->business->getId()}");
	}

	private function commitError(Main\Error $error)
	{
		State::set("business_campaign_error_{$this->business->getId()}", $error->getMessage());
	}
}