<?php
namespace Yandex\Market\Ui\Iblock\CategoryForm;

use Bitrix\Main;
use Yandex\Market\Data\Number;

class Factory
{
	const SECTION = 'section';
	const SECTION_GRID = 'sectionGrid';
	const ELEMENT = 'element';
	const ELEMENT_GRID = 'elementGrid';
	const OFFER_GRID = 'offerGrid';
	const OFFER = 'offer';
	const NULL_FORM = 'null';
	const MASSIVE_EDIT = 'massiveEdit';

	public static function restore($type, array $payload, array $fieldOrProperty)
	{
		switch ($type)
		{
			case self::SECTION:
				return new Section($fieldOrProperty, $payload['sectionId']);
			case self::SECTION_GRID:
				return new SectionGrid($fieldOrProperty, $payload['sectionId']);
			case self::ELEMENT:
				return new Element($fieldOrProperty, $payload['elementId']);
			case self::ELEMENT_GRID:
				return new ElementGrid($fieldOrProperty, $payload['elementId']);
			case self::OFFER_GRID:
				return new OfferGrid($fieldOrProperty, $payload['offerId']);
			case self::OFFER:
				return new Offer($fieldOrProperty, $payload['offerId'], $payload['skuPropertyId'], isset($payload['productId']) ? $payload['productId'] : null);
			case self::MASSIVE_EDIT:
				return new MassiveEdit($payload['parentValue']);
		}

		return new NullForm();
	}

	public static function makeSection(array $userField, array $htmlControl)
	{
		$request = self::httpRequest();

		if (isset($htmlControl['NAME']) && preg_match('/^FIELDS\[(\d+)]/', $htmlControl['NAME'], $matches))
		{
			return new SectionGrid($userField, (int)$matches[1]);
		}

		return new Section($userField, (int)$request->get('ID'));
	}

	public static function makeElement(array $property, array $htmlControl)
	{
		if (isset($htmlControl['VALUE']) && preg_match('/^FIELDS\[E?(\d+)]/', $htmlControl['VALUE'], $matches))
		{
			if (self::skuPropertyId($property['IBLOCK_ID']) !== null)
			{
				return new OfferGrid($property, (int)$matches[1]);
			}

			return new ElementGrid($property, (int)$matches[1]);
		}

		$request = self::httpRequest();
		$skuPropertyId = self::skuPropertyId($property['IBLOCK_ID']);

		if ($skuPropertyId !== null)
		{
			$productId = Number::castInteger($request->get('PRODUCT_ID'));

			return new Offer($property, (int)$request->get('ID'), $skuPropertyId, $productId);
		}

		$sectionId = $request->get('IBLOCK_SECTION_ID');
		$sections = $request->getPost('IBLOCK_SECTION');

		return new Element(
			$property,
			(int)$request->get('ID'),
			is_numeric($sectionId) ? (int)$sectionId : null,
			is_array($sections) ? array_filter(array_map('intval', $sections)) : null
		);
	}

	private static function skuPropertyId($iblockId)
	{
		if (Main\Loader::includeModule('catalog'))
		{
			$catalog = \CCatalogSku::GetInfoByIBlock($iblockId);

			if ($catalog !== false && $catalog['CATALOG_TYPE'] === \CCatalogSku::TYPE_OFFERS)
			{
				return (int)$catalog['SKU_PROPERTY_ID'];
			}
		}

		return null;
	}

	private static function httpRequest()
	{
		return Main\Application::getInstance()->getContext()->getRequest();
	}
}