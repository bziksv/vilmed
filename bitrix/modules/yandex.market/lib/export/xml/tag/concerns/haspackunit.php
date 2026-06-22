<?php
namespace Yandex\Market\Export\Xml\Tag\Concerns;

use Yandex\Market\Type;

trait HasPackUnit
{
    protected function compileType(array $parameters)
    {
        $type = parent::compileType($parameters);

        return new Type\Decorator\PackRatio($type, $this->isPackRatioInverted());
    }

    protected function typeSettings(array $tagValue = null, array $siblingsValues = null)
    {
        if (isset($tagValue['SETTINGS']['PACK_RATIO']))
        {
            return [
                'pack_ratio' => $tagValue['SETTINGS']['PACK_RATIO'],
            ];
        }

        return null;
    }

	protected function isPackRatioInverted()
	{
		return false;
	}
}