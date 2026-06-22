<?php
namespace Yandex\Market\Export\Xml\Tag;

use Yandex\Market\Error;

class OldPrice extends Price
{
	use Concerns\HasPackUnitDependency;

	public function getDefaultParameters()
	{
		return [ 'name' => 'oldprice' ] + parent::getDefaultParameters();
	}

	public function extendTagDescriptionList(&$tagDescriptionList, array $context)
	{
		parent::extendTagDescriptionList($tagDescriptionList, $context);
		$this->copyPricePackUnitSetting($tagDescriptionList, $context);
	}

    public function sanitize($value, array $context = [], array $tagValue = null, array $siblingsValues = null)
    {
        $oldPrice = parent::sanitize($value, $context, $tagValue, $siblingsValues);

        if ($siblingsValues === null || !is_numeric($oldPrice)) { return $oldPrice; }

        $priceTag = $this->getTagValues($siblingsValues, $this->getParameter('price_name', 'price'));

        if (!isset($priceTag['VALUE'])) { return new Error\SkipError(); }

        $price = parent::sanitize($priceTag['VALUE'], $context, $tagValue, $siblingsValues);

        if (!is_numeric($price)) { return new Error\SkipError(); }

        $price = (int)$price;
	    $oldPrice = (int)$oldPrice;

        if ($oldPrice <= 0 || $oldPrice <= $price) { return new Error\SkipError(); }

        $percent = ($oldPrice - $price) / $oldPrice;

        if ($percent < 0.05 || $percent > 0.99) { return new Error\SkipError(); }

        return $oldPrice;
    }

	public function getSourceRecommendation(array $context = [])
	{
		$variants = parent::getSourceRecommendation($context);

		foreach ($variants as $key => &$sourceMap)
		{
			if (mb_strpos($sourceMap['FIELD'], '.DISCOUNT_VALUE') === false)
			{
				unset($variants[$key]);
				continue;
			}

			$sourceMap['FIELD'] = str_replace('.DISCOUNT_VALUE', '.VALUE', $sourceMap['FIELD']);
		}
		unset($sourceMap);

		return $variants;
	}

	public function getSettingsDescription(array $context = [])
	{
		return [];
	}
}