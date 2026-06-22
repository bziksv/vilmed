<?php
namespace Yandex\Market\Trading\Service\Marketplace\Command;

use Bitrix\Main;
use Yandex\Market\Reference\Concerns;
use Yandex\Market\Trading\Campaign;
use Yandex\Market\Trading\Service as TradingService;
use Yandex\Market\Trading\Settings as TradingSettings;

class GroupStores
{
	use Concerns\HasOnce;

	/** @var TradingService\Marketplace\Provider */
	protected $provider;
	/** @var array */
	protected $settings;
	/** @var array */
	protected $preload = [
		'PRODUCT_FEED',
		'PRODUCT_STORE',
		'USE_ORDER_RESERVE',
		'STOCKS_BEHAVIOR',
	];

	public function __construct(TradingService\Marketplace\Provider $provider)
	{
		$this->provider = $provider;
	}

	public function feeds()
	{
		$result = [];

		foreach ($this->setting('PRODUCT_FEED') as $setupId => $value)
		{
			if (!is_array($value)) { $value = []; }

			Main\Type\Collection::normalizeArrayValuesByInt($value);

			$result[$setupId] = $value;
		}

		return $result;
	}

	public function stores()
	{
		$stores = $this->setting('PRODUCT_STORE');

		return $this->valueMerge($stores);
	}

	public function primarySetup()
	{
		$campaignId = $this->provider->getContext()->getCampaign()->getExternalSettings()->getWarehouseGroupPrimary();

		if ($campaignId === null) { return null; }

		$linked = $this->linked();

		if (isset($linked[$campaignId]))
		{
			return $linked[$campaignId];
		}

		if (!empty($linked))
		{
			return reset($linked);
		}

		return null;
	}

	public function linked()
	{
		$context = $this->provider->getContext();
		$campaign = $context->getCampaign();
		$primaryCampaignId = $campaign->getExternalSettings()->getWarehouseGroupPrimary();

		if ($primaryCampaignId === null) { return []; }

		$result = [];

		/** @var Campaign\Model $siblingCampaign */
		foreach ($context->getBusiness()->getCampaignCollection() as $siblingCampaign)
		{
			$tradingId = $siblingCampaign->getTradingId();

			if ($tradingId === 0) { continue; }

			if ($siblingCampaign->getExternalSettings()->getWarehouseGroupPrimary() === $primaryCampaignId)
			{
				$result[$siblingCampaign->getId()] = $tradingId;
			}
		}

		return $result;
	}

	protected function valueMerge(array $values)
	{
		$partials = [];

		foreach ($values as $value)
		{
			if (!is_array($value)) { continue; }

			$partials[] = $value;
		}

		return !empty($partials) ? array_merge(...$partials) : [];
	}

	protected function setting($name)
	{
		$settings = $this->settings();

		if (!isset($settings[$name]))
		{
			throw new Main\ArgumentException(sprintf('register setting %s preload', $name));
		}

		return $settings[$name];
	}

	protected function settings()
	{
		if ($this->settings === null)
		{
			$this->settings = $this->loadSettings();
		}

		return $this->settings;
	}

	protected function loadSettings()
	{
		$setupIds = array_diff($this->linked(), [ $this->provider->getContext()->getSetupId() ]);
		$result = array_fill_keys($this->preload, []);

		if (empty($setupIds)) { return $result; }

		$query = TradingSettings\Table::getList([
			'filter' => [
				'=ENTITY_TYPE' => TradingSettings\Table::ENTITY_TYPE_SETUP,
				'=ENTITY_ID' => array_values(array_unique($setupIds)),
				'=NAME' => $this->preload,
			],
			'select' => [ 'ENTITY_ID', 'NAME', 'VALUE' ],
		]);

		while ($row = $query->fetch())
		{
			$result[$row['NAME']][$row['ENTITY_ID']] = $row['VALUE'];
		}

		return $result;
	}
}