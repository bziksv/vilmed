<?php
/** @noinspection PhpReturnDocTypeMismatchInspection */
/** @noinspection PhpIncompatibleReturnTypeInspection */
namespace Yandex\Market\Export\Setup;

use Bitrix\Main;
use Bitrix\Main\Localization\Loc;
use Yandex\Market;
use Yandex\Market\Export\Glossary;

Loc::loadMessages(__FILE__);

class Model extends Market\Reference\Storage\Model
	implements Market\Watcher\Agent\EntityRefreshable
{
	use Market\Watcher\Agent\EntityRefreshableTrait;

	/** @var \Yandex\Market\Export\Xml\Format\Reference\Base */
	protected $format;
	protected $domainParsed;

	public static function getDataClass()
	{
		return Table::class;
	}

	public static function normalizeFileName($fileName, $primary = null)
	{
		$fileName = basename(trim($fileName), '.xml');

		if ($fileName === '' && !empty($primary))
		{
			$fileName = 'setup_' . $primary;
		}

		return ($fileName !== '' ? $fileName . '.xml' : null);
	}

	public function onBeforeRemove()
    {
        $this->handleChanges(false);
        $this->handleRefresh(false);
    }

	public function onAfterSave()
	{
	    $this->updateListener();
	}

	public function updateListener()
	{
		$isAutoUpdate = $this->isAutoUpdate();
		$hasFullRefresh = $this->hasFullRefresh();

		$this->handleChanges($isAutoUpdate);
		$this->handleRefresh($hasFullRefresh);
		$this->updatePromoListener();
		$this->updateCollectionListener();
	}

	public function handleChanges($direction)
	{
		if ($direction && !$this->isFileReady()) { return; }

		$installer = new Market\Watcher\Track\Installer(Glossary::SERVICE_SELF, Glossary::ENTITY_SETUP, $this->getId());

        if ($direction)
        {
	        $installer->install($this->getTrackSourceList(), $this->getBindEntities());
        }
        else
        {
	        $installer->uninstall();
        }
	}

	public function updatePromoListener()
    {
        /** @var Market\Export\Promo\Model $promo */
        foreach ($this->getPromoCollection() as $promo)
        {
            $promo->updateListener();
        }
    }

	public function updateCollectionListener()
    {
        /** @var Market\Export\Collection\Model $promo */
        foreach ($this->getCollectionCollection() as $collection)
        {
	        $collection->updateListener();
        }
    }

	public function getTrackSourceList()
    {
        $partials = [];

	    /** @var Market\Export\IblockLink\Model $iblockLink */
        foreach ($this->getIblockLinkCollection() as $iblockLink)
        {
	        $partials[] = $iblockLink->getTrackSourceList();
        }

        return !empty($partials) ? array_merge(...$partials) : [];
    }

	public function getBindEntities()
	{
		$partials = [];

		/** @var Market\Export\IblockLink\Model $iblockLink */
		foreach ($this->getIblockLinkCollection() as $iblockLink)
		{
			$partials[] = $iblockLink->getSetupBindEntities();
		}

		return !empty($partials) ? array_merge(...$partials) : [];
	}

	public function handleRefresh($direction)
	{
		$agentParams = [
			'method' => 'refreshStart',
			'arguments' => [ (int)$this->getId() ],
		];

		if ($direction)
		{
			if (!$this->isFileReady()) { return; }

			$nextExecDate = $this->getRefreshNextExec();

			$agentParams['interval'] = $this->getRefreshPeriod();
			$agentParams['next_exec'] = ConvertTimeStamp($nextExecDate->getTimestamp(), 'FULL');

			Market\Export\Run\Agent::register($agentParams);
		}
		else
		{
			Market\Export\Run\Agent::unregister($agentParams);
			Market\Export\Run\Agent::unregister([
				'method' => 'refresh',
				'arguments' => [ (int)$this->getId() ],
				'search' => Market\Reference\Agent\Controller::SEARCH_RULE_SOFT,
			]);

			Market\Watcher\Agent\StateFacade::drop('refresh', Glossary::SERVICE_SELF, $this->getId());
		}
	}

	/**
	 * @return array
	 */
	public function getContext()
	{
		$format = $this->getFormat();
		$result = $format->getContext();
		$result += [
			'SETUP_ID' => $this->getId(),
			'EXPORT_SERVICE' => $this->getField('EXPORT_SERVICE'),
			'EXPORT_FORMAT' => $this->getField('EXPORT_FORMAT'),
			'EXPORT_FORMAT_TYPE' => $format->getType(),
			'ENABLE_AUTO_DISCOUNTS' => $this->isAutoDiscountsEnabled(),
			'ENABLE_CPA' => $this->isCpaEnabled(),
			'HTTPS' => $this->isHttps(),
			'DOMAIN_URL' => $this->getDomainUrl(),
			'ORIGINAL_URL' => $this->getDomainUrl($this->getField('DOMAIN')),
			'USER_GROUPS' => Market\Data\UserGroup::getDefaults(),
			'HAS_CATALOG' => Main\ModuleManager::isModuleInstalled('catalog'),
			'HAS_SALE' => Main\ModuleManager::isModuleInstalled('sale'),
			'SHOP_DATA' => $this->getShopData()
		];

		// sales notes

		$salesNotes = $this->getSalesNotes();

		if ($salesNotes !== '')
		{
			$result['SALES_NOTES'] = $salesNotes;
		}

		// delivery options

		$deliveryOptions = $this->getDeliveryOptions();

		if (!empty($deliveryOptions))
		{
			$result['DELIVERY_OPTIONS'] = $deliveryOptions;
		}

		return $result;
	}

	public function getDeliveryOptions()
	{
		$deliveryCollection = $this->getDeliveryCollection();

		return $deliveryCollection->getDeliveryOptions();
	}

	public function getSalesNotes()
	{
		return trim($this->getField('SALES_NOTES'));
	}

	public function getShopData()
	{
		$fieldValue = $this->getField('SHOP_DATA');

		return is_array($fieldValue) ? $fieldValue : null;
	}

	public function getFormat()
	{
		if (!isset($this->format))
		{
			$this->format = $this->loadFormat();
		}

		return $this->format;
	}

	protected function loadFormat()
	{
		$service = $this->getField('EXPORT_SERVICE');
		$format = $this->getField('EXPORT_FORMAT');

		return Market\Export\Xml\Format\Manager::getEntity($service, $format);
	}

	public function getFileName()
	{
		$nameValue = (string)$this->getField('FILE_NAME');
		$fileName = static::normalizeFileName($nameValue, $this->getId());
		$dirName = $nameValue !== '' ? dirname($nameValue) : '';

		if ($dirName !== '' && $dirName !== '.')
		{
			$fileName = rtrim($dirName, '/') . '/' . $fileName;
		}

		return $fileName;
	}

	public function getFileRelativePath()
	{
		$fileName = $this->getFileName();

		if (Market\Data\TextString::getPosition($fileName, '/') === 0)
		{
			$result = $fileName;
		}
		else
		{
			$result = BX_ROOT . '/catalog_export/' . $this->getFileName();
		}

		return $result;
	}

	public function getFileAbsolutePath()
	{
		$relativePath = $this->getFileRelativePath();

		return Main\IO\Path::convertRelativeToAbsolute($relativePath);
	}

	public function isFileReady()
	{
		$path = $this->getFileAbsolutePath();

		return Main\IO\File::isFileExists($path);
	}

	public function getFileUrl()
	{
		return $this->getDomainUrl() . $this->getFileRelativePath();
	}

	public function getDomainUrl($domain = null)
	{
		if ($domain === null)
		{
			$domain = $this->getDomain();
		}

		return 'http' . ($this->isHttps() ? 's' : '') . '://' . $domain;
	}

	public function getDomain()
	{
		$parsedDomain = $this->getDomainParsed();

		if ($parsedDomain !== false)
		{
			$result = $parsedDomain['DOMAIN'];
		}
		else
		{
			$result = $this->getField('DOMAIN');
		}

		return $result;
	}

	public function getDomainPath()
	{
		$parsedDomain = $this->getDomainParsed();

		if ($parsedDomain !== false)
		{
			$result = $parsedDomain['PATH'];
		}
		else
		{
			$result = '';
		}

		return $result;
	}

	protected function getDomainParsed()
	{
		if ($this->domainParsed === null)
		{
			$domain = $this->getField('DOMAIN');

			$this->domainParsed = $this->parseDomain($domain);
		}

		return $this->domainParsed;
	}

	protected function parseDomain($domain)
	{
		$result = false;

		if (preg_match('#^([^/]+)([^?\#]*)(.*)?$#', $domain, $matches))
		{
			$result = [
				'DOMAIN' => $matches[1],
				'PATH' => $matches[2],
				'QUERY' => $matches[3]
			];
		}

		return $result;
	}

	public function isHttps()
	{
		return ((string)$this->getField('HTTPS') === Market\Ui\UserField\BooleanType::VALUE_Y);
	}

	public function isAutoDiscountsEnabled()
	{
		return ((string)$this->getField('ENABLE_AUTO_DISCOUNTS') === Market\Ui\UserField\BooleanType::VALUE_Y);
	}

	public function isCpaEnabled()
	{
		return ((string)$this->getField('ENABLE_CPA') === Market\Ui\UserField\BooleanType::VALUE_Y);
	}

	public function isAutoUpdate()
	{
		return ((string)$this->getField('AUTOUPDATE') === Market\Ui\UserField\BooleanType::VALUE_Y);
	}

	public function getRefreshPeriod()
	{
		$period = (int)$this->getField('REFRESH_PERIOD');

		return $period > 0 ? $period : null;
	}

	public function getRefreshTime()
	{
		return Market\Data\Time::parse($this->getField('REFRESH_TIME'));
	}

	public function getIblockLinkCollection()
	{
		return $this->getCollection('IBLOCK_LINK', Market\Export\IblockLink\Collection::class);
	}

	public function getDeliveryCollection()
	{
		return $this->getCollection('DELIVERY', Market\Export\Delivery\Collection::class);
	}

    public function getCollectionCollection()
    {
        return $this->getCollection('COLLECTION', Market\Export\Collection\Collection::class);
    }

    public function getPromoCollection()
    {
        return $this->getCollection('PROMO', Market\Export\Promo\Collection::class);
    }

    protected function getChildCollectionQueryParameters($fieldKey)
    {
        if ($fieldKey === 'PROMO' || $fieldKey === 'COLLECTION')
        {
			return [
				'distinct' => true,
				'filter' => [
					'LOGIC' => 'OR',
					[ 'SETUP_LINK.SETUP_ID' => $this->getId() ],
					[ 'SETUP_EXPORT_ALL' => Market\Export\Promo\Table::BOOLEAN_Y ],
				]
			];
        }

        return parent::getChildCollectionQueryParameters($fieldKey);
    }
}
