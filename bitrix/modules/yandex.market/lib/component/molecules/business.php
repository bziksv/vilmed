<?php
namespace Yandex\Market\Component\Molecules;

use Bitrix\Main;
use Yandex\Market\Export;
use Yandex\Market\Trading;
use Yandex\Market\Reference\Concerns;
use Yandex\Market\Ui\Admin\Path;
use Yandex\Market\Ui\UserField;
use Yandex\Market\Utils\Field;

class Business
{
	use Concerns\HasOnce;
	use Concerns\HasMessage;

	private $fieldName;
	private $primaryName;

	public function __construct($fieldName = 'BUSINESS', $primaryName = 'BUSINESS_ID')
	{
		$this->fieldName = $fieldName;
		$this->primaryName = $primaryName;
	}

	public function modifyRequest(array $request)
	{
		if (isset($request[$this->fieldName]))
		{
			$request[$this->primaryName] = $request[$this->fieldName];
		}

		return $request;
	}

	public function markDefined(array $fields, $businessId)
	{
		$businessId = (int)$businessId;

		if ($businessId <= 0 || !isset($fields[$this->fieldName])) { return $fields; }

		$fields[$this->fieldName]['HIDDEN'] = 'Y';
		$fields[$this->fieldName]['SETTINGS']['DEFAULT_VALUE'] = $businessId;

		return $fields;
	}

	public function testIsEmpty(array $fields)
	{
		if (!isset($fields[$this->fieldName])) { return; }

		$query = UserField\ReferenceType::GetList($fields[$this->fieldName]);

		if ($query->Fetch()) { return; }

		throw new Main\SystemException(self::getMessage('EMPTY', [
			'#URL#' => Path::getModuleUrl('trading_connect', [
				'lang' => LANGUAGE_ID,
			]),
		]));
	}

	public function initial($businessId)
	{
		$businessId = (int)$businessId;

		if ($businessId <= 0) { return []; }

		return [
			$this->fieldName => $businessId,
			$this->primaryName => $businessId,
		];
	}

	public function afterLoad(array $row, $businessId = null)
	{
		$businessId = (int)$businessId;

		if ($businessId > 0)
		{
			$row[$this->primaryName] = $businessId;
		}

		if (isset($row[$this->primaryName]))
		{
			$row[$this->fieldName] = $row[$this->primaryName];
		}

		return $row;
	}

	public function extend(array $row, Trading\Business\Model $business = null)
	{
		if ($business !== null)
		{
			$row[$this->fieldName . '_MODEL'] = $business;
		}
		else if (!empty($row[$this->fieldName]))
		{
			$row[$this->fieldName . '_MODEL'] = Trading\Business\Model::loadById($row[$this->fieldName]);
		}

		return $row;
	}

	public function usedIblocks(array $data, $skuMapFieldName = null)
	{
		if ($skuMapFieldName !== null)
		{
			return $this->skuMapIblocks($data, $skuMapFieldName) ?: $this->globalIblocks();
		}

		return $this->businessIblocks($data) ?: $this->globalIblocks();
	}

	private function skuMapIblocks(array $data, $skuMapFieldName)
	{
		if (empty($skuMapFieldName)) { return []; }

		$skuMap = Field::getChainValue($data, $skuMapFieldName, Field::GLUE_BRACKET);

		if (!is_array($skuMap)) { return []; }

		$skuMap = array_filter($skuMap, static function($iblockMap) {
			return is_array($iblockMap) && !empty($iblockMap['IBLOCK']) && !empty($iblockMap['FIELD']);
		});

		return $this->normalizeIblocks(array_column($skuMap, 'IBLOCK'));
	}

	private function businessIblocks(array $data)
	{
		$businessId = !empty($data[$this->fieldName]) ? (int)$data[$this->fieldName] : null;

		if ($businessId <= 0) { return []; }

		return $this->once('usedIblocks', [ $businessId ], function($businessId) {
			if (empty($businessId)) { return []; }

			$iblockIds = Trading\Business\Model::loadById($businessId)->getOptions()->getSkuMap()->getIblockIds();

			return $this->normalizeIblocks($iblockIds);
		});
	}

	private function globalIblocks()
	{
		return $this->once('globalIblocks', null, function() {
			return (
				$this->feedIblocksByFilter([
					'=EXPORT_SERVICE' => [
						Export\Xml\Format\Manager::EXPORT_SERVICE_MARKETPLACE,
						Export\Xml\Format\Manager::EXPORT_SERVICE_YANDEX_MARKET,
					],
					'=AUTOUPDATE' => true,
				])
				?: $this->feedIblocksByFilter([
					'=EXPORT_SERVICE' => [
						Export\Xml\Format\Manager::EXPORT_SERVICE_MARKETPLACE,
						Export\Xml\Format\Manager::EXPORT_SERVICE_YANDEX_MARKET,
					],
				])
				?: $this->moduleCatalogIblocks()
			);
		});
	}

	private function feedIblocksByFilter(array $filter)
	{
		$query = Export\Setup\Table::getList([
			'filter' => $filter,
			'select' => [ 'ID' ],
		]);

		return $this->feedIblocks(array_column($query->fetchAll(), 'ID'));
	}

	private function moduleCatalogIblocks()
	{
		if (!Main\Loader::includeModule('catalog')) { return []; }

		$result = [];
		$query = \CCatalog::GetList();

		while ($row = $query->Fetch())
		{
			$iblockId = !empty($row['PRODUCT_IBLOCK_ID']) ? (int)$row['PRODUCT_IBLOCK_ID'] : (int)$row['IBLOCK_ID'];
			$result[$iblockId] = true;
		}

		return array_keys($result);
	}

	private function normalizeIblocks(array $iblockIds)
	{
		Main\Type\Collection::normalizeArrayValuesByInt($iblockIds);

		if (empty($iblockIds)) { return []; }
		if (!Main\Loader::includeModule('catalog')) { return $iblockIds; }

		$iblockMap = array_flip($iblockIds);

		foreach ($iblockIds as $iblockId)
		{
			$catalog = \CCatalogSku::GetInfoByIBlock($iblockId);

			if (isset($catalog['CATALOG_TYPE']) && $catalog['CATALOG_TYPE'] === \CCatalogSku::TYPE_OFFERS)
			{
				$iblockMap[$catalog['PRODUCT_IBLOCK_ID']] = true;
				unset($iblockMap[$iblockId]);
			}
		}

		return array_keys($iblockMap);
	}

	private function feedIblocks(array $feedIds)
	{
		if (empty($feedIds)) { return []; }

		$iblockMap = [];

		$query = Export\IblockLink\Table::getList([
			'filter' => [ '=SETUP_ID' => $feedIds ],
			'select' => [ 'IBLOCK_ID' ],
		]);

		while ($row = $query->fetch())
		{
			$iblockMap[$row['IBLOCK_ID']] = true;
		}

		return array_keys($iblockMap);
	}
}