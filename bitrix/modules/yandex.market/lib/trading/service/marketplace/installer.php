<?php
namespace Yandex\Market\Trading\Service\Marketplace;

use Yandex\Market;
use Bitrix\Main;
use Yandex\Market\Trading\Service as TradingService;
use Yandex\Market\Ui\Trading\MenuCompiler;

/**
 * @property Provider $provider
*/
class Installer extends TradingService\Common\Installer
{
	public function install()
	{
		parent::install();
		$this->installListener();
		$this->installAdminExtension();
		$this->applyPushAgents($this->getPushAgents());
		$this->installSyncAgent();
		$this->installReturnAgent();
		$this->installMenu();
		$this->installTradingId();
	}

	public function uninstall(array $context = [])
	{
		$exportStatuses = $this->getPushAgents(true);
		$exportStatuses = array_fill_keys(array_keys($exportStatuses), false);

		parent::uninstall($context);
		$this->uninstallListener($context);
		$this->uninstallAdminExtension($context);
		$this->uninstallTradingId();
		$this->uninstallMenu();
		$this->uninstallSyncAgent();
		$this->uninstallReturnAgent();
		$this->applyPushAgents($exportStatuses);
	}

	public function onCatalogSubmitted()
	{
		$this->applyPushAgents($this->getPushAgents());
	}

	protected function installListener()
	{
		$this->provider->getContext()->getEnvironment()->getListener()->bind();
	}

	protected function uninstallListener(array $context)
	{
		if ($context['SERVICE_USED']) { return; }

		$this->provider->getContext()->getEnvironment()->getListener()->unbind();
	}

	protected function installAdminExtension()
	{
		$this->provider->getContext()->getEnvironment()->getAdminExtension()->install();
	}

	protected function uninstallAdminExtension(array $context)
	{
		if ($context['SERVICE_USED']) { return; }

		$this->provider->getContext()->getEnvironment()->getAdminExtension()->uninstall();
	}

	protected function installSyncAgent()
	{
		$this->uninstallOldSyncAgent();
		$this->installCampaignSyncAgent();
	}

	protected function installCampaignSyncAgent()
	{
		$parameters = [
			'method' => 'start',
			'arguments' => [ $this->provider->getOptions()->getCampaignId() ],
			'update' => Market\Reference\Agent\Controller::UPDATE_RULE_STRICT,
		];

		if ($this->provider->getOptions()->getYandexMode() === Options::YANDEX_MODE_PUSH)
		{
			$nextExec = $this->getSyncAgentNextExec();
		}
		else
		{
			$interval = (int)Market\Config::getOption('trading_pull_period', 600);

			$nextExec = new Main\Type\DateTime();
			$nextExec->add(sprintf('PT%sS', $interval));

			$parameters['interval'] = $interval;
		}

		$parameters['next_exec'] = ConvertTimeStamp($nextExec->getTimestamp(), 'FULL');

		Market\Trading\State\OrderStatusSync::register($parameters);
	}

	protected function getSyncAgentNextExec()
	{
		$result = new Main\Type\DateTime();
		$result->setTime(mt_rand(0, 10), mt_rand(0, 59));

		if ($result->getTimestamp() <= time())
		{
			$result->add('P1D');
		}

		return $result;
	}

	protected function uninstallSyncAgent()
	{
		$this->uninstallOldSyncAgent();
		$this->uninstallCampaignSyncAgent();
	}

	protected function uninstallCampaignSyncAgent()
	{
		$campaignId = $this->provider->getContext()->getCampaign()->getId();

		Market\Trading\State\OrderStatusSync::unregister([
			'method' => 'start',
			'arguments' => [ $campaignId ],
		]);
		Market\Trading\State\OrderStatusSync::unregister([
			'method' => 'sync',
			'arguments' => [ $campaignId ],
			'search' => Market\Reference\Agent\Controller::SEARCH_RULE_SOFT,
		]);
	}

	protected function uninstallOldSyncAgent()
	{
		$setupId = $this->provider->getContext()->getSetupId();

		Market\Trading\State\OrderStatusSync::unregister([
			'method' => 'start',
			'arguments' => [ (int)$setupId ], // fix
		]);
		Market\Trading\State\OrderStatusSync::unregister([
			'method' => 'start',
			'arguments' => [ (string)$setupId ],
		]);
		Market\Trading\State\OrderStatusSync::unregister([
			'method' => 'sync',
			'arguments' => [ (string)$setupId ],
			'search' => Market\Reference\Agent\Controller::SEARCH_RULE_SOFT,
		]);
	}

	protected function installReturnAgent()
	{
		if (!$this->provider->getOptions()->useTrackReturn())
		{
			$this->uninstallReturnAgent();
			return;
		}

		$this->uninstallOldReturnAgent();

		$campaignId = $this->provider->getOptions()->getCampaignId();

		if (!$this->hasTrackOrderReturn($campaignId))
		{
			return;
		}

		Market\Trading\State\OrderReturnPickup::register([
			'method' => 'start',
			'arguments' => [ $campaignId ],
		]);
	}

	protected function hasTrackOrderReturn($campaignId)
	{
		return (bool)Market\Trading\State\Internals\OrderReturnTable::getRow([
			'filter' => [
				'=CAMPAIGN_ID' => $campaignId,
				'=STATUS' => Market\Trading\State\Internals\OrderReturnTable::STATUS_PROCESS,
			],
		]);
	}

	protected function uninstallReturnAgent()
	{
		$this->uninstallOldReturnAgent();
		$this->uninstallCampaignReturnAgent();
	}

	protected function uninstallOldReturnAgent()
	{
		$setupId = $this->provider->getContext()->getSetupId();

		Market\Trading\State\OrderReturnPickup::unregister([
			'method' => 'start',
			'arguments' => [ $setupId ],
		]);

		Market\Trading\State\OrderReturnPickup::unregister([
			'method' => 'sync',
			'arguments' => [ $setupId ],
			'search' => Market\Reference\Agent\Controller::SEARCH_RULE_SOFT,
		]);
	}

	protected function uninstallCampaignReturnAgent()
	{
		$campaignId = $this->provider->getContext()->getCampaign()->getId();

		Market\Trading\State\OrderReturnPickup::unregister([
			'method' => 'start',
			'arguments' => [ $campaignId ],
		]);

		Market\Trading\State\OrderReturnPickup::unregister([
			'method' => 'sync',
			'arguments' => [ $campaignId ],
			'search' => Market\Reference\Agent\Controller::SEARCH_RULE_SOFT,
		]);
	}

	protected function getPushAgents($onlyList = false)
	{
		$options = $this->provider->getOptions();

		return [
			'push/stocks' => !$onlyList && $options->usePushStocks() && !$this->groupPushStocksUsed($options),
			'push/prices' => !$onlyList && $options->usePushPrices(),
		];
	}

	protected function groupPushStocksUsed(Options $options)
	{
		$result = false;
		$contextSetupId = $this->provider->getContext()->getSetupId();

		foreach ($options->getStoreGroup() as $setupId)
		{
			if ((int)$setupId === (int)$contextSetupId) { continue; }

			$isRegistered = Market\Trading\State\PushAgent::isRegistered([
				'method' => 'change',
				'arguments' => [ (string)$setupId, 'push/stocks' ],
			]);

			if ($isRegistered)
			{
				$result = true;
				break;
			}
		}

		return $result;
	}

	protected function applyPushAgents(array $statuses)
	{
		$setupId = (string)$this->provider->getContext()->getSetupId();

		foreach ($statuses as $path => $status)
		{
			if ($status)
			{
				$refreshDelay = Market\Trading\State\PushAgent::getRefreshPeriod();
				$refreshNext = $this->getPushAgentNextExec($refreshDelay);

				Market\Trading\State\PushAgent::register([
					'method' => 'refresh',
					'arguments' => [ $setupId, $path ],
					'next_exec' => $refreshNext,
					'interval' => $refreshDelay,
				]);

				$changeDelay = Market\Trading\State\PushAgent::getChangePeriod();
				$changeNext = $this->getPushAgentNextExec($changeDelay);

				Market\Trading\State\PushAgent::register([
					'method' => 'change',
					'arguments' => [ $setupId, $path ],
					'next_exec' => $changeNext,
					'interval' => $changeDelay,
				]);
			}
			else
			{
				Market\Trading\State\PushAgent::unregister([
					'method' => 'refresh',
					'arguments' => [ $setupId, $path ],
				]);

				Market\Trading\State\PushAgent::unregister([
					'method' => 'change',
					'arguments' => [ $setupId, $path ],
				]);

				Market\Trading\State\PushAgent::unregister([
					'method' => 'process',
					'arguments' => [ $setupId, $path ],
					'search' => Market\Reference\Agent\Controller::SEARCH_RULE_SOFT,
				]);
			}
		}
	}

	protected function getPushAgentNextExec($delay = 60)
	{
		$result = new Main\Type\DateTime();
		$result->add(sprintf('PT%sS', $delay));

		return $result;
	}

	private function installMenu()
	{
		$business = $this->provider->getContext()->getBusiness();

		$compiler = new MenuCompiler();
		$compiler->injectTrading($business->getId(), $this->provider->getBehaviorCode());
		$compiler->save();
	}

	private function uninstallMenu()
	{
		$context = $this->provider->getContext();
		$business = $context->getBusiness();
		$behavior = $this->provider->getBehaviorCode();

		if ($business->getTradingRepository()->someoneUsingBehavior($behavior, $context->getSetupId())) { return; }

		$compiler = new MenuCompiler();
		$compiler->ejectTrading($business->getId(), $behavior);
		$compiler->save();
	}

	private function installTradingId()
	{
		$context = $this->provider->getContext();
		$campaign = $context->getCampaign();

		if ($campaign->isUnknown()) { return; }

		$campaign->setField('TRADING_ID', $context->getSetupId());
		$campaign->save();
	}

	private function uninstallTradingId()
	{
		$context = $this->provider->getContext();
		$campaign = $context->getCampaign();

		if ($campaign->getTradingId() !== $context->getSetupId()) { return; }

		$campaign->setField('TRADING_ID', 0);
		$campaign->save();
	}
}