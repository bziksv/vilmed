<?php
namespace Yandex\Market\Trading\Service\Marketplace\Command;

use Bitrix\Main;
use Yandex\Market\Trading\Service as TradingService;
use Yandex\Market\Trading\Settings as TradingSettings;
use Yandex\Market\Trading\Setup as TradingSetup;
use Yandex\Market\Api;

class GroupStoresTweak
{
	protected $provider;
	protected $setupId;
	protected $previous;
	protected $waitTweak = [];

	public function __construct(TradingService\Marketplace\Provider $provider, $setupId, array $previous = null)
	{
		$this->provider = $provider;
		$this->setupId = (int)$setupId;
		$this->previous = $previous;
	}

	public function install()
	{
		$actual = $this->actual();
		$stored = $this->stored();

		$this->saveSelf($actual);
		$this->passOptions($actual);
		$this->link($stored, $actual);
		$this->unlink($stored, $actual);

		$this->flushTweak();
	}

	public function uninstall()
	{
		$stored = $this->stored();

		$this->unlink($stored);

		$this->flushTweak();
	}

	protected function actual()
	{
		$campaignIds = Api\Business\Warehouses\Facade::storeGroup(
			$this->provider->getOptions(),
			$this->provider->getLogger()
		);
		list($primaryWarehouse, $primaryCampaign) = Api\Business\Warehouses\Facade::primaryWarehouse(
			$this->provider->getOptions(),
			$this->provider->getLogger()
		);
		$campaignMap = $this->mapCampaigns($campaignIds);
		$result = [
			'WAREHOUSE' => $primaryWarehouse,
		];

		if (!empty($campaignMap))
		{
			$result['GROUP'] = array_keys($campaignMap);
		}

		if ($primaryCampaign !== null)
		{
			$result['PRIMARY_CAMPAIGN'] = $primaryCampaign;
			$campaignSetup = array_search($primaryCampaign, $campaignMap, true);

			if ($campaignSetup !== false)
			{
				$result['PRIMARY_SETUP'] = $campaignSetup;
			}
			else if (!empty($campaignMap))
			{
				reset($campaignMap);
				$result['PRIMARY_SETUP'] = key($campaignMap);
			}
		}

		return $result;
	}

	protected function mapCampaigns(array $campaignIds = null)
	{
		if (empty($campaignIds)) { return []; }

		$query = TradingSettings\Table::getList([
			'filter' => [
				'=NAME' => 'CAMPAIGN_ID',
				'=VALUE' => $campaignIds,
				'=SETUP.ACTIVE' => true,
			],
			'select' => [ 'SETUP_ID', 'VALUE' ],
		]);

		$result = array_column($query->fetchAll(), 'VALUE', 'SETUP_ID');
		$result = array_map('intval', $result);

		return $result;
	}

	protected function stored()
	{
		$result = [];

		$query = TradingSettings\Table::getList([
			'filter' => [ '=NAME' => 'STORE_DATA' ],
			'select' => [ 'SETUP_ID', 'VALUE' ],
		]);

		while ($row = $query->fetch())
		{
			$option = $row['VALUE'];

			if (!is_array($option)) { $option = []; }

			$result[$row['SETUP_ID']] = $option;
		}

		return $result;
	}

	protected function saveSelf(array $actual)
	{
		if (!$this->changed($actual, $this->previous)) { return; }

		$this->save($this->setupId, $actual, $this->previous !== null);
	}

	protected function passOptions(array $actual)
	{
		$this->provider->getOptions()->extendValues([
			'STORE_DATA' => $actual,
		]);
	}

	protected function link(array $stored, array $actual)
	{
		$group = isset($actual['GROUP']) ? (array)$actual['GROUP'] : [];

		foreach ($group as $setupId)
		{
			$setupId = (int)$setupId;

			if ($setupId === $this->setupId) { continue; }

			$previous = isset($stored[$setupId]) ? $stored[$setupId] : null;
			$previousExists = ($previous !== null);

			if (!$this->changed($actual, $previous)) { continue; }

			$this->save($setupId, $actual, $previousExists);
			$this->waitTweak($setupId);
		}
	}

	protected function unlink(array $stored, array $actual = null)
	{
		$group = isset($actual['GROUP']) ? (array)$actual['GROUP'] : [];

		foreach ($stored as $setupId => $option)
		{
			$setupId = (int)$setupId;

			if ($setupId === $this->setupId) { continue; }
			if (in_array($setupId, $group, true)) { continue; }
			if (!isset($option['GROUP']) || !in_array($this->setupId, $option['GROUP'], true)) { continue; }

			$newOption = $option;
			$newOption['GROUP'] = array_diff(
				$option['GROUP'],
				array_merge($group, [ $this->setupId ])
			);

			if (!$this->changed($newOption, $option)) { continue; }

			$this->save($setupId, $newOption, true);
			$this->waitTweak($setupId);
		}
	}

	protected function changed($a, $b)
	{
		if (!is_array($a) || !is_array($b)) { return true; }

		$diffA = array_diff_assoc($a, $b);
		$diffB = array_diff_assoc($b, $a);

		if (!empty($diffA) || !empty($diffB)) { return true; }

		$aGroup = isset($a['GROUP']) ? (array)$a['GROUP'] : [];
		$bGroup = isset($b['GROUP']) ? (array)$b['GROUP'] : [];

		$diffGroupA = array_diff_assoc($aGroup, $bGroup);
		$diffGroupB = array_diff_assoc($bGroup, $aGroup);

		return (!empty($diffGroupA) || !empty($diffGroupB));
	}

	protected function save($setupId, array $storeData, $optionExists)
	{
		$primary = [
			'SETUP_ID' => $setupId,
			'NAME' => 'STORE_DATA',
		];
		$fields = [
			'VALUE' => $storeData,
		];

		if ($optionExists)
		{
			TradingSettings\Table::update($primary, $fields);
		}
		else
		{
			TradingSettings\Table::add($primary + $fields);
		}
	}

	protected function waitTweak($setupId)
	{
		if ((int)$setupId === $this->setupId) { return; }

		$this->waitTweak[] = $setupId;
	}

	protected function flushTweak()
	{
		foreach ($this->waitTweak as $setupId)
		{
			$this->tweak($setupId);
		}

		$this->waitTweak = [];
	}

	protected function tweak($setupId)
	{
		try
		{
			$setup = TradingSetup\Model::loadById($setupId);

			$setup->wakeupService();
			$setup->tweak();
		}
		catch (Main\ObjectNotFoundException $exception)
		{
			trigger_error($exception->getMessage(), E_USER_WARNING);
		}
	}
}