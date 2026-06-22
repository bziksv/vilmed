<?php
namespace Yandex\Market\Component\TradingSettings;

use Yandex\Market;
use Yandex\Market\Utils;
use Yandex\Market\Reference\Assert;
use Yandex\Market\Trading;
use Yandex\Market\Component;
use Bitrix\Main;

class EditForm extends Component\Plain\EditForm
{
	/** @var Trading\Setup\Model */
	private $tradingModel;

	public function __construct(\CBitrixComponent $component, array $componentParameters = [])
	{
		Assert::notNull($componentParameters['TRADING'], '$componentParameters[TRADING]');
		Assert::isInstanceOf($componentParameters['TRADING'], Trading\Setup\Model::class);

		$this->tradingModel = $componentParameters['TRADING'];

		parent::__construct($component, $componentParameters + [
			'FIELDS' => $this->tradingModel->wakeupService()->getOptions()->getFields(),
		]);
	}

	public function prepareComponentParams(array $componentParameters)
	{
		return parent::prepareComponentParams($componentParameters + [
			'TABS' => $this->tradingModel->getService()->getOptions()->getTabs(),
		]);
	}

	public function processPostAction($action, $data)
	{
		if ($action === 'reset')
		{
			$this->processResetAction($data);
			return;
		}

		parent::processPostAction($action, $data);
	}

	private function processResetAction($data)
	{
		if (!$this->getComponentParam('ALLOW_SAVE'))
		{
			$message = $this->getComponentLang('SAVE_DISALLOW');
			throw new Main\AccessDeniedException($message);
		}

		Trading\Settings\Table::deleteBatch([
			'filter' => [
				'=ENTITY_TYPE' => Trading\Settings\Table::ENTITY_TYPE_SETUP,
				'=ENTITY_ID' => $data['PRIMARY'],
			],
		]);
	}

	public function initial(array $select = [])
	{
		if ( $this->tradingModel->getBehaviorCode() !== Trading\Service\Manager::BEHAVIOR_BUSINESS)
		{
			return $this->injectDefaultSettings([], $select);
		}

		$options = $this->tradingModel->getService()->getOptions();

		if ((!$options instanceof Trading\Service\MarketplaceBusiness\Options))
		{
			return $this->injectDefaultSettings([], $select);
		}

		$campaignSettings = $this->collectCampaignOptions();
		$settings = $options->mergeBusinessValues($campaignSettings);

		return $this->injectDefaultSettings($settings, $select, $campaignSettings);
	}

	private function collectCampaignOptions()
	{
		$options = [];
		$active = [];

		try
		{
			/** @var Trading\Setup\Model $trading */
			foreach ($this->tradingModel->getBusiness()->getTradingCollection() as $trading)
			{
				if ($trading->getId() === $this->tradingModel->getId()) { continue; }

				$behavior = $trading->getBehaviorCode();

				if ($behavior === Trading\Service\Manager::BEHAVIOR_BUSINESS) { continue; }

				$placement = Trading\Campaign\Placement::toPlacement($behavior);

				if (isset($active[$placement]) || (isset($options[$placement]) && !$trading->isActive())) { continue; }

				$options[$placement] = $trading->getSettings()->getValues();

				if ($trading->isActive())
				{
					$active[$placement] = true;
				}
			}
		}
		catch (Main\SystemException $exception)
		{
			trigger_error($exception->getMessage(), E_USER_WARNING);
		}

		return $options;
	}

	public function load($primary, array $select = [], $isCopy = false)
	{
		$setup = $this->tradingModel;

		if ($this->alreadyConfigured($setup))
		{
			$settings = $setup->wakeupService()->getOptions()->getValues();
			$settings = $this->fillFieldsValueEmpty($settings, $select);
		}
		else
		{
			$settings = $setup->getSettings()->getValues();
			$settings = $this->injectDefaultSettings($settings, $select);
		}

		return $settings;
	}

	private function alreadyConfigured(Trading\Setup\Model $setup)
	{
		return $setup->getSettings()->count() > 0;
	}

	private function injectDefaultSettings(array $settings, array $select = [], array $campaignSettings = null)
	{
		foreach ($this->getFields($select) as $fieldName => $field)
		{
			if (!isset($field['SETTINGS']['DEFAULT_VALUE'])) { continue; }

			$value = Utils\Field::getChainValue($settings, $fieldName, Utils\Field::GLUE_BRACKET);

			if ($value !== null) { continue; }

			$value = $field['SETTINGS']['DEFAULT_VALUE'];

			if ($campaignSettings !== null && isset($field['PLACEMENT']) && is_array($field['PLACEMENT']))
			{
				foreach ($field['PLACEMENT'] as $placement => $dummy)
				{
					if (isset($campaignSettings[$placement]))
					{
						$value = false;
						break;
					}
				}
			}

			Utils\Field::setChainValue($settings, $fieldName, $value, Utils\Field::GLUE_BRACKET);
		}

		return $settings;
	}

	private function fillFieldsValueEmpty(array $settings, array $select = [])
	{
		foreach ($this->getFields($select) as $fieldName => $field)
		{
			if (!empty($field['SETTINGS']['READONLY'])) { continue; }

			$filled = Utils\Field::getChainValue($settings, $fieldName, Utils\Field::GLUE_BRACKET);

			if ($filled !== null) { continue; }

			$isHidden = isset($field['HIDDEN']) && $field['HIDDEN'] === 'Y';
			$hasDefaultValue = isset($field['SETTINGS']['DEFAULT_VALUE']);
			$value = ($isHidden && $hasDefaultValue)
				? $field['SETTINGS']['DEFAULT_VALUE']
				: false;

			Utils\Field::setChainValue($settings, $fieldName, $value, Utils\Field::GLUE_BRACKET);
		}

		return $settings;
	}

	public function add(array $data)
	{
		try
		{
			$setup = $this->tradingModel;
			$data = $this->prepareInsert($data);

			$addResult = $this->install($setup, $data);

			if (!$addResult->isSuccess()) { return $addResult; }

			$this->dropConnect();
			$this->afterInsert($setup);

			return $addResult;
		}
		catch (Main\SystemException $exception)
		{
			$result = new Main\Entity\AddResult();
			$result->addError(new Main\Error($exception->getMessage()));

			return $result;
		}
	}

	public function update($primary, array $data)
	{
		try
		{
			$setup = $this->tradingModel;
			$data = $this->prepareInsert($data, $primary);

			$updateResult = $this->insert($setup, $data);

			if (!$updateResult->isSuccess()) { return $updateResult; }

			$this->afterInsert($setup);

			return $updateResult;
		}
		catch (Main\SystemException $exception)
		{
			$result = new Main\Entity\UpdateResult();
			$result->addError(new Main\Error($exception->getMessage()));

			return $result;
		}
	}

	private function prepareInsert(array $values, $primary = null)
	{
		$values = $this->applyUserFieldsOnBeforeSave($this->fields, $values, $primary);
		$values = $this->sliceFieldsDependHidden($this->fields, $values);

		return $values;
	}

	private function rawFields(Trading\Setup\Model $setup, array $values)
	{
		$scalarFields = Trading\Setup\Table::getEntity()->getScalarFields();
		$fields = array_intersect_key($values, $scalarFields);
		$fields += array_intersect_key($setup->getFields(), $scalarFields);
		$fields['SETTINGS'] = $this->rawSettings($values);

		return $fields;
	}

	private function rawSettings($values)
	{
		$result = [];

		foreach ($values as $key => $value)
		{
			$result[] = [
				'NAME' => $key,
				'VALUE' => $value,
			];
		}

		return $result;
	}

	private function install(Trading\Setup\Model $setup, array $values)
	{
		$updateResult = $this->insert($setup, $values);

		$addResult = new Main\Entity\AddResult();
		$addResult->setId($setup->getId());

		if (!$updateResult->isSuccess())
		{
			$addResult->addErrors($updateResult->getErrors());
		}

		return $addResult;
	}

	private function insert(Trading\Setup\Model $setup, array $values)
	{
		try
		{
			$rawFields = $this->rawFields($setup, $values);

			$setup->setFields($rawFields);
			$setup->save();

			return new Main\Entity\UpdateResult();
		}
		catch (Main\SystemException $exception)
		{
			$result = new Main\Entity\UpdateResult();
			$result->addError(new Main\Error($exception->getMessage()));

			return $result;
		}
	}

	private function dropConnect()
	{
		$connectKey = Main\Application::getInstance()->getContext()->getRequest()->getQuery('connect');

		if ($connectKey === null || !isset($_SESSION[Component\TradingConnect\EditForm::SESSION_KEY][$connectKey])) { return; }

		unset($_SESSION[Component\TradingConnect\EditForm::SESSION_KEY][$connectKey]);
	}

	private function afterInsert(Trading\Setup\Model $setup)
	{
		$needActivate = $this->needActivateOnSave();

		if ($needActivate || $setup->isActive())
		{
			$setup->install();
			$setup->activate();
			$setup->save();
		}

		if ($needActivate && $setup->getBehaviorCode() === Trading\Service\Manager::BEHAVIOR_BUSINESS)
		{
			$business = $setup->getBusiness();
			$placements = $business->getTradingRepository()->getBusinessPlacements();

			/** @var Trading\Setup\Model $siblingTrading */
			foreach ($business->getTradingCollection()->exceptItemId($setup->getId()) as $siblingTrading)
			{
				$campaign = $siblingTrading->getCampaign();

				if ($campaign === null || !in_array($campaign->getPlacement(), $placements, true)) { continue; }

				$siblingTrading->uninstall();
				$siblingTrading->deactivate();
				$siblingTrading->save();
			}
		}
	}

	private function needActivateOnSave()
	{
		return (
			$this->getComponentParam('CAN_ACTIVATE')
			&& Main\Application::getInstance()->getContext()->getRequest()->getPost('save') !== null
		);
	}
}