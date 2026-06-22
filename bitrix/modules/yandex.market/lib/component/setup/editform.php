<?php
namespace Yandex\Market\Component\Setup;

use Bitrix\Main;
use Yandex\Market;
use Yandex\Market\Component\Molecules;

class EditForm extends Market\Component\Model\EditForm
{
	use Market\Reference\Concerns\HasMessage;

	private $repository;
	private $productLink;
	private $productParam;
	private $productFilter;

	public function __construct(\CBitrixComponent $component)
	{
		parent::__construct($component);

		$this->productLink = new Molecules\ProductLink([
			'IBLOCK_LINK',
		]);
		$this->productParam = new Molecules\ProductParam([
			'IBLOCK_LINK.PARAM',
		]);
		$this->productFilter = new Molecules\ProductFilter([
			'IBLOCK_LINK.FILTER',
		]);
	}

	public function modifyRequest(array $request, array $fields)
	{
		$result = parent::modifyRequest($request, $fields);

		if (isset($request['IBLOCK']))
		{
			$result = $this->productLink->sanitizeIblock($result, (array)$request['IBLOCK']);
		}

		return $result;
	}

	public function load($primary, array $select = [], $isCopy = false)
	{
		$result = parent::load($primary, $select, $isCopy);
		$result = $this->productParam->parse($result);

		if ($isCopy)
		{
			$copyNameMarker = (string)self::getMessage('COPY_MARKER', null, '');

			if (
				$copyNameMarker !== ''
				&& isset($result['NAME'])
				&& mb_stripos($result['NAME'], $copyNameMarker) === false
			)
			{
				$result['NAME'] .= ' ' . $copyNameMarker;
			}

			if (isset($result['FILE_NAME']))
			{
				$result['FILE_NAME'] = null;
			}
		}

		return $result;
	}

	public function validate(array $data, array $fields)
	{
		$result = parent::validate($data, $fields);

		$this->validateIblock($result, $data);
		$this->validateDelivery($result, $data, $fields);
		$this->productFilter->validate($result, $data, $fields);
		$this->validateFilterDistinct($result, $data, $fields);

		return $result;
	}

	private function validateIblock(Main\Entity\Result $result, $data)
	{
		if (empty($data['IBLOCK_LINK']))
		{
			$result->addError(new Market\Error\EntityError(
				self::getMessage('ERROR_IBLOCK_EMPTY'),
				0,
				[ 'FIELD' => 'IBLOCK' ]
			));
		}
	}

	private function validateDelivery(Main\Entity\Result $result, $data, array $fields = null)
	{
		if (isset($fields['DELIVERY'])) // has delivery in validation list
		{
			$deliveryTypeList = [
				Market\Export\Delivery\Table::DELIVERY_TYPE_DELIVERY,
			];

			foreach ($deliveryTypeList as $deliveryType)
			{
				if (empty($data['DELIVERY']) || !$this->isValidDeliveryDataList($data['DELIVERY'], $deliveryType)) // and empty primary delivery
				{
					$childWithDeliveryOptions = null;

					foreach ($data['IBLOCK_LINK'] as $iblockLink)
					{
						// has in param

						if (!empty($iblockLink['PARAM']))
						{
							foreach ($iblockLink['PARAM'] as $tagDescription)
							{
								if (!empty($tagDescription['PARAM_VALUE']) && $tagDescription['XML_TAG'] === 'delivery-options')
								{
									foreach ($tagDescription['PARAM_VALUE'] as $tagValue)
									{
										if (!empty($tagValue['SOURCE_TYPE']) && !empty($tagValue['SOURCE_FIELD']))
										{
											$childWithDeliveryOptions = 'PARAM';
											break 3;
										}
									}
								}
							}
						}

						// has in filter

						if (!empty($iblockLink['DELIVERY']) && $this->isValidDeliveryDataList($iblockLink['DELIVERY'], $deliveryType))
						{
							$childWithDeliveryOptions = 'FILTER';
							break;
						}

						if (!empty($iblockLink['FILTER']))
						{
							foreach ($iblockLink['FILTER'] as $filter)
							{
								if (!empty($filter['DELIVERY']) && $this->isValidDeliveryDataList($filter['DELIVERY'], $deliveryType))
								{
									$childWithDeliveryOptions = 'FILTER';
									break 2;
								}
							}
						}
					}

					if ($childWithDeliveryOptions !== null)
					{
						$result->addError(new Market\Error\EntityError(
							self::getMessage('ERROR_CHILD_DELIVERY_OPTIONS_WITHOUT_ROOT_BY_' . $childWithDeliveryOptions),
							0,
							[ 'FIELD' => 'DELIVERY' ]
						));
						break;
					}
				}
			}
		}
	}

	private function validateFilterDistinct(Main\Entity\Result $result, $data, array $fields = null)
	{
		if (!empty($data['IBLOCK_LINK']))
		{
			foreach ($data['IBLOCK_LINK'] as $iblockLinkIndex => $iblockLink)
			{
				$filterFieldName = 'IBLOCK_LINK_' . $iblockLinkIndex . '_FILTER';
				$filterInputName = 'IBLOCK_LINK[' . $iblockLinkIndex . '][FILTER]';

				if (!isset($fields[$filterFieldName]) || empty($iblockLink['FILTER'])) { continue; }

				foreach ($iblockLink['FILTER'] as $filterRow)
				{
					if (empty($filterRow['FILTER_CONDITION'])) { continue; }

					$filter = Market\Export\Filter\Model::initialize($filterRow);
					$sourceFilter = $filter->getSourceFilter();

					foreach ($sourceFilter as $type => $filter)
					{
						$source = Market\Export\Entity\Manager::getSource($type);
						$queryFilter = $source->getQueryFilter($filter, []);

						if (empty($queryFilter['DISTINCT'])) { continue; }

						foreach ($queryFilter['DISTINCT'] as $distinctRule)
						{
							$isValid = true;
							$ruleType = null;

							if (isset($distinctRule['TAG'], $distinctRule['ATTRIBUTE']))
							{
								$isValid = $this->hasFilledTagSource($iblockLink, $distinctRule['TAG'], $distinctRule['ATTRIBUTE']);
								$ruleType = 'ATTRIBUTE';
							}
							else if (isset($distinctRule['TAG']))
							{
								$isValid = $this->hasFilledTagSource($iblockLink, $distinctRule['TAG']);
								$ruleType = 'TAG';
							}

							if (!$isValid)
							{
								$result->addError(new Market\Error\EntityError(
									self::getMessage('ERROR_DISTINCT_REQUIRE_' . $ruleType, $distinctRule),
									0,
									[ 'FIELD' => $filterInputName ]
								));
							}
						}
					}
				}
			}
		}
	}

	public function add(array $data)
	{
		$data = $this->productParam->compile($data);
		$this->fillGroupField($data);

		return parent::add($data);
	}

	public function update($primary, array $data)
	{
		$data = $this->productParam->compile($data);

		return parent::update($primary, $data);
	}

	public function getFields(array $select = [], array $item = null)
	{
		$result = parent::getFields($select, $item);
		$result = $this->writeParamFormat($result, $item);
		$result = $this->modifyNameField($result);
		$result = $this->modifyHttpsField($result);
		$result = $this->modifyDomainField($result);

		if (isset($result['EXPORT_SERVICE'], $result['EXPORT_FORMAT']))
		{
			$result = $this->getRepository()->makeServiceDependFields($result);
		}

		return $result;
	}

	private function writeParamFormat(array $fields, $item)
	{
		if (!isset($item['EXPORT_SERVICE'], $item['EXPORT_FORMAT'])) { return $fields; }

		foreach ($fields as &$field)
		{
			if (empty($field['FIELD_GROUP']) || isset($field['FORMAT'])) { continue; }

			if ($field['FIELD_GROUP'] === 'IBLOCK_LINK.PARAM')
			{
				$field['FORMAT'] = new ParamFormat($item['EXPORT_SERVICE'], $item['EXPORT_FORMAT']);
			}
		}
		unset($field);

		return $fields;
	}

	private function modifyNameField(array $fields)
	{
		if (!isset($fields['NAME'])) { return $fields; }

		$fields['NAME']['SETTINGS']['DEFAULT_VALUE'] = self::getMessage('DEFAULT_NAME');

		return $fields;
	}

	private function modifyHttpsField(array $fields)
	{
		if (!isset($fields['HTTPS'])) { return $fields; }

		$isHttps = Main\Application::getInstance()->getContext()->getRequest()->isHttps();
		$fields['HTTPS']['SETTINGS']['DEFAULT_VALUE'] = $isHttps ? Market\Ui\UserField\BooleanType::VALUE_Y : Market\Ui\UserField\BooleanType::VALUE_N;

		return $fields;
	}

	private function modifyDomainField(array $fields)
	{
		if (!isset($fields['DOMAIN'])) { return $fields; }

		$host = Main\Application::getInstance()->getContext()->getRequest()->getHttpHost();
		$siteId = Market\Data\SiteDomain::getSite($host);

		if ($siteId !== null && !Market\Data\Site::isCrm($siteId))
		{
			$fields['DOMAIN']['SETTINGS']['DEFAULT_VALUE'] = $host . $this->siteDomainDirectory($siteId);
			
			return $fields;
		}
	
		$found = null;

		foreach (Market\Data\Site::getVariants() as $variant)
		{
			if (Market\Data\Site::isCrm($variant)) { continue; }

			$variantHost = (string)Market\Data\SiteDomain::getHost($variant);

			if ($variantHost === '') { continue; }

			$found = $variantHost . $this->siteDomainDirectory($variant);
			break;
		}

		$fields['DOMAIN']['SETTINGS']['DEFAULT_VALUE'] = $found !== null ? $found : $host;

		return $fields;
	}

	private function siteDomainDirectory($siteId)
	{
		list(, $dir) = Market\Data\Site::getDocumentRoot($siteId);

		return $dir !== '/' ? $dir : '';
	}

	public function extend(array $data, array $fields)
	{
		$result = $data;

		if (!isset($result['FILE_NAME']) || trim($result['FILE_NAME']) === '')
		{
			/** @noinspection PhpDeprecationInspection */
			$result['FILE_NAME'] = 'export_' . randString(3) . '.xml';
		}

		if (!empty($result['IBLOCK_LINK']))
		{
			$setup = $this->loadSetupModel($data);
			$setupContext = $setup->getContext();

			foreach ($result['IBLOCK_LINK'] as &$iblockLink)
			{
				$iblockId = isset($iblockLink['IBLOCK_ID']) ? (int)$iblockLink['IBLOCK_ID'] : null;
				$iblockLink['CONTEXT'] = Market\Export\Entity\Iblock\Provider::getContext($iblockId) + $setupContext;
			}
			unset($iblockLink);
		}

		return $result;
	}

	public function ajaxActionFilterCount($data, $baseName = 'IBLOCK_LINK', $step = Market\Export\Run\Manager::STEP_OFFER)
	{
		$request = Main\Application::getInstance()->getContext()->getRequest();

		$setup = $this->loadSetupModel($data);
		$offset = null;
		$offsetName = $request->getPost('offsetName');

		if ($offsetName !== null && preg_match('/^' . $baseName . '\[(\d+)]\[FILTER](?:\[(\d+)])?/', $offsetName, $offsetNameMatches))
		{
			$offset = $offsetNameMatches[1] . (isset($offsetNameMatches[2]) ? ':' . $offsetNameMatches[2] : '');
		}

		return [ 'status' => 'ok' ] + $this->getFilterCount($setup, $offset, $baseName, $step);
	}

	public function getFilterCount(Market\Export\Setup\Model $setup, $offset = null, $baseName = 'IBLOCK_LINK', $step = Market\Export\Run\Manager::STEP_OFFER)
	{
		/** @var $offerStep Market\Export\Run\Steps\Offer */
		$processor = new Market\Export\Run\Processor($setup);
		$offerStep = Market\Export\Run\Manager::getStepProvider($step, $processor);

		$processor->loadModules();

		$filterCountList = $offerStep->getCount($offset, true);
		$iblockLinkIndex = (int)$offset;
		$filterIndex = 0;
		$result = [
			'countList' => [],
			'warningList' => [],
		];

		foreach ($filterCountList->getCountList() as $countKey => $count)
		{
			$countKeyParts = explode(':', $countKey);

			if (count($countKeyParts) === 2) // is filter group
			{
				$inputName = $baseName . '[' . $iblockLinkIndex . '][FILTER][' . $filterIndex . '][FILTER_CONDITION]';

				++$filterIndex;
			}
			else
			{
				$inputName = $baseName . '[' . $iblockLinkIndex . '][FILTER]';

				$filterIndex = 0;
				++$iblockLinkIndex;
			}

			$warning = $filterCountList->getCountWarning($countKey);

			$result['countList'][$inputName] = $count;
			$result['warningList'][$inputName] = $warning ? $warning->getMessage() : null;
		}

		return $result;
	}

	/**
	 * @param $data
	 *
	 * @return Market\Export\Setup\Model
	 */
	private function loadSetupModel($data)
	{
		/** @var \Yandex\Market\Export\Setup\Model $modelClassName */
		$modelClassName = $this->getModelClass();

		return $modelClassName::initialize($data);
	}

	private function isValidDeliveryDataList($dataList, $deliveryType)
	{
		$result = false;

		if (is_array($dataList))
		{
			foreach ($dataList as $data)
			{
				$isMatchType = (isset($data['DELIVERY_TYPE']) && $data['DELIVERY_TYPE'] === $deliveryType);

				if ($isMatchType && Market\Export\Delivery\Table::isValidData($data))
				{
					$result = true;
					break;
				}
			}
		}

		return $result;
	}

	private function hasFilledTagSource($iblockLink, $tagName, $attributeName = null)
	{
		$result = false;

		if (!empty($iblockLink['PARAM']))
		{
			foreach ($iblockLink['PARAM'] as $param)
			{
				if ($param['XML_TAG'] !== $tagName || empty($param['PARAM_VALUE'])) { continue; }

				foreach ($param['PARAM_VALUE'] as $paramValue)
				{
					if ($attributeName !== null)
					{
						$isMatch = (
							$paramValue['XML_TYPE'] === 'attribute'
							&& $paramValue['XML_ATTRIBUTE_NAME'] === $attributeName
						);
					}
					else
					{
						$isMatch = $paramValue['XML_TYPE'] === 'value';
					}

					if (
						$isMatch
						&& isset($paramValue['SOURCE_TYPE'], $paramValue['SOURCE_FIELD'])
						&& (string)$paramValue['SOURCE_TYPE'] !== ''
						&& (string)$paramValue['SOURCE_FIELD'] !== ''
					)
					{
						$result = true;
						break 2;
					}
				}
			}
		}

		return $result;
	}

	private function fillGroupField(&$fields)
	{
		$groupId = (int)$this->getComponentParam('GROUP');

		if ($groupId > 0)
		{
			$fields['GROUP'] = [ $groupId ];
		}
	}

	private function getRepository()
	{
		if ($this->repository === null)
		{
			$this->repository = $this->makeRepository();
		}

		return $this->repository;
	}

	private function makeRepository()
	{
		$modelClass = $this->getModelClass();

		return new Repository($modelClass);
	}
}