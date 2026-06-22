<?php
namespace Yandex\Market\Component\TradingLog;

use Bitrix\Iblock;
use Bitrix\Main;
use Yandex\Market\Component;
use Yandex\Market\Exceptions;
use Yandex\Market\Glossary;
use Yandex\Market\Reference\Concerns as GlobalConcerns;
use Yandex\Market\Trading;
use Yandex\Market\Catalog;
use Yandex\Market\Ui\Admin;
use Yandex\Market\Ui;
use Yandex\Market\Utils\ArrayHelper;

class GridList extends Component\Data\GridList
{
	use GlobalConcerns\HasMessage;

	private $calculatedFields;
	private $compoundFields;

	public function __construct(\CBitrixComponent $component)
	{
		self::includeSelfMessages();

		parent::__construct($component);

		$this->calculatedFields = new Component\Molecules\CalculatedFields([
			'ORDER_ID' => [
				'TYPE' => 'primary',
				'SELECTABLE' => false,
				'SETTINGS' => [ 'URL_FIELD' => 'ORDER_URL' ],
				'QUERY_FILTER' => function($value, $compare) {
					return [
						$compare . 'ENTITY_TYPE' => Trading\Glossary::ENTITY_ORDER,
						$compare . 'ENTITY_ID' => $value,
					];
				},
				'BATCH_LOADER' => function(array $rows) {
					return $this->loadRowsOrderId($rows);
				},
				'USES' => [ 'SETUP_TYPE', 'SETUP_ID', 'ENTITY_TYPE', 'ENTITY_ID' ],
			],
			'OFFER_ID' => [
				'TYPE' => 'primary',
				'SELECTABLE' => false,
				'SETTINGS' => [ 'URL_FIELD' => 'OFFER_URL' ],
				'QUERY_FILTER' => function($value, $compare) {
					return [
						'LOGIC' => 'OR',
						[
							$compare . 'ENTITY_TYPE' => Glossary::ENTITY_OFFER,
							$compare . 'ENTITY_ID' => $value,
						],
						[ $compare . 'ASSORTMENT.ELEMENT_ID' => $value ],
						[ $compare . 'ASSORTMENT.SKU' => $value ],
					];
				},
				'BATCH_LOADER' => function(array $rows) {
					return $this->loadRowsOfferId($rows);
				},
				'USES' => [
					'ENTITY_TYPE',
					'ENTITY_ID',
				],
			],
		], self::getMessagePrefix());
		$this->compoundFields = new Component\Molecules\CompoundFields([
			'SETUP' => [
				'TYPE' => 'compound',
				'FILTERABLE' => true,
				'FIELDS' => [
					'TRADING_SETUP' => [
						'TYPE' => 'setup',
						'NAME' => self::getMessage('FIELD_TRADING_SETUP'),
						'LINK_FIELD' => 'SETUP_ID',
						'SETTINGS' => [
							'DATA_CLASS' => Trading\Setup\Table::class,
							'NAME' => [ '[%s] %s%s', 'ID', 'CAMPAIGN_NAME', 'BUSINESS_NAME' ],
							'EDIT_URL' => Admin\Path::getModuleUrl('trading_edit') . '&id=#ID#',
						],
					],
					'CATALOG_SETUP' => [
						'TYPE' => 'setup',
						'NAME' => self::getMessage('FIELD_CATALOG_SETUP'),
						'LINK_FIELD' => 'SETUP_ID',
						'SETTINGS' => [
							'DATA_CLASS' => Catalog\Setup\Table::class,
							'EDIT_URL' => Admin\Path::getModuleUrl('catalog_edit') . '&id=#ID#',
						],
					],
				],
				'FILTER' => [
					'TRADING_SETUP' => [ 'SETUP_TYPE' => Glossary::SERVICE_TRADING ],
					'CATALOG_SETUP' => [ 'SETUP_TYPE' => Glossary::SERVICE_CATALOG ],
				],
			],
			'ENTITY' => [
				'TYPE' => 'compound',
				'FIELDS' => [
					'ORDER_ID',
					'OFFER_ID',
				],
				'FILTER' => [
					'ORDER_ID' => [ 'ENTITY_TYPE' => Trading\Glossary::ENTITY_ORDER ],
					'OFFER_ID' => [ 'ENTITY_TYPE' => [ Glossary::ENTITY_OFFER, Catalog\Glossary::ENTITY_SKU ] ],
				],
			],
			'DEBUG' => [
				'FILTERABLE' => false,
				'SORTABLE' => false,
				'FIELDS' => [
					'CONTEXT',
					'TRACE',
				],
			],
		], self::getMessagePrefix());
	}

	public function getDefaultFilter()
	{
		return
			parent::getDefaultFilter()
			+ Ui\Trading\Menu::businessFilter($this->getComponentParam('BUSINESS_ID'));
	}

	public function getFields(array $select = [])
	{
		$fields = parent::getFields();
		$fields += $this->calculatedFields->getFields();
		$fields += $this->compoundFields->getFields($fields);
		$fields = $this->injectFieldsEditBaseQuery($fields);
		$fields = $this->injectSetupFieldBusinessFilter($fields);

		if (!empty($select)) { $fields = array_intersect_key($fields, array_flip($select)); }

		return $fields;
	}

	private function injectFieldsEditBaseQuery(array $fields)
	{
		$businessId = $this->getComponentParam('BUSINESS_ID');

		if ((string)$businessId === '') { return $fields; }

		foreach ($fields as &$field)
		{
			if (isset($field['FIELDS']))
			{
				$field['FIELDS'] = $this->injectFieldsEditBaseQuery($field['FIELDS']);
				continue;
			}

			if (!isset($field['SETTINGS']['EDIT_URL'])) { continue; }

			$queryPosition = mb_strpos($field['SETTINGS']['EDIT_URL'], '?');
			$gluePosition = $queryPosition !== false ? mb_strpos($field['SETTINGS']['EDIT_URL'], '&', $queryPosition) : false;
			$queryString = http_build_query([ 'business' => $businessId ]);

			if ($gluePosition !== false)
			{
				$field['SETTINGS']['EDIT_URL'] =
					mb_substr($field['SETTINGS']['EDIT_URL'], 0, $gluePosition)
					. '&' . $queryString
					. mb_substr($field['SETTINGS']['EDIT_URL'], $gluePosition);
			}
			else
			{
				$field['SETTINGS']['EDIT_URL'] .= ($queryPosition === false ? '?' : '&') . $queryString;
			}
		}
		unset($field);

		return $fields;
	}

	private function injectSetupFieldBusinessFilter(array $fields)
	{
		if (!isset($fields['SETUP'])) { return $fields; }

		$filter = Ui\Trading\Menu::businessFilter($this->getComponentParam('BUSINESS_ID'));

		$fields['SETUP']['FIELDS']['TRADING_SETUP']['SETTINGS']['FILTER'] = $filter;
		$fields['SETUP']['FIELDS']['CATALOG_SETUP']['SETTINGS']['FILTER'] = $filter;

		return $fields;
 	}

	public function load(array $queryParameters = [])
	{
		$queryParameters = $this->compoundFields->queryParameters($queryParameters);
		list($queryParameters, $calculatedSelect) = $this->calculatedFields->queryParameters($queryParameters);

		$rows = parent::load($queryParameters);
		$rows = $this->clearNullCampaignId($rows);
		$rows = $this->calculatedFields->extendRows($rows, $calculatedSelect);

		return $rows;
	}

	protected function normalizeQueryFilter(array $filter)
	{
		$queryParameters = [ 'filter' => $filter ];
		$queryParameters = $this->compoundFields->queryParameters($queryParameters);
		list($queryParameters) = $this->calculatedFields->queryParameters($queryParameters);

		return parent::normalizeQueryFilter($queryParameters['filter']);
	}

	private function clearNullCampaignId(array $rows)
	{
		foreach ($rows as &$row)
		{
			if (empty($row['CAMPAIGN_ID']))
			{
				$row['CAMPAIGN_ID'] = null;
			}
		}
		unset($row);

		return $rows;
	}

	private function loadRowsOrderId(array $rows)
	{
		$orderRows = array_filter($rows, static function(array $row) { return $row['ENTITY_TYPE'] === Trading\Glossary::ENTITY_ORDER; });

		foreach (ArrayHelper::groupBy($orderRows, 'SETUP_ID') as $setupId => $rowsChunk)
		{
			$editUrls = $this->ordersEditUrl($setupId, array_column($rowsChunk, 'ENTITY_ID'));

			foreach ($rowsChunk as $key => $row)
			{
				$rows[$key]['ORDER_ID'] = $row['ENTITY_ID'];
				$rows[$key]['ORDER_URL'] = isset($editUrls[$row['ENTITY_ID']]) ? $editUrls[$row['ENTITY_ID']] : null;
			}
		}

		return $rows;
	}

	private function ordersEditUrl($setupId, array $accountNumbers)
	{
		try
		{
			$setup = Trading\Setup\Model::loadById($setupId);
			$orderRegistry = $setup->getEnvironment()->getOrderRegistry();
			$numberToExternalIdMap = $orderRegistry->suggestExternalIds($accountNumbers, 'ACCOUNT_NUMBER', $setup->getPlatform());
			$externalIdToNumberMap = array_flip($numberToExternalIdMap);
			$externalToIdMap = $orderRegistry->searchList($numberToExternalIdMap, $setup->getPlatform(), false);
			$idToExternalMap = array_flip($externalToIdMap);
			$result = [];

			foreach ($orderRegistry->loadOrderList($externalToIdMap) as $id => $order)
			{
				$externalId = $idToExternalMap[$id];
				$accountNumber = $externalIdToNumberMap[$externalId];

				$result[$accountNumber] = $order->getAdminEditUrl();
			}

			return $result;
		}
		catch (Exceptions\Trading\SetupNotFound $exception)
		{
			return [];
		}
	}

	private function loadRowsOfferId(array $rows)
	{
		$rows = $this->loadRowsOfferIdBySku($rows);
		$rows = $this->loadRowsOfferIdById($rows);

		return $rows;
	}

	private function loadRowsOfferIdBySku(array $rows)
	{
		$skuRows = array_filter($rows, static function(array $row) { return $row['ENTITY_TYPE'] === Catalog\Glossary::ENTITY_SKU; });

		foreach (ArrayHelper::groupBy($skuRows, 'SETUP_ID') as $catalogId => $rowsChunk)
		{
			$query = Catalog\Run\Storage\AssortmentTable::getList([
				'filter' => [
					'=CATALOG_ID' => $catalogId,
					'=SKU' => array_values(array_column($rowsChunk, 'ENTITY_ID', 'ENTITY_ID')),
					'>ELEMENT_ID' => 0,
				],
				'select' => [ 'ELEMENT_ID', 'SKU' ],
			]);

			$assortment = array_column($query->fetchAll(), 'ELEMENT_ID', 'SKU');
			$iblockElements = $this->iblockElements(array_values($assortment));

			foreach ($rowsChunk as $key => $row)
			{
				if (!isset($assortment[$row['ENTITY_ID']], $iblockElements[$assortment[$row['ENTITY_ID']]]))
				{
					$rows[$key]['OFFER_ID'] = $row['ENTITY_ID'];
					continue;
				}

				$elementId = $assortment[$row['ENTITY_ID']];
				$iblockElement = $iblockElements[$assortment[$row['ENTITY_ID']]];

				$rows[$key]['OFFER_ID'] = "[{$elementId}] {$iblockElement['NAME']}";
				$rows[$key]['OFFER_URL'] = $iblockElement['URL'];
			}
		}

		return $rows;
	}

	private function loadRowsOfferIdById(array $rows)
	{
		$offerRows = array_filter($rows, static function(array $row) { return $row['ENTITY_TYPE'] === Glossary::ENTITY_OFFER; });
		$offerIds = array_diff_key(array_column($offerRows, 'ENTITY_ID', 'ENTITY_ID'), [ 0 => true ]);
		$iblockElements = $this->iblockElements(array_values($offerIds));

		foreach ($offerRows as $key => $row)
		{
			if (!isset($iblockElements[$row['ENTITY_ID']]))
			{
				$rows[$key]['OFFER_ID'] = $row['ENTITY_ID'];
				continue;
			}

			$iblockElement = $iblockElements[$row['ENTITY_ID']];

			$rows[$key]['OFFER_ID'] = "[{$row['ENTITY_ID']}] {$iblockElement['NAME']}";
			$rows[$key]['OFFER_URL'] = $iblockElement['URL'];
		}

		return $rows;
	}

	/** @noinspection PhpDeprecationInspection */
	private function iblockElements(array $ids)
	{
		if (empty($ids) || !Main\Loader::includeModule('iblock')) { return []; }

		$result = [];

		$query = Iblock\ElementTable::getList([
			'filter' => [ '=ID' => $ids ],
			'select' => [ 'IBLOCK_ID', 'ID', 'NAME' ],
		]);

		while ($row = $query->fetch())
		{
			$result[$row['ID']] = [
				'URL' => \CIBlock::GetAdminElementEditLink($row['IBLOCK_ID'], $row['ID']),
				'NAME' => $row['NAME'],
			];
		}

		return $result;
	}
}