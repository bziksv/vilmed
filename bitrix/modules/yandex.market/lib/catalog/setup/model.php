<?php
namespace Yandex\Market\Catalog\Setup;

use Yandex\Market\Catalog;
use Yandex\Market\Data;
use Yandex\Market\State;
use Yandex\Market\Trading;
use Yandex\Market\Utils;
use Yandex\Market\Watcher;
use Yandex\Market\Reference;
use Yandex\Market\Ui\UserField;
use Yandex\Market\Reference\Storage;
use Yandex\Market\Reference\Concerns;
use Yandex\Market\Export\Routine\QueryBuilder;

class Model extends Storage\Model
    implements Watcher\Agent\EntityRefreshable
{
    use Watcher\Agent\EntityRefreshableTrait;
    use Concerns\HasOnce;

	public static function getDataClass()
	{
		return Table::class;
	}

	public function getId()
	{
		return Data\Number::castInteger($this->getField('ID'));
	}

    public function getContext()
    {
        $siteId = $this->getBusiness()->getSiteId();
		$host = Data\SiteDomain::getHost($siteId);

        return [
            'SETUP_ID' => $this->getId(),
            'CATALOG_ID' => $this->getId(),
            'BUSINESS_ID' => $this->getBusinessId(),
            'SITE_ID' => $siteId,
            'DOMAIN_URL' => $host ? "https://{$host}" : null,
        ];
    }

	public function getLogLevel()
	{
		return (string)$this->getField('LOG_LEVEL');
	}

    public function getBusinessId()
    {
        return (int)$this->getField('BUSINESS_ID');
    }

    /** @return Trading\Business\Model */
    public function getBusiness()
    {
		$parent = $this->getParent();

		if ($parent instanceof Trading\Business\Model) { return $parent; }

		return $this->requireModel('BUSINESS', Trading\Business\Model::class);
    }

    public function getProductCollection()
    {
        return $this->getCollection('PRODUCT', Catalog\Product\Collection::class);
    }

	public function canDoSomething()
	{
		return (
			$this->isPriceEnabled()
			|| $this->isStockEnabled()
			|| $this->isOfferEnabled()
			|| $this->isCardEnabled()
		);
	}

	public function isActive()
	{
		return ($this->isAutoUpdate() || $this->hasFullRefresh());
	}

	public function activate()
	{
		$this->setField('AUTOUPDATE', Table::BOOLEAN_Y);
		$this->setField('REFRESH_PERIOD', Utils::isAgentUseCron() ? Watcher\Setup\StorageSchedule::ONE_HOUR : 0);

		$this->updateListener();
	}

	public function deactivate()
	{
		$this->setField('AUTOUPDATE', Table::BOOLEAN_N);
		$this->setField('REFRESH_PERIOD', 0);

		$this->updateListener();
	}

	public function isPriceEnabled()
	{
		return $this->isSegmentEnabled('PRICE');
	}

	public function isStockEnabled()
	{
		return $this->isSegmentEnabled('STOCK');
	}

	public function isOfferEnabled()
	{
		return $this->isSegmentEnabled('OFFER');
	}

	public function isCardEnabled()
	{
		return $this->isSegmentEnabled('CARD');
	}

	private function isSegmentEnabled($segment)
	{
		return ((string)$this->getField($segment . '_ENABLE') === Table::BOOLEAN_Y);
	}

    public function isAutoUpdate()
    {
        return ((string)$this->getField('AUTOUPDATE') === UserField\BooleanType::VALUE_Y);
    }

    public function getRefreshPeriod()
    {
        $period = (int)$this->getField('REFRESH_PERIOD');

        return $period > 0 ? $period : null;
    }

    public function getRefreshTime()
    {
        return Data\Time::parse($this->getField('REFRESH_TIME'));
    }

	public function wasSubmitted()
	{
		return (State::get("catalog_submitted_{$this->getId()}", 'N') === 'Y');
	}

    public function onBeforeRemove()
    {
        $this->handleChanges(false);
        $this->handleRefresh(false);
    }

    public function onAfterSave()
    {
		$this->warmSourceSelect();

		if (!$this->isAutoUpdate())
		{
			$this->handleChanges(false);
		}

		if (!$this->hasFullRefresh())
		{
			$this->handleRefresh(false);
		}
    }

    public function updateListener()
    {
		$ready = $this->wasSubmitted() && $this->canDoSomething();

        $this->handleChanges($ready && $this->isAutoUpdate());
        $this->handleRefresh($ready && $this->hasFullRefresh());
    }

    public function handleChanges($direction)
    {
        $installer = new Watcher\Track\Installer(Catalog\Glossary::SERVICE_SELF, Catalog\Glossary::ENTITY_SETUP, $this->getId());

        if ($direction)
        {
            $installer->install($this->getTrackSourceList(), $this->getBindEntities());
        }
        else
        {
            $installer->uninstall();
        }
    }

	private function warmSourceSelect()
	{
		/** @var Catalog\Product\Model $product */
		foreach ($this->getProductCollection() as $product)
		{
			$select = [];
			$context = $product->getContext();

			/** @var Catalog\Segment\Collection $segmentCollection */
			foreach ($product->getActiveSegments() as $segmentCollection)
			{
				/** @var Catalog\Segment\Model $segment */
				foreach ($segmentCollection as $segment)
				{
					$select = $segment->getParamCollection()->getTagMap()->getSourceSelect($select);
				}
			}

			(new QueryBuilder\Select())->boot($select, $context);
		}
	}

    private function getTrackSourceList()
    {
        $partials = [];

        /** @var Catalog\Product\Model $product */
        foreach ($this->getProductCollection() as $product)
        {
            $partials[] = $product->getTrackSourceList();
        }

        return !empty($partials) ? array_merge(...$partials) : [];
    }

    private function getBindEntities()
    {
        $partials = [];

        /** @var Catalog\Product\Model $product */
        foreach ($this->getProductCollection() as $product)
        {
            $partials[] = $product->getSetupBindEntities();
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
            $nextExecDate = $this->getRefreshNextExec();

            $agentParams['interval'] = $this->getRefreshPeriod();
            $agentParams['next_exec'] = ConvertTimeStamp($nextExecDate->getTimestamp(), 'FULL');

            Catalog\Run\Agent::register($agentParams);
        }
        else
        {
            Catalog\Run\Agent::unregister($agentParams);
            Catalog\Run\Agent::unregister([
                'method' => 'refresh',
                'arguments' => [ $this->getId() ],
                'search' => Reference\Agent\Controller::SEARCH_RULE_SOFT,
            ]);

            Watcher\Agent\StateFacade::drop('refresh', Catalog\Glossary::SERVICE_SELF, $this->getId());
        }
    }

	public function reset()
	{
		$this->setField('PRICE_ENABLE', Table::BOOLEAN_Y);
		$this->setField('STOCK_ENABLE', Table::BOOLEAN_Y);
		$this->setField('OFFER_ENABLE', Table::BOOLEAN_Y);
		$this->setField('CARD_ENABLE', Table::BOOLEAN_Y);
		$this->setField('PRODUCT', []);
	}

	protected function getChildCollectionQueryParameters($fieldKey)
	{
		if ($fieldKey === 'BUSINESS')
		{
			return [
				'filter' => [ '=ID' => $this->getBusinessId() ],
			];
		}

		return parent::getChildCollectionQueryParameters($fieldKey);
	}
}