<?php
namespace Yandex\Market\Component\Business;

use Yandex\Market\Reference\Assert;
use Yandex\Market\Reference\Concerns;
use Yandex\Market\Trading;
use Yandex\Market\Component;
use Yandex\Market\Api;
use Yandex\Market\Environment;
use Bitrix\Main;

class EditForm extends Component\Plain\EditForm
{
	use Concerns\HasMessage;

	/** @var Trading\Business\Model */
	private $businessModel;
	/** @var Trading\Setup\Model */
	private $tradingModel;

	public function __construct(\CBitrixComponent $component, array $componentParameters = [])
	{
		Assert::notNull($componentParameters['BUSINESS'], '$componentParameters[BUSINESS]');
		Assert::notNull($componentParameters['TRADING'], '$componentParameters[TRADING]');
		Assert::isInstanceOf($componentParameters['BUSINESS'], Trading\Business\Model::class);
		Assert::isInstanceOf($componentParameters['TRADING'], Trading\Setup\Model::class);

		$this->businessModel = $componentParameters['BUSINESS'];
		$this->tradingModel = $componentParameters['TRADING'];

		parent::__construct($component, $componentParameters);
	}

	public function load($primary, array $select = [], $isCopy = false)
	{
		return $this->initial($select);
	}

	public function initial(array $select = [])
	{
		$values = $this->optionValues();
		$values['SITE_ID'] = $this->tradingModel->getSiteId();

		return $this->applyValuesSelect($values, $select);
	}

	private function optionValues()
	{
		$values = $this->businessModel->getOptions()->getValues();

		if ($this->tradingModel->getBehaviorCode() !== Trading\Service\Manager::BEHAVIOR_BUSINESS)
		{
			if (!empty($values))
			{
				return $values;
			}

			return $this->tradingModel->getSettings()->getValues();
		}

		if ($this->businessModel->isNew())
		{
			$migrator = new OptionsMigrator();
			$values += $migrator->compile($this->businessModel->getTradingCollection(), $this->tradingModel);
			$values += $this->defaultSkuMap($values);

			return $values;
		}

		if (
			!$this->tradingModel->isNew()
			|| count(array_diff_key($values, [ 'API_KEY' => true ])) > 0
		)
		{
			return $values;
		}

		$migrator = new OptionsMigrator();
		$values += $migrator->compile($this->businessModel->getTradingCollection(), $this->tradingModel);
		$values += $this->defaultSkuMap($values);

		return $values;
	}

	private function defaultSkuMap(array $values)
	{
		if (isset($values['PRODUCT_SKU_FIELD'])) { return $values; }

		$molecula = new Component\Molecules\Business();
		$values['PRODUCT_SKU_FIELD'] = [];

		foreach ($molecula->usedIblocks([]) as $iblockId)
		{
			$values['PRODUCT_SKU_FIELD'][] = [
				'IBLOCK' => $iblockId,
				'FIELD' => 'ID',
			];
		}

		return $values;
	}

	public function getFields(array $select = [], array $item = null)
	{
		if ($this->fields === null)
		{
			$options = $this->businessModel->getOptions();
			$options->setValues($item !== null ? $item : $this->initial());

			$this->fields = $this->prepareFields($options->getFields());
		}

		return parent::getFields($select, $item);
	}

	private function applyValuesSelect(array $values, array $select)
	{
		if (empty($select)) { return $values; }

		return array_intersect_key($values, array_flip($select));
	}

	public function add(array $data)
	{
		try
		{
			$business = $this->businessModel;
			$trading = $this->tradingModel;

			$data = $this->prepareInsert($data);
			list($businessRaw, $tradingRaw) = $this->compileRaw($data, $trading);

			$business->setFields($businessRaw);
			$trading->setFields($tradingRaw);

			$apiCampaign = $this->apiCampaign($business);
			$business = $this->fulfillBusiness($business, $apiCampaign);
			$this->fulfillCampaign($business, $apiCampaign);

			$business->save();
			$business->install();

			$addResult = new Main\Entity\AddResult();
			$addResult->setId($business->getId());

			return $addResult;
		}
		catch (Main\SystemException $exception)
		{
			$updateResult = new Main\Entity\UpdateResult();
			$updateResult->addError(new Main\Error($exception->getMessage()));

			return $updateResult;
		}
	}

	private function apiCampaign(Trading\Business\Model $business)
	{
		$campaignId = $this->tradingModel->getCampaignId();
		$apiCampaigns = Api\Campaigns\Facade::campaigns($business->getOptions()->getApiAuth());
		$apiCampaign = $apiCampaigns->getItemByCampaignId($campaignId);

		if ($apiCampaign === null)
		{
			throw new Main\SystemException(self::getMessage('CAMPAIGN_NOT_FOUND', [ '#ID#' => $campaignId ]));
		}

		return $apiCampaign;
	}

	private function fulfillBusiness(Trading\Business\Model $business, Api\Campaigns\Model\Campaign $apiCampaign)
	{
		$campaignBusiness = $apiCampaign->getBusiness();
		$storedBusiness = Trading\Business\Model::loadOne([
			'filter' => [ '=ID' => $campaignBusiness->getId() ],
		]);

		if ($storedBusiness !== null)
		{
			$storedSettings = $storedBusiness->getSettings()->getValues();
			$storeSiteId = $storedBusiness->getSiteId();

			$this->tradingModel->injectBusiness($storedBusiness);

			if (empty($storedSettings))
			{
				$storedBusiness->setField('SETTINGS', $business->getField('SETTINGS'));
			}

			if ($storeSiteId === '')
			{
				$storedBusiness->setField('SITE_ID', $this->tradingModel->getSiteId());
			}

			$business = $storedBusiness;
		}
		else
		{
			$business->setField('ID', $business->getId());
		}

		$business->setField('NAME', $business->getName());

		return $business;
	}

	private function fulfillCampaign(Trading\Business\Model $business, Api\Campaigns\Model\Campaign $apiCampaign)
	{
		$campaignCollection = $business->getCampaignCollection();
		$campaign = $campaignCollection->getItemById($apiCampaign->getId());

		if ($campaign === null)
		{
			$campaignCollection->addItem(Trading\Campaign\Model::fromApi(
				$apiCampaign,
				$business->getOverlay()->getWarehouses()->getWarehouseGroups()->getItemByCampaignId($apiCampaign->getId())
			));

			$business->setField('CAMPAIGN', $campaignCollection->toArray());
		}
	}

	public function update($primary, array $data)
	{
		try
		{
			$business = $this->businessModel;
			$trading = $this->tradingModel;

			$data = $this->prepareInsert($data, $primary);
			list($businessRaw, $tradingRaw) = $this->compileRaw($data, $trading);

			$business->setFields($businessRaw);
			$trading->setFields($tradingRaw);

			$business->save();
			$business->install();

			return new Main\Entity\UpdateResult();
		}
		catch (Main\SystemException $exception)
		{
			$updateResult = new Main\Entity\UpdateResult();
			$updateResult->addError(new Main\Error($exception->getMessage()));

			return $updateResult;
		}
	}

	private function prepareInsert(array $data, $primary = null)
	{
		Environment::stamp();

		$data = $this->applyUserFieldsOnBeforeSave($this->fields, $data, $primary);
		$data = $this->sliceFieldsDependHidden($this->fields, $data);

		return $data;
	}

	private function compileRaw(array $data, Trading\Setup\Model $trading)
	{
		$rawKeys = [
			'SITE_ID' => true,
		];

		$settings = array_diff_key($data, $rawKeys);
		$businessRaw = array_intersect_key($data, $rawKeys);
		$tradingRaw = $businessRaw;

		if ($trading->getBehaviorCode() !== Trading\Service\Manager::BEHAVIOR_BUSINESS)
		{
			$businessRaw = array_diff_key($businessRaw, $tradingRaw);
		}

		$businessRaw['SETTINGS'] = array_map(static function($key, $value) {
			return [
				'NAME' => $key,
				'VALUE' => $value,
			];
		}, array_keys($settings), array_values($settings));

		return [ $businessRaw, $tradingRaw ];
	}
}