<?php
namespace Yandex\Market\Catalog\Endpoint;

use Yandex\Market\Api;
use Yandex\Market\Api\Reference\Auth;
use Yandex\Market\Catalog;
use Yandex\Market\Error;
use Yandex\Market\Export\Xml\Listing;
use Yandex\Market\Logger\Trading\Audit;
use Yandex\Market\Reference\Concerns;
use Yandex\Market\Result;
use Yandex\Market\Psr\Log\LoggerInterface;
use Yandex\Market\Type;
use Yandex\Market\Utils;

class Card implements Driver, DriverWithPrepareCategory
{
    use Concerns\HasMessage;
    use Concerns\HasOnce;

    private $businessId;
    private $valueTypeCache;
    private $unitTypeCache;

    public function __construct($businessId)
    {
        $this->businessId = (int)$businessId;
    }

    public function type()
    {
        return Catalog\Glossary::ENDPOINT_CARD;
    }

    public function campaignId()
    {
        return 0;
    }

	public function audit()
	{
		return Audit::CATALOG_CARD;
	}

    public function priority($placementStatus, array $prepared, array $submitted = null)
    {
        if (!PriorityDictionary::wasPublished($placementStatus))
        {
            return PriorityDictionary::CARD_NEW;
        }

        return PriorityDictionary::CARD_PUBLISHED;
    }

    public function limit()
    {
        return 100;
    }

    public function prepareCategory($categoryId, array $bag, Auth $auth, LoggerInterface $logger)
    {
        list($categoryParameters, $error) = $this->categoryParameters($categoryId, $auth, $logger);

        if ($error !== null)
        {
            $result = new Result\Base();
            $result->addError($error);

            return array_fill_keys(array_keys($bag), $result);
        }

        $prepareResults = [];

        foreach ($bag as $sku => $payload)
        {
            list($parameterValues, $errors) = $this->preparePayload($payload, $categoryParameters);

            $skuResult = new Result\Base();

            if (!empty($parameterValues))
            {
	            $skuResult->addWarnings($errors + $this->validatePayload($categoryParameters, $parameterValues));

                $skuResult->setData([
                    'categoryId' => (int)$categoryId,
                    'parameterValues' => $parameterValues,
                ]);
            }
            else if (!empty($errors))
            {
                $skuResult->addErrors($errors);
            }
            else
            {
                $skuResult->addError(new Error\Base(self::getMessage('EMPTY')));
            }

            $prepareResults[$sku] = $skuResult;
        }

        $this->clearOnce('valueType');
        $this->clearOnce('unitType');

        return $prepareResults;
    }

    private function categoryParameters($categoryId, Auth $auth, LoggerInterface $logger)
    {
        try
        {
            $categoryId = (int)$categoryId;

            if ($categoryId <= 0)
            {
                return [ null, new Error\Base(self::getMessage('CATEGORY_ID_EMPTY')) ];
            }

            $request = new Api\Category\Parameters\Request($auth, $logger);
            $request->setCategoryId($categoryId);

            return [ $request->execute()->getCategoryParameters() ];
        }
        catch (Api\Exception\NotFoundException $exception)
        {
            if ($exception->getErrorCode() === Api\Category\Parameters\Request::CATEGORY_NOT_FOUND)
            {
	            return [ null, new Error\Base($exception->getMessage()) ];
            }

            throw $exception;
        }
    }

    private function preparePayload(array $payload, Api\Category\Parameters\Model\CategoryParameterCollection $categoryParameters)
    {
        $export = [];
        $errors = [];
        $preparedGroups = empty($payload['parameterValues']) ? [] : Utils\ArrayHelper::groupBy($payload['parameterValues'], 'parameterId');
        $propertyGroups = empty($payload['param']) ? [] : Utils\ArrayHelper::groupBy(array_map(
            static function(array $param) { return $param + [ 'nameKey' => mb_strtolower(trim($param['name'])) ]; },
            $payload['param']
        ), 'nameKey');
        $foundIds = [];
		$foundNames = [];

        /** @var Api\Category\Parameters\Model\CategoryParameter $categoryParameter */
        foreach ($categoryParameters as $categoryParameter)
        {
            $parameterId = $categoryParameter->getId();

            if (isset($foundIds[$parameterId])) { continue; }

	        $name = mb_strtolower(trim($categoryParameter->getName()));
	        $unit = $categoryParameter->getUnit();
			$nameVariants = [
				$name => true,
			];

			if ($unit !== null)
			{
				$defaultUnit = $unit->getDefaultUnit();
				$nameWithUnit = mb_strtolower("{$name}, {$defaultUnit['name']}");
				$nameVariants[$nameWithUnit] = [
					'unitId' => $defaultUnit['id'],
				];
			}

            if (isset($preparedGroups[$parameterId]))
            {
	            $foundIds[$parameterId] = true;
	            $foundNames += $nameVariants;
                array_push($export, ...$preparedGroups[$parameterId]);
                continue;
            }

			$defined = null;
	        $propertyGroup = null;

			foreach ($nameVariants as $nameVariant => $nameDefined)
			{
				if (isset($propertyGroups[$nameVariant]))
				{
					$propertyGroup = $propertyGroups[$nameVariant];
					$defined = $nameDefined !== true ? $nameDefined : null;
					$foundNames[$nameVariant] = true;
					break;
				}
			}

			if ($propertyGroup === null) { continue; }

	        $foundIds[$parameterId] = true;

            foreach ($propertyGroup as $param)
            {
                list($value, $valueError) = $this->paramValue($param, $categoryParameter);

                if ($valueError instanceof Error\Base)
                {
                    $errors[$parameterId] = new Error\Base(sprintf(
						'%s: %s',
						$categoryParameter->getName(),
						$valueError->getMessage()
                    ));
                    continue;
                }

                if ($value === null) { continue; }

	            $unitParent = $this->paramUnitParent($param, $categoryParameter, $categoryParameters);

	            if ($unitParent !== null && !isset($foundIds[$unitParent['parameterId']]))
	            {
		            $export[] = $unitParent;
		            $foundIds[$unitParent['parameterId']] = true;
	            }

	            list($valueId, $valueIdError) = $this->paramValueId($value, $categoryParameter, $categoryParameters, $export);

	            if ($valueIdError instanceof Error\Base)
	            {
		            $errors[$parameterId] = new Error\Base(sprintf(
			            '%s: %s',
			            $categoryParameter->getName(),
			            $valueIdError->getMessage()
		            ));
		            continue;
	            }

	            list($unitId, $unitError) = $this->paramUnit($param, $categoryParameter->getUnit());

	            if ($unitError instanceof Error\Base)
	            {
		            $errors[$parameterId] = new Error\Base(sprintf(
			            '%s: %s (%s)',
			            $categoryParameter->getName(),
			            $valueError->getMessage(),
			            self::getMessage('UNIT_ERROR')
		            ));
		            continue;
	            }

                $exportItem = [
                    'parameterId' => $parameterId,
                    'value' => $value,
                ];

                if ($valueId !== null)
                {
                    $exportItem['valueId'] = $valueId;
                }

                if ($unitId !== null)
                {
                    $exportItem['unitId'] = $unitId;
                }

				if ($defined !== null)
				{
					$exportItem += $defined;
				}

                $export[] = $exportItem;
            }
        }

		$otherId = Api\Category\Parameters\Model\CategoryParameter::ID_OTHER;
		$otherParameter = $categoryParameters->getItemById($otherId);

		if ($otherParameter !== null && !isset($foundIds[$otherId]))
		{
			$otherValue = $this->paramOther(array_diff_key($propertyGroups, $foundNames));

			if ($otherValue !== '' || !empty($export))
			{
				$export[] = [
					'parameterId' => $otherId,
					'value' => $otherValue,
				];
			}
		}

        return [ $export, $errors ];
    }

    private function paramValue(array $param, Api\Category\Parameters\Model\CategoryParameter $categoryParameter)
    {
        if (!isset($param['value']) || Utils\Value::isEmpty($param['value'])) { return [ null, null ]; }

        $valueType = $this->valueType($categoryParameter);
        $sanitized = $valueType->sanitize($param['value']);

		if ($sanitized instanceof Error\Base)
		{
			if (
				$valueType instanceof Type\EnumType
				&& $sanitized->getCode() === Type\EnumType::ERROR_INVALID
				&& $categoryParameter->isAllowCustomValues()
			)
			{
				return [ $param['value'], null ];
			}

			return [ null, $sanitized ];
		}

        return [ $sanitized, null ];
    }

    private function valueType(Api\Category\Parameters\Model\CategoryParameter $categoryParameter)
    {
        return $this->once('valueType', [ $categoryParameter ], function (Api\Category\Parameters\Model\CategoryParameter $categoryParameter) {
            $parameterType = $categoryParameter->getType();

            if ($parameterType === Api\Category\Parameters\Model\CategoryParameter::TYPE_TEXT)
            {
                return new Type\StringType();
            }

            if ($parameterType === Api\Category\Parameters\Model\CategoryParameter::TYPE_BOOLEAN)
            {
                return new Type\BooleanType();
            }

            if ($parameterType === Api\Category\Parameters\Model\CategoryParameter::TYPE_NUMERIC)
            {
                return new Type\NumberType([
                    Type\NumberType::SETTING_PRECISION => 2,
                ]);
            }

            if ($parameterType === Api\Category\Parameters\Model\CategoryParameter::TYPE_ENUM)
            {
                $values = array_values(array_column((array)$categoryParameter->getValues(), 'value', 'value'));

                return new Type\EnumType(new Listing\Custom($values), [
					'value_synonym' => !$categoryParameter->isAllowCustomValues() || $categoryParameter->isFiltering(),
                ]);
            }

            trigger_error(sprintf('unknown category parameter type %s', $parameterType));

            return new Type\StringType();
        });
    }

    private function paramUnit(array $param, Api\Category\Parameters\Model\Unit $parameterUnit = null)
    {
        if ($parameterUnit === null || !isset($param['unit'])) { return [ null, null ]; }

        $unitType = $this->unitType($parameterUnit);
        $sanitized = $unitType->sanitize($param['unit']);

        if ($sanitized === null) { return [ null, null ]; }
        if ($sanitized instanceof Error\XmlNode) { return [ null, $sanitized ]; }

        $id = array_search($sanitized, $unitType->listing()->values(), true);

        if ($id === false)
        {
            return [ null, new Error\Base(sprintf('cant find unitId %s', $sanitized)) ];
        }

        return [ $id, null ];
    }

    /** @return Type\EnumType */
    private function unitType(Api\Category\Parameters\Model\Unit $unit)
    {
        return $this->once('unitType', [ $unit ], function(Api\Category\Parameters\Model\Unit $unit) {
            $units = $unit->getUnits();
            $names = array_column($units, 'name', 'id');
            $fullNames = array_column($units, 'fullName', 'id');

            return new Type\EnumType(new Listing\Custom($names, $fullNames));
        });
    }

	private function paramUnitParent(
		array $param,
		Api\Category\Parameters\Model\CategoryParameter $categoryParameter,
		Api\Category\Parameters\Model\CategoryParameterCollection $categoryParameters
	)
	{
		if (!isset($param['unit']) || (!is_string($param['unit']))) { return null; }

		$unitValue = mb_strtoupper(trim($param['unit']));

		if ($unitValue === '') { return null; }

		/** @var Api\Category\Parameters\Model\ValueRestriction $valueRestriction */
		foreach ($categoryParameter->getValueRestrictions() as $valueRestriction)
		{
			$restrictionParameter = $categoryParameters->getItemById($valueRestriction->getLimitingParameterId());

			if ($restrictionParameter === null) { continue; }

			foreach ((array)$restrictionParameter->getValues() as $restrictionParameterValue)
			{
				if (mb_strtoupper($restrictionParameterValue['value']) === $unitValue)
				{
					return [
						'parameterId' => $restrictionParameter->getId(),
						'value' => $restrictionParameterValue['value'],
						'valueId' => $restrictionParameterValue['id'],
					];
				}
			}
		}

		return null;
	}

	private function paramValueId(
		$value,
		Api\Category\Parameters\Model\CategoryParameter $categoryParameter,
		Api\Category\Parameters\Model\CategoryParameterCollection $categoryParameters,
		array $parameterValues
	)
	{
		$valuesDto = $categoryParameter->getValues();

		if ($valuesDto === null) { return [ null, null ]; }

		$matched = array_column(
			array_filter($valuesDto, static function(array $option) use ($value) { return (string)$option['value'] === (string)$value; }),
			'value',
			'id'
		);

		if (empty($matched))
		{
			if ($categoryParameter->isAllowCustomValues()) { return [ null, null ]; }

			$error = new Error\Base(self::getMessage('VALUE_ID_NOT_FOUND', [
				'#VALUE#' => $value,
			]));

			return [ null, $error ];
		}

		$restrictions = $categoryParameter->getValueRestrictions();
		$restricted = $restrictions->getRestricted($parameterValues);

		if ($restricted !== null)
		{
			$matched = array_intersect_key($matched, array_flip($restricted));

			if (empty($matched))
			{
				$error = new Error\Base(self::getMessage('VALUE_ID_RESTRICTED', [
					'#VALUE#' => $value,
					'#RESTRICTED_VALUES#' => implode(', ', array_filter(array_map(static function(Api\Category\Parameters\Model\ValueRestriction $valueRestriction) use ($categoryParameters, $parameterValues) {
						$parameterId = $valueRestriction->getLimitingParameterId();
						$categoryParameter = $categoryParameters->getItemById($parameterId);

						if ($categoryParameter === null) { return null; }

						$selectedValues = array_filter($parameterValues, static function(array $parameterValue) use ($parameterId) {
							return $parameterValue['parameterId'] === $parameterId;
						});

						if (empty($selectedValues)) { return null; }

						return sprintf('%s: %s', $categoryParameter->getName(), implode(', ', array_column($selectedValues, 'value')));
					}, $restrictions->asArray()))),
				]));

				return [ null, $error ];
			}
		}

		if (count($matched) > 1 && $restrictions->count() > 0)
		{
			$error = new Error\Base(self::getMessage('VALUE_ID_FEW_VARIANTS', [
				'#VALUE#' => $value,
				'#RESTRICTED_NAME#' => implode(', ', array_filter(array_map(static function(Api\Category\Parameters\Model\ValueRestriction $valueRestriction) use ($categoryParameters) {
					$parameterId = $valueRestriction->getLimitingParameterId();
					$categoryParameter = $categoryParameters->getItemById($parameterId);

					if ($categoryParameter === null) { return null; }

					return $categoryParameter->getName();
				}, $restrictions->asArray()))),
			]));

			return [ null, $error ];
		}

		reset($matched);

		return [ key($matched), null ];
	}

	private function paramOther(array $paramGroups)
	{
		$rows = [];

		foreach ($paramGroups as $paramGroup)
		{
			$row = '';

			foreach ($paramGroup as $param)
			{
				if (Utils\Value::isEmpty($param['value'])) { continue; }

				if ($row === '')
				{
					$row = $param['name'];
					$row .= !empty($param['unit']) ? ", {$param['unit']}" : '';
					$row .= ': ';
				}
				else
				{
					$row .= ', ';
				}

				$row .= is_array($param['value']) ? implode(', ', $param['value']) : $param['value'];
			}

			$rows[] = $row;
		}

		return implode(PHP_EOL, $rows);
	}

	private function validatePayload(
		Api\Category\Parameters\Model\CategoryParameterCollection $categoryParameters,
		array $parameterValues
	)
	{
		$warnings = [];
		$preparedGroups = Utils\ArrayHelper::groupBy($parameterValues, 'parameterId');

		/** @var Api\Category\Parameters\Model\CategoryParameter $categoryParameter */
		foreach ($categoryParameters as $categoryParameter)
		{
			$parameterId = $categoryParameter->getId();

			if (!isset($preparedGroups[$parameterId]))
			{
				if ($categoryParameter->isRequired())
				{
					$warnings[$parameterId] = new Error\Base(sprintf(
						'%s: %s',
						$categoryParameter->getName(),
						self::getMessage('PARAMETER_REQUIRED')
					));
				}

				continue;
			}

			$valueRestrictions = $categoryParameter->getValueRestrictions();

			if ($valueRestrictions->count() === 0) { continue; }

			foreach ($preparedGroups[$parameterId] as $parameterValue)
			{
				if (!isset($parameterValue['valueId'])) { continue; }

				foreach ($valueRestrictions->getDependsOn($parameterValue['valueId']) as $restrictionParameterId => $restrictionValueIds)
				{
					$restrictionParameter = $categoryParameters->getItemById($restrictionParameterId);

					if ($restrictionParameter === null) { continue; }

					if (!isset($preparedGroups[$restrictionParameterId]))
					{
						$warnings[$restrictionParameterId] = new Error\Base(sprintf(
							'%s: %s',
							$categoryParameter->getName(),
							self::getMessage('RESTRICTED_REQUIRED', [ '#RESTRICTED_NAME#' => $restrictionParameter->getName() ])
						));
						break;
					}

					foreach ($preparedGroups[$restrictionParameterId] as $restrictedValue)
					{
						if (!isset($restrictedValue['valueId']))
						{
							$warnings[$restrictionParameterId] = new Error\Base(sprintf(
								'%s: %s',
								$restrictionParameter->getName(),
								self::getMessage('RESTRICTED_WITHOUT_VALUE_ID')
							));
							break;
						}

						if (!in_array($restrictedValue['valueId'], $restrictionValueIds, true))
						{
							$restrictedAvailable = array_filter((array)$restrictionParameter->getValues(), static function(array $option) use ($restrictionValueIds) {
								return in_array($option['id'], $restrictionValueIds, true);
							});

							$warnings[$parameterId] = new Error\Base(new Error\Base(sprintf(
								'%s: %s',
								$categoryParameter->getName(),
								self::getMessage('RESTRICTED_INVALID_DEPENDENCY', [
									'#PARAMETER_VALUE#' => $parameterValue['value'],
									'#RESTRICTED_NAME#' => $restrictionParameter->getName(),
									'#RESTRICTED_VALUE#' => $restrictedValue['value'],
									'#RESTRICTED_AVAILABLE#' => count($restrictedAvailable) > 10
										? implode(', ', array_column(array_slice($restrictedAvailable, 0, 10), 'value')) . '...'
										: implode(', ', array_column($restrictedAvailable, 'value')),
								])
							)));
							break;
						}
					}
				}
			}
		}

		return $warnings;
	}

    public function submit(array $payloadBag, Auth $auth, LoggerInterface $logger)
    {
		$offersContent = $this->compileOffersContent($payloadBag);

		$skus = array_keys($payloadBag);
	    $offerResults = array_combine($skus, array_map(static function() { return new Result\Base(); }, $skus));

        $request = new Api\Business\OfferCards\Update\Request($this->businessId, $auth, $logger);
        $request->setOffersContent($offersContent);

        $response = $request->execute();

		$errorOfferIds = $this->fillResponseResult($offerResults, $response->getResults());
	    $repeatOffers = $this->sliceErrorOffers($offersContent, $errorOfferIds);

	    if (!empty($repeatOffers))
	    {
		    $request->setOffersContent($repeatOffers);
		    $response = $request->execute();

		    $this->fillResponseResult($offerResults, $response->getResults());
	    }

		$this->logSubmitted($offerResults, $payloadBag, $logger);

        return $offerResults;
    }

    private function compileOffersContent(array $payloadBag)
    {
        $result = [];

        foreach ($payloadBag as $sku => $payload)
        {
            $result[] = [ 'offerId' => (string)$sku ] + $payload;
        }

        return $result;
    }

    private function fillResponseResult(array $submitResults, Api\Business\OfferCards\Update\OfferResultCollection $offerResultCollection)
    {
	    $errorOfferIds = [];

        /** @var Api\Business\OfferCards\Update\OfferResult $offerResult */
        foreach ($offerResultCollection as $offerResult)
        {
            $offerId = $offerResult->getOfferId();

            if (!isset($submitResults[$offerId])) { continue; }

            /** @var Result\Base $submitResult */
            $submitResult = $submitResults[$offerId];

            /** @var Api\Business\OfferCards\Update\OfferError $error */
            foreach ($offerResult->getErrors() as $error)
            {
	            $errorOfferIds[$offerId] = $offerId;
                $submitResult->addError(new Error\Base($error->errorMessage()));

				if ($error->getType() === Api\Business\OfferCards\Update\OfferError::OFFER_NOT_FOUND)
				{
					$submitResult->setData([ 'REPEAT' => true ]);
				}
            }

			if ($submitResult->hasWarnings()) { continue; }

            /** @var Api\Business\OfferCards\Update\OfferError $error */
            foreach ($offerResult->getWarnings() as $error)
            {
                $submitResult->addWarning(new Error\Base($error->errorMessage()));
            }
        }

		return array_values($errorOfferIds);
    }

	private function sliceErrorOffers(array $offersContent, array $errorOfferIds)
	{
		if (empty($errorOfferIds)) { return null; }

		$errorOfferMap = array_flip($errorOfferIds);

		return array_values(array_filter($offersContent, static function(array $offer) use ($errorOfferMap) {
			return !isset($errorOfferMap[$offer['offerId']]);
		}));
	}

	private function logSubmitted(array $offerResults, array $payloadBag, LoggerInterface $logger)
	{
		foreach ($offerResults as $sku => $offerResult)
		{
			if (!$offerResult->isSuccess()) { continue; }

			$logger->info(self::getMessage('SUBMITTED', [
				'#CATEGORY#' => $payloadBag[$sku]['categoryId'],
				'#PARAMETERS#' => implode(', ', array_column($payloadBag[$sku]['parameterValues'], 'value')),
			]), [
				'ENTITY_TYPE' => Catalog\Glossary::ENTITY_SKU,
				'ENTITY_ID' => $sku,
			]);
		}
	}
}