<?php
namespace Yandex\Market\Trading\Setup;

use Bitrix\Main;
use Yandex\Market;
use Yandex\Market\Reference\Assert;
use Yandex\Market\Reference\Concerns;
use Yandex\Market\Trading\Service as TradingService;

class Model extends Market\Reference\Storage\Model
{
	use Concerns\HasMessage;

	protected $environment;
	protected $service;
	protected $isServiceReady;

	public static function getDataClass()
	{
		return Table::class;
	}

	public static function loadById($id)
	{
		try
		{
			$result = parent::loadById($id);
		}
		catch (Main\ObjectNotFoundException $exception)
		{
			throw new Market\Exceptions\Trading\SetupNotFound($exception->getMessage());
		}

		return $result;
	}

	public static function loadByTradingInfo(array $tradingInfo)
	{
		if (isset($tradingInfo['CAMPAIGN_ID']))
		{
			return Market\Trading\Campaign\ModelPool::getById($tradingInfo['CAMPAIGN_ID'])->getTrading();
		}

		if (isset($tradingInfo['SETUP_ID']))
		{
			return static::loadById($tradingInfo['SETUP_ID']);
		}

		if (isset($tradingInfo['TRADING_PLATFORM_ID'], $tradingInfo['SITE_ID']))
		{
			return static::loadByExternalIdAndSite($tradingInfo['TRADING_PLATFORM_ID'], $tradingInfo['SITE_ID']);
		}

		throw new Main\ArgumentException('unknown trading info format');
	}

	/** @return Model */
	public static function loadByServiceAndSite($serviceCode, $siteId, $behaviorCode = null)
	{
		$list = static::loadList([
			'filter' => [
				'=TRADING_SERVICE' => $serviceCode,
				'=TRADING_BEHAVIOR' => $behaviorCode ?: TradingService\Manager::BEHAVIOR_DEFAULT,
				'=SITE_ID' => $siteId,
			],
			'order' => [ 'ID' => 'ASC' ], // compatibility
			'limit' => 1,
		]);

		if (empty($list))
		{
			throw new Market\Exceptions\Trading\SetupNotFound(self::getMessage('NOT_FOUND'));
		}

		return reset($list);
	}

	public static function loadByServiceAndUrlId($serviceCode, $urlId, $behaviorCode = null)
	{
		$list = static::loadList([
			'filter' => [
				'=TRADING_SERVICE' => $serviceCode,
				'=TRADING_BEHAVIOR' => $behaviorCode ?: TradingService\Manager::BEHAVIOR_DEFAULT,
				'=CODE' => $urlId,
			],
			'limit' => 1,
		]);

		if (empty($list))
		{
			throw new Market\Exceptions\Trading\SetupNotFound(self::getMessage('NOT_FOUND'));
		}

		return reset($list);
	}

	/** @return Model */
	public static function loadByExternalIdAndSite($externalId, $siteId)
	{
		$list = static::loadList([
			'filter' => [
				'=EXTERNAL_ID' => $externalId,
				'=SITE_ID' => $siteId,
			],
			'order' => [ 'ID' => 'ASC' ], // compatibility
			'limit' => 1,
		]);

		if (empty($list))
		{
			throw new Market\Exceptions\Trading\SetupNotFound(self::getMessage('NOT_FOUND'));
		}

		return reset($list);
	}

	public function getId()
	{
		$id = $this->getField('ID');

		if ($id === null || $id === '') { return null; }

		return (int)$id;
	}

	public function isInstalled()
	{
		return ($this->getId() > 0);
	}

	public function install()
	{
		if ($this->isDeprecated())
		{
			throw new Main\SystemException('cant install deprecated service');
		}

		$campaignId = $this->getCampaignId();
		$tradingRepository = $this->getBusiness()->getTradingRepository();

		if ($campaignId > 0 && $this->getBehaviorCode() !== TradingService\Manager::BEHAVIOR_BUSINESS)
		{
			$this->getBusiness()->getTradingRepository()->uninstallBusinessCampaign($campaignId);
		}

		$this->wakeupService()->getInstaller()->install();
		$tradingRepository->installPlatform($this->getEnvironment());
		$tradingRepository->linkMenuBusinessBehavior($this);
	}

	public function uninstall()
	{
		$this->wakeupService()->getInstaller()->uninstall([
			'SERVICE_USED' => Facade::hasActiveSetupUsingServiceCode($this->getServiceCode(), $this->getId()),
		]);

		if ($this->isDeprecated()) { return; }

		$campaignId = $this->getCampaignId();
		$tradingRepository = $this->getBusiness()->getTradingRepository();

		$tradingRepository->unlinkPlatform($this->getEnvironment(), $this->getId());
		$tradingRepository->unlinkMenuBusinessBehavior($this);

		if ($campaignId > 0 && $this->getBehaviorCode() !== TradingService\Manager::BEHAVIOR_BUSINESS)
		{
			$tradingRepository->installBusinessCampaign($campaignId);
		}
	}

	/** @deprecated */
	public function getDefaultName()
	{
		$service = $this->getService();

		return sprintf('%s (%s)', $service->getInfo()->getTitle(), $this->getSiteId());
	}

	public function migrate(TradingService\Reference\Provider $service)
	{
		$this->wakeupService()->getInstaller()->migrate($service);
		$this->setField('TRADING_SERVICE', $service->getServiceCode());
		$this->setField('TRADING_BEHAVIOR', $service->getBehaviorCode());
	}

	public function isActive()
	{
		return (string)$this->getField('ACTIVE') === Table::BOOLEAN_Y;
	}

	public function isDeprecated()
	{
		return TradingService\Migration::isDeprecated($this->getServiceCode());
	}

	public function activate()
	{
		if ($this->isDeprecated())
		{
			throw new Main\SystemException('cant activate deprecated service');
		}

		$this->setField('ACTIVE', Table::BOOLEAN_Y);
	}

	public function deactivate()
	{
		$this->setField('ACTIVE', Table::BOOLEAN_N);
		$this->save();
	}

	public function reset()
	{
		$this->setField('SETTINGS', []);
	}

	public function getServiceCode()
	{
		return $this->getField('TRADING_SERVICE');
	}

	public function getBehaviorCode()
	{
		return $this->getField('TRADING_BEHAVIOR') ?: TradingService\Manager::BEHAVIOR_DEFAULT;
	}

	public function getSiteId()
	{
		return $this->getField('SITE_ID');
	}

	/** @deprecated */
	public function getExternalId()
	{
		return $this->getBusiness()->getPlatformId();
	}

	public function getUrlId()
	{
		return $this->getField('CODE');
	}

	public function getEnvironment()
	{
		if ($this->environment === null)
		{
			$this->environment = Market\Trading\Entity\Manager::createEnvironment();
		}

		return $this->environment;
	}

	public function getPlatform()
	{
		$platform = $this->getEnvironment()->getPlatformRegistry()->getPlatform($this->getBusinessId());
		$platform->setSetupId($this->getId());

		return $platform;
	}

	public function bootCampaign(Market\Trading\Campaign\Model $campaign)
	{
		$service = $this->wakeupService();

		if (!($service instanceof TradingService\Reference\HasCampaignFactory))
		{
			return $this;
		}

		$campaignTrading = clone $this;
		$campaignTrading->service = $service->getCampaignFactory()->getProvider($campaign);
		$campaignTrading->fields['CAMPAIGN_ID'] = $campaign->getId();

		return $campaignTrading;
	}

	public function wakeupService()
	{
		$service = $this->getService();

		if ($this->isServiceReady) { return $service; }

		$service->wakeup($this->makeTradingContext(), $this->getSettings()->getValues());

		$this->isServiceReady = true;

		return $service;
	}

	private function makeTradingContext()
	{
		if ($this->isDeprecated())
		{
			return new DeprecatedContext($this->getEnvironment(), $this->getSiteId(), $this->getId());
		}

		if ($this->getBehaviorCode() === TradingService\Manager::BEHAVIOR_BUSINESS)
		{
			return new BusinessContext(
				$this->getBusiness(),
				$this->getBusiness()->getTradingRepository()->getBusinessCampaignCollection(),
				$this->getEnvironment(),
				$this->getBusiness()->getSiteId(),
				$this->getId()
			);
		}

		$campaign = $this->getCampaign();

		Assert::notNull($campaign, 'campaign');

		return new CampaignContext(
			$this->getBusiness(),
			$campaign,
			$this->getEnvironment(),
			$this->getSiteId(),
			$this->getId(),
			$this->getUrlId()
		);
	}

	public function getCatalog()
	{
		$business = $this->getBusiness();

		if ($business === null) { return null; }

		return $business->getCatalog();
	}

	public function getBusinessId()
	{
		return (int)$this->getField('BUSINESS_ID');
	}

	public function getBusiness()
	{
		$parent = $this->getParent();

		if ($parent instanceof Market\Trading\Business\Model) { return $parent; }

		return $this->requireModel('BUSINESS', Market\Trading\Business\Model::class);
	}

	public function injectBusiness(Market\Trading\Business\Model $business)
	{
		$this->setField('BUSINESS_ID', $business->getId());
		$this->childModel['BUSINESS'] = $business;
	}

	public function getCampaignId()
	{
		return (int)$this->getField('CAMPAIGN_ID');
	}

	/** @return Market\Trading\Campaign\Model|null */
	public function getCampaign()
	{
		$parent = $this->getParent();

		if ($parent instanceof Market\Trading\Campaign\Model) { return $parent; }

		$campaignId = $this->getCampaignId();

		if ($campaignId === 0) { return null; }

		$campaign = $this->getBusiness()->getCampaignCollection()->getItemById($campaignId);

		if ($campaign === null)
		{
			/** @noinspection MissUsingParentKeywordInspection */
			throw new Main\ObjectNotFoundException(parent::getMessage('MODEL_NOT_FOUND', [
				'#FIELD#' => 'CAMPAIGN',
			]));
		}

		return $campaign;
	}

	public function getService()
	{
		if ($this->service === null)
		{
			$this->service = TradingService\Manager::createProvider($this->getServiceCode(), $this->getBehaviorCode());
		}

		return $this->service;
	}

	public function getSettings()
	{
		return $this->getCollection('SETTINGS', Market\Trading\Settings\Collection::class);
	}

	protected function getChildCollectionQueryParameters($fieldKey)
	{
		if ($fieldKey === 'BUSINESS')
		{
			$businessId = $this->getBusinessId();

			if ($businessId === 0) { return null; }

			return [
				'filter' => [ '=ID' => $businessId ],
			];
		}

		return parent::getChildCollectionQueryParameters($fieldKey);
	}

	public function setField($name, $value)
	{
		parent::setField($name, $value);

		if (($name === 'ID' || $name === 'SETTINGS') && $this->isServiceReady)
		{
			$this->getService()->wakeup($this->makeTradingContext(), $this->getSettings()->getValues());
		}
	}
}