<?php
namespace Yandex\Market\Component\TradingSetup;

use Bitrix\Main;
use Yandex\Market;
use Yandex\Market\Ui;
use Yandex\Market\Reference\Concerns;
use Yandex\Market\Component;
use Yandex\Market\Trading\Service as TradingService;
use Yandex\Market\Trading\Setup as TradingSetup;

class GridList extends Market\Component\Model\GridList
{
	use Concerns\HasMessage;

	private $calculatedFields;

	public function __construct(\CBitrixComponent $component, array $componentParameters = [])
	{
		self::includeSelfMessages();

		parent::__construct($component, $componentParameters);

		$this->calculatedFields = new Component\Molecules\CalculatedFields([
			'SERVICE' => [
				'TYPE' => 'string',
				'ITEM_LOADER' => function(array $row) {
			        if ($row['TRADING_SERVICE'] !== TradingService\Manager::SERVICE_MARKETPLACE)
					{
						return $row['TRADING_SERVICE'];
					}

					if ($row['TRADING_BEHAVIOR'] === TradingService\Manager::BEHAVIOR_BUSINESS)
					{
						return self::getMessage('SERVICE_BUSINESS', [
							'#ID#' => $row['BUSINESS_ID'],
							'#NAME#' => $row['BUSINESS_NAME'],
						]);
					}

					if ((string)$row['CAMPAIGN_PLACEMENT'] !== '')
					{
						$glue = self::getMessage('CAMPAIGN_GLUE', null, '');

						return "{$row['CAMPAIGN_PLACEMENT']} {$glue} {$row['CAMPAIGN_NAME']} [{$row['CAMPAIGN_ID']}]";
					}

					return $row['TRADING_BEHAVIOR'];
				},
				'USES' => [
					'TRADING_SERVICE',
					'TRADING_BEHAVIOR',
					'BUSINESS_ID',
					'BUSINESS_NAME',
					'CAMPAIGN_ID',
					'CAMPAIGN_PLACEMENT',
					'CAMPAIGN_NAME',
				],
			],
		], self::getMessagePrefix());
	}

	public function processPostAction($action, $data)
	{
		if ($action === 'reinstall')
		{
			$this->processReinstall($data);
		}
		else if ($action === 'moveToPull')
		{
			$this->processMoveToPull($data);
		}
		else
		{
			parent::processPostAction($action, $data);
		}
	}

	protected function processReinstall($data)
	{
		global $APPLICATION;

		$model = $this->getModelClass();
		$successUrl = $APPLICATION->GetCurPageParam('', [ 'postAction' ]);

		$setupList = $model::loadList(array_diff_key($data, [
			'select' => true,
			'limit' => true,
			'offset' => true,
		]));

		/** @var TradingSetup\Model $setup */
		foreach ($setupList as $setup)
		{
			Market\Reference\Assert::typeOf($setup, TradingSetup\Model::class, 'setup');

			if ($setup->isActive() && !$setup->isDeprecated())
			{
				$setup->install();
				$setup->activate();
			}
			else
			{
				$setup->uninstall();
				$setup->deactivate();
			}
			
			$setup->save();
		}

		Market\Utils\ServerStamp\Facade::reset();
		\CAdminNotify::DeleteByTag(Market\Trading\State\PushAgent::NOTIFY_DISABLED);

		LocalRedirect($successUrl);
	}

	protected function processMoveToPull($data)
	{
		global $APPLICATION;

		$routine = new Market\Trading\Routine\MoveToPull(array_diff_key($data, [
			'select' => true,
		]));

		$routine->run();
		$routine->clearUpdater();

		LocalRedirect($APPLICATION->GetCurPageParam('', [ 'postAction' ]));
	}

	public function getFields(array $select = [])
	{
		$fields = parent::getFields($select);
		$fields = $this->injectFieldsBusinessFilter($fields);
		$fields += $this->calculatedFields->getFields();

		return $this->sortFields($fields);
	}

	protected function injectFieldsBusinessFilter(array $fields)
	{
		$keys = [
			'BUSINESS' => 'ID',
			'CAMPAIGN' => 'BUSINESS_ID',
		];

		foreach ($keys as $key => $businessField)
		{
			if (!isset($fields[$key])) { continue; }

			$fields[$key]['SETTINGS']['FILTER'] = Ui\Trading\Menu::businessFilter($this->getComponentParam('BUSINESS_ID'), $businessField);
		}

		return $fields;
	}

	protected function sortFields(array $fields)
	{
		$order = array_flip($this->getComponentParam('LIST_FIELDS'));

		uasort($fields, static function($fieldA, $fieldB) use ($order) {
			$sortA = isset($order[$fieldA['FIELD_NAME']]) ? $order[$fieldA['FIELD_NAME']] : 500;
			$sortB = isset($order[$fieldB['FIELD_NAME']]) ? $order[$fieldB['FIELD_NAME']] : 500;

			if ($sortA === $sortB) { return 0; }

			return $sortA < $sortB ? -1 : 1;
		});

		return $fields;
	}

	public function getDefaultFilter()
	{
		return
			parent::getDefaultFilter()
			+ Market\Ui\Trading\Menu::businessFilter($this->getComponentParam('BUSINESS_ID'));
	}

	public function filterActions($item, $actions)
	{
		foreach ($actions as $actionKey => $action)
		{
			if (!isset($action['TYPE'])) { continue; }

			$isValid = true;

			switch ($action['TYPE'])
			{
				case 'ACTIVATE':
					$isValid = ($item['ACTIVE'] === Market\Export\Promo\Table::BOOLEAN_N);
				break;

				case 'DEACTIVATE':
					$isValid = ($item['ACTIVE'] === Market\Export\Promo\Table::BOOLEAN_Y);
				break;
			}

			if (!$isValid)
			{
				unset($actions[$actionKey]);
			}
		}

		return $actions;
	}

	public function processAjaxAction($action, $data)
	{
		if ($action === 'activate')
		{
			return $this->processActivateAction($data);
		}

		if ($action === 'deactivate')
		{
			return $this->processDeactivateAction($data);
		}

		if ($action === 'delete')
		{
			return $this->processDeleteAction($data);
		}

		return parent::processAjaxAction($action, $data);
	}

	protected function processActivateAction($data)
	{
		$selectedIds = $this->getActionSelectedIds($data);

		foreach ($selectedIds as $id)
		{
			$this->activateItem($id);
		}

		return $selectedIds;
	}

	protected function processDeactivateAction($data)
	{
		$selectedIds = $this->getActionSelectedIds($data);

		foreach ($selectedIds as $id)
		{
			$this->deactivateItem($id);
		}

		return $selectedIds;
	}

	protected function processDeleteAction($data)
	{
		$selectedIds = $this->getActionSelectedIds($data);

		foreach ($selectedIds as $id)
		{
			$this->deactivateItem($id);
			$this->deleteItem($id);
		}

		return $selectedIds;
	}

	protected function activateItem($id)
	{
		$setup = TradingSetup\Model::loadById($id);

		if (TradingService\Migration::isDeprecated($setup->getServiceCode()))
		{
			throw new Main\SystemException(self::getMessage('SERVICE_DEPRECATED', [
				'#SERVICE#' => $setup->getServiceCode(),
			]));
		}

		if ($this->isBrokenItem($setup))
		{
			throw new Main\SystemException(self::getMessage('BROKEN_TRADING', [
				'#ID#' => $setup->getId(),
			]));
		}

		$setup->install();
		$setup->activate();
		$setup->save();
	}

	protected function deactivateItem($id)
	{
		$setup = TradingSetup\Model::loadById($id);

		$setup->deactivate();

		if (!$this->isBrokenItem($setup))
		{
			$setup->uninstall();
		}

		$setup->save();
	}

	protected function isBrokenItem(TradingSetup\Model $trading)
	{
		if ($trading->getServiceCode() !== TradingService\Manager::SERVICE_MARKETPLACE)
		{
			return false;
		}

		if ($trading->getBehaviorCode() === TradingService\Manager::BEHAVIOR_BUSINESS)
		{
			return ($trading->getBusinessId() === 0);
		}

		return ($trading->getCampaignId() === 0);
	}

	protected function isAllowBatch()
	{
		return false;
	}

	public function load(array $queryParameters = [])
	{
		list($queryParameters, $calculatedSelect) = $this->calculatedFields->queryParameters($queryParameters);

		$rows = parent::load($queryParameters);
		$rows = $this->calculatedFields->extendRows($rows, $calculatedSelect);

		return $rows;
	}

	protected function normalizeQueryFilter(array $filter)
	{
		list($queryParameters) = $this->calculatedFields->queryParameters([ 'filter' => $filter ]);

		return parent::normalizeQueryFilter($queryParameters['filter']);
	}
}