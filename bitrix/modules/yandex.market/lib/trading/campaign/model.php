<?php
namespace Yandex\Market\Trading\Campaign;

use Bitrix\Main;
use Yandex\Market\Api;
use Yandex\Market\Trading;
use Yandex\Market\Exceptions;
use Yandex\Market\Reference\Storage;
use Yandex\Market\Reference\Concerns;

class Model extends Storage\Model
{
	use Concerns\HasOnce;
	use Concerns\HasMessage;

	private $externalSettings;
	private $trading;

	public static function getDataClass()
	{
		return Table::class;
	}

	/** @return Model */
	public static function fromApi(Api\Campaigns\Model\Campaign $apiCampaign, Api\Business\Warehouses\Model\WarehouseGroup $warehouseGroup = null)
	{
		$campaign = new static();
		$campaign->setField('ID', $apiCampaign->getId());
		$campaign->configureByApi($apiCampaign, $warehouseGroup);

		return $campaign;
	}

	public static function loadById($id)
	{
		try
		{
			$result = parent::loadById($id);
		}
		catch (Main\ObjectNotFoundException $exception)
		{
			throw new Exceptions\Trading\SetupNotFound($exception->getMessage());
		}

		return $result;
	}

	public function configureByApi(Api\Campaigns\Model\Campaign $apiCampaign, Api\Business\Warehouses\Model\WarehouseGroup $warehouseGroup = null)
	{
		$this->setFields([
			'NAME' => $apiCampaign->getDomain(),
			'PLACEMENT' => $apiCampaign->getPlacementType(),
			'EXTERNAL_SETTINGS' => ExternalSettings::fromApi($warehouseGroup)->getValues(),
		]);
	}

	public function getId()
	{
		return (int)$this->getField('ID');
	}

	public function getTitle()
	{
		$glue = self::getMessage('TITLE_GLUE', null, ' ');

		return "{$this->getPlacement()} {$glue} {$this->getName()}";
	}

	public function getName()
	{
		return (string)$this->getField('NAME');
	}

	public function setFallbackName()
	{
		$nameHolder = self::getMessage('FALLBACK_NAME');

		$this->setField('NAME', "[{$this->getId()}] {$nameHolder}");
	}

	public function getPlacement()
	{
		return (string)$this->getField('PLACEMENT');
	}

	public function install()
	{
		$trading = $this->resolveTrading();

		if ($trading === null || !$trading->isActive()) { return; }

		$trading->install();
	}

	public function uninstall()
	{
		if ($this->getTradingId() === 0) { return; }

		$trading = $this->getTrading();

		$trading->uninstall();

		if ($trading->getBehaviorCode() !== Trading\Service\Manager::BEHAVIOR_BUSINESS)
		{
			$trading->deactivate();
			$trading->save();
		}
	}

	public function getExternalSettings()
	{
		if ($this->externalSettings === null)
		{
			$this->externalSettings = new ExternalSettings($this->getField('EXTERNAL_SETTINGS'));
		}

		return $this->externalSettings;
	}

	public function isUnknown()
	{
		return (bool)$this->getField('UNKNOWN');
	}

	public function getTradingBehavior()
	{
		return (string)$this->getField('BEHAVIOR');
	}

	public function getTradingId()
	{
		return (int)$this->getField('TRADING_ID');
	}

	public function getTrading()
	{
		if ($this->trading === null)
		{
			$this->trading = $this->compileTrading();
		}

		return $this->trading;
	}

	private function compileTrading()
	{
		$parent = $this->getParent();

		if ($parent instanceof Trading\Setup\Model)
		{
			return $parent->bootCampaign($this);
		}

		return $this->requireModel('TRADING', Trading\Setup\Model::class)->bootCampaign($this);
	}

	private function resolveTrading()
	{
		$business = $this->getBusiness();
		$tradingCollection = $business->getTradingCollection();
		$campaignTrading = $tradingCollection->getItemByCampaignId($this->getId());

		if ($campaignTrading !== null && $campaignTrading->isActive())
		{
			$this->trading = $campaignTrading;

			return $this->trading;
		}

		$businessTrading = $tradingCollection->getByBehavior(Trading\Service\Manager::BEHAVIOR_BUSINESS);

		if ($businessTrading !== null && $businessTrading->isActive())
		{
			if (!in_array($this->getPlacement(), $business->getTradingRepository()->getBusinessPlacements(), true))
			{
				return null;
			}

			$this->trading = $businessTrading->bootCampaign($this);

			return $this->trading;
		}

		return null;
	}

	public function getBusinessId()
	{
		return (int)$this->getField('BUSINESS_ID');
	}

	public function getBusiness()
	{
		$parent = $this->getParent();

		if ($parent instanceof Trading\Business\Model) { return $parent; }

		return $this->requireModel('BUSINESS', Trading\Business\Model::class);
	}

	protected function getChildCollectionQueryParameters($fieldKey)
	{
		if ($fieldKey === 'TRADING')
		{
			$tradingId = $this->getTradingId();

			if ($tradingId === null) { return null; }

			return [
				'filter' => [ 'ID' => $tradingId ],
			];
		}

		if ($fieldKey === 'BUSINESS')
		{
			return [
				'filter' => [ 'ID' => $this->getBusinessId() ],
			];
		}

		return parent::getChildCollectionQueryParameters($fieldKey);
	}

	public function setField($name, $value)
	{
		parent::setField($name, $value);

		if ($name === 'EXTERNAL_SETTINGS' && $this->externalSettings !== null)
		{
			$this->externalSettings->setValues($value);
		}
	}
}