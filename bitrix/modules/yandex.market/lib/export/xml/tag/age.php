<?php
namespace Yandex\Market\Export\Xml\Tag;

use Yandex\Market;

/** @property Market\Type\AgeType $type */
class Age extends Base
{
	public function getDefaultParameters()
	{
		return [
			'name' => 'age',
			'value_type' => Market\Type\Manager::TYPE_AGE
		];
	}

    protected function typeSettings(array $tagValue = null, array $siblingsValues = null)
    {
        $unitAttribute = $this->getParameter('unit_attribute', 'unit');

        if (isset($tagValue['ATTRIBUTES'][$unitAttribute]))
        {
            return [
                'value_unit' => mb_strtolower(trim($tagValue['ATTRIBUTES'][$unitAttribute])),
            ];
        }

        return null;
    }
}