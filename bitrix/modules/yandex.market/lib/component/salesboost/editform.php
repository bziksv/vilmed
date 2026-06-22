<?php
namespace Yandex\Market\Component\SalesBoost;

use Yandex\Market;
use Yandex\Market\Component\Molecules;

class EditForm extends Market\Component\Model\EditForm
{
	use Market\Reference\Concerns\HasOnce;
	use Market\Reference\Concerns\HasMessage;

	protected $business;
	protected $productLink;
	protected $productFilter;

	public function __construct(\CBitrixComponent $component)
	{
		parent::__construct($component);

		$this->business = new Molecules\Business();
		$this->productLink = new Molecules\ProductLink([
			'SALES_BOOST_PRODUCT',
		]);
		$this->productFilter = new Molecules\ProductFilter([
			'SALES_BOOST_PRODUCT.FILTER',
		]);
	}

	public function getFields(array $select = [], array $item = null)
	{
		$result = parent::getFields($select, $item);
		$result = $this->extendFieldBusiness($result);
		$result = $this->extendFieldBidField($result, $item);

		$this->business->testIsEmpty($result);

		return $result;
	}

	protected function extendFieldBusiness(array $fields)
	{
		if (!isset($fields['BUSINESS'])) { return $fields; }

		$fields['BUSINESS']['SETTINGS']['FILTER'] = Market\Ui\Trading\Menu::businessFilter(
			$this->getComponentParam('BUSINESS_ID'),
			'ID'
		);

		return $fields;
	}

	protected function extendFieldBidField(array $fields, $item = null)
	{
		if (!isset($fields['BID_FIELD'])) { return $fields; }

		$fields['BID_FIELD']['SETTINGS'] = [
			'IBLOCK_ID' => !empty($item) ? $this->business->usedIblocks($item) : [],
		];

		return $fields;
	}

	public function modifyRequest(array $request, array $fields)
	{
		$result = parent::modifyRequest($request, $fields);
		$result = $this->business->modifyRequest($result);
		$result = $this->productLink->sanitizeIblock($result, $this->business->usedIblocks($result));
		$result = $this->productFilter->sanitizeFilter($result, $fields);

		return $result;
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
		$result = $this->business->afterLoad($result);

		return $result;
	}

	public function extend(array $data, array $fields)
	{
		return $this->productLink->extend($data);
	}
}