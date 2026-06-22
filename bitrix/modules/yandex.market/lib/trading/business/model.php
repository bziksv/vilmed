<?php
namespace Yandex\Market\Trading\Business;

use Bitrix\Main;
use Yandex\Market\Api;
use Yandex\Market\Catalog;
use Yandex\Market\Glossary;
use Yandex\Market\SalesBoost;
use Yandex\Market\Logger\Trading\Logger;
use Yandex\Market\Trading;
use Yandex\Market\Exceptions;
use Yandex\Market\Reference\Storage;
use Yandex\Market\Reference\Concerns;
use Yandex\Market\Ui\Trading\MenuCompiler;

class Model extends Storage\Model
{
	use Concerns\HasMessage;

	private $overlay;
	/** @var Options */
	private $options;
	/** @var ExternalSettings */
	private $externalSettings;
	/** @var TradingRepository */
	private $tradingRepository;
	/** @var CampaignRepository */
	private $campaignRepository;

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
			throw new Exceptions\Trading\SetupNotFound($exception->getMessage());
		}

		return $result;
	}

	public function __construct(array $fields = [])
	{
		parent::__construct($fields);

		$this->tradingRepository = new TradingRepository($this);
		$this->campaignRepository = new CampaignRepository($this);
	}

	public function getOverlay()
	{
		if ($this->overlay === null)
		{
			$this->overlay = new Api\Overlay\Business($this->getId(), $this->getOptions()->getApiAuth(), $this->createLogger());
		}

		return $this->overlay;
	}

	public function getId()
	{
		return (int)$this->getField('ID');
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

	public function getSiteId()
	{
		return (string)$this->getField('SITE_ID');
	}

	public function getPlatformId()
	{
		return (int)$this->getField('PLATFORM_ID');
	}

	public function getCampaignRepository()
	{
		return $this->campaignRepository;
	}

	public function getCampaignCollection()
	{
		return $this->getCollection('CAMPAIGN', Trading\Campaign\Collection::class);
	}

	public function getTradingRepository()
	{
		return $this->tradingRepository;
	}

	public function getTradingCollection()
	{
		$tradingCollection = $this->getCollection('TRADING', Trading\Setup\Collection::class);
		$parent = $this->getParent();

		if ($parent instanceof Trading\Setup\Model)
		{
			$tradingCollection->injectItem($parent);
		}

		return $tradingCollection;
	}

	public function getSalesBoostCollection()
	{
		return $this->getCollection('SALES_BOOST', SalesBoost\Setup\Collection::class);
	}

	public function getSettings()
	{
		return $this->getCollection('SETTINGS', Trading\Settings\Collection::class);
	}

	public function getOptions()
	{
		if ($this->options === null)
		{
			$this->options = new Options();
			$this->options->setValues($this->getSettings()->getValues());
		}

		return $this->options;
	}

	public function getExternalSettings()
	{
		if ($this->externalSettings === null)
		{
			$this->externalSettings = new ExternalSettings($this->getField('EXTERNAL_SETTINGS'));
		}

		return $this->externalSettings;
	}

	public function createLogger()
	{
		$logger = new Logger(Glossary::SERVICE_TRADING);
		$logger->setContext('BUSINESS_ID', $this->getId());
		$logger->setContext('CAMPAIGN_ID', 0);

		return $logger;
	}

	public function getCatalog()
	{
		$parent = $this->getParent();

		if ($parent instanceof Catalog\Setup\Model) { return $parent; }

		return $this->getModel('CATALOG', Catalog\Setup\Model::class);
	}

	public function getPrimaryTrading()
	{
		$tradingCollection = $this->getTradingCollection();
		$defaultTrading = null;
		$candidates = [
			$tradingCollection->getByBehavior(Trading\Service\Manager::BEHAVIOR_BUSINESS),
			$tradingCollection->getActive(),
			$tradingCollection->offsetGet(0),
		];

		/** @var Trading\Setup\Model $trading */
		foreach ($candidates as $trading)
		{
			if ($trading === null) { continue; }

			if ($trading->isActive())
			{
				return $trading;
			}

			if ($defaultTrading === null)
			{
				$defaultTrading = $trading;
			}
		}

		if ($defaultTrading === null)
		{
			throw new Main\ObjectNotFoundException(self::getMessage('TRADING_NOT_FOUND', [
				'#BUSINESS_ID#' => $this->getId(),
			]));
		}

		return $defaultTrading;
	}

	public function setField($name, $value)
	{
		parent::setField($name, $value);

		if ($name === 'SETTINGS')
		{
			$this->resetSettings();
		}

		if ($name === 'EXTERNAL_SETTINGS' && $this->externalSettings !== null)
		{
			$this->externalSettings->setValues($value);
		}
	}

	private function resetSettings()
	{
		$this->overlay = null;

		if ($this->options !== null)
		{
			$this->options->setValues($this->getSettings()->getValues());
		}
	}

	public function install()
	{
		$this->installMenu();
		$this->synchronizeCampaign();
	}

	public function uninstall(MenuCompiler $menuCompiler = null)
	{
		$this->uninstallMenu($menuCompiler);
	}

	private function installMenu()
	{
		$menuCompiler = new MenuCompiler();
		$menuCompiler->installBusiness($this->getId(), $this->getName());
		$menuCompiler->save();
	}

	private function uninstallMenu(MenuCompiler $menuCompiler = null)
	{
		if ($menuCompiler !== null)
		{
			$menuCompiler->uninstallBusiness($this->getId());
			return;
		}

		$menuCompiler = new MenuCompiler();
		$menuCompiler->uninstallBusiness($this->getId());
		$menuCompiler->save();
	}

	private function synchronizeCampaign()
	{
		$this->getCampaignRepository()->synchronize();
	}
}