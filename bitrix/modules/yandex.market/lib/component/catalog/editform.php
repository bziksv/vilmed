<?php
namespace Yandex\Market\Component\Catalog;

use Bitrix\Main;
use Yandex\Market\Component;
use Yandex\Market\Catalog\Setup;
use Yandex\Market\Reference\Concerns;
use Yandex\Market\Ui;
use Yandex\Market\Utils;
use Yandex\Market\Watcher\Setup\StorageSchedule;

class EditForm extends Component\Model\EditForm
{
	use Concerns\HasOnce;

	protected $business;
	protected $productLink;
	protected $productParam;
	protected $productFilter;
    protected $segmentEnable;

	public function __construct(\CBitrixComponent $component, array $componentParameters = [])
	{
		parent::__construct($component, $componentParameters);

		$this->business = new Component\Molecules\Business();
		$this->productLink = new Component\Molecules\ProductLink([
			'PRODUCT',
		]);
		$this->productParam = new Component\Molecules\ProductParam([
			'PRODUCT.PRICE_SEGMENT.PARAM',
			'PRODUCT.STOCK_SEGMENT.PARAM',
			'PRODUCT.OFFER_SEGMENT.PARAM',
			'PRODUCT.CARD_SEGMENT.PARAM',
		]);
		$this->productFilter = new Component\Molecules\ProductFilter([
			'PRODUCT.FILTER',
		]);
        $this->segmentEnable = new Molecules\SegmentEnable([
            'PRICE_ENABLE' => 'PRODUCT.PRICE_SEGMENT',
            'STOCK_ENABLE' => 'PRODUCT.STOCK_SEGMENT',
            'OFFER_ENABLE' => 'PRODUCT.OFFER_SEGMENT',
            'CARD_ENABLE' => 'PRODUCT.CARD_SEGMENT',
        ]);
	}

	public function modifyRequest(array $request, array $fields)
	{
		$result = parent::modifyRequest($request, $fields);
		$result = $this->business->modifyRequest($result);
		$result = $this->productLink->sanitizeIblock($result, $this->usedIblocks($result));
		$result = $this->productFilter->sanitizeFilter($result, $fields);

		return $result;
	}

    public function getFields(array $select = [], array $item = null)
    {
		if ($item !== null && $this->productLink->inSelect($select))
		{
			$item = $this->productLink->sanitizeIblock($item, $this->usedIblocks($item));
		}

        $result = parent::getFields($select, $item);
        $result = $this->segmentEnable->extendFields($result);
	    $result = $this->business->markDefined($result, $this->getComponentParam('BUSINESS_ID'));
		$result = $this->unsetDefaultValueWithCanActivate($result);
		$result = $this->injectBusinessFieldFilter($result);

		if ($this->getComponentParam('BUSINESS') === null)
		{
			$this->business->testIsEmpty($result);
		}

        return $result;
    }

	private function unsetDefaultValueWithCanActivate(array $fields)
	{
		if (!$this->getComponentParam('CAN_ACTIVATE')) { return $fields; }

		$names = [
			'AUTOUPDATE',
			'REFRESH_PERIOD',
		];

		foreach ($names as $name)
		{
			if (!isset($fields[$name]['SETTINGS']['DEFAULT_VALUE'])) { continue; }

			unset($fields[$name]['SETTINGS']['DEFAULT_VALUE']);
		}

		return $fields;
	}

	private function injectBusinessFieldFilter(array $fields)
	{
		if (!isset($fields['BUSINESS'])) { return $fields; }

		$fields['BUSINESS']['SETTINGS']['FILTER'] = Ui\Trading\Menu::businessFilter($this->getComponentParam('BUSINESS_ID'), 'ID');

		return $fields;
	}

    public function validate(array $data, array $fields)
	{
		$result = parent::validate($data, $fields);
		$this->productFilter->validate($result, $data, $fields);

		return $result;
	}

	public function load($primary, array $select = [], $isCopy = false)
	{
		$result = parent::load($primary, $select, $isCopy);
		$result = $this->business->afterLoad($result, $this->getComponentParam('BUSINESS_ID'));
		$result = $this->productParam->parse($result);

		return $result;
	}

	public function initial(array $select = [])
	{
		$result = $this->business->initial($this->getComponentParam('BUSINESS_ID'));

		return $result;
	}

	public function add(array $data)
	{
		$data = $this->productParam->compile($data);
		$data = $this->injectActivateValues($data);

		return parent::add($data);
	}

	public function update($primary, array $data)
	{
		$data = $this->productParam->compile($data);
		$data = $this->injectActivateValues($data);

		return parent::update($primary, $data);
	}

	private function injectActivateValues(array $data)
	{
		if (!empty($data['REFRESH_PERIOD']) || !$this->needActivateOnSave()) { return $data; }

		$data['AUTOUPDATE'] = Setup\Table::BOOLEAN_Y;
		$data['REFRESH_PERIOD'] = Utils::isAgentUseCron() ? StorageSchedule::ONE_HOUR : 0;

		return $data;
	}

	public function extend(array $data, array $fields)
	{
		$data = $this->business->extend($data, $this->getComponentParam('BUSINESS'));
		$data = $this->productLink->sanitizeIblock($data, $this->usedIblocks($data));
		$data = $this->productLink->extend($data);

		return $data;
	}

	private function usedIblocks(array $data)
	{
		return $this->business->usedIblocks($data, $this->getComponentParam('SKU_MAP_FIELD'));
	}

	protected function getModelClass()
	{
		return Setup\Model::class;
	}

	private function needActivateOnSave()
	{
		return (
			$this->getComponentParam('CAN_ACTIVATE')
			&& Main\Application::getInstance()->getContext()->getRequest()->getPost('save') !== null
		);
	}
}