<?php

namespace Yandex\Market\Export\Xml\Tag;

use Yandex\Market\Type;
use Yandex\Market\Reference\Concerns as GlobalConcerns;
use Yandex\Market\Export\Xml\Routine\Recommendation;

class Barcode extends Base
    implements Concerns\HasTagValueModifier
{
	use GlobalConcerns\HasMessage;

	public function getDefaultParameters()
	{
		return [
			'name' => 'barcode',
			'value_type' => Type\Manager::TYPE_BARCODE,
		];
	}

	public function preselect(array $context)
	{
		$recommendation = $this->getSourceRecommendation($context);

		if (empty($recommendation)) { return null; }

		$used = [];
		$result = [];

		foreach ($recommendation as $map)
		{
			if (isset($used[$map['TYPE']])) { continue; }

			$result[] = $map;
			$used[$map['TYPE']] = true;
		}

		return $result;
	}

	public function getSourceRecommendation(array $context = [])
	{
		return Recommendation\Property::filter([
			'LOGIC' => 'OR',
			[ '%CODE' => [ 'BAR_CODE', 'BARCODE' ] ],
			[ '%NAME' => explode(',', self::getMessage('FILTER_TITLE')) ],
		], $context);
	}

    public function modifyTagValues(array $tagValues, array $context)
    {
        $newResult = [];

        foreach ($tagValues as $tagValue)
        {
            if (!isset($tagValue['VALUE']) || !is_string($tagValue['VALUE']))
            {
                $newResult[] = $tagValue;
                continue;
            }

            foreach (preg_split('#[,;]#', $tagValue['VALUE']) as $barcode)
            {
                $newResult[] = [ 'VALUE' => $barcode ] + $tagValue;
            }
        }

        return $newResult;
    }
}