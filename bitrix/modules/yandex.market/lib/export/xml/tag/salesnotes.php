<?php
namespace Yandex\Market\Export\Xml\Tag;

class SalesNotes extends Base
    implements Concerns\HasTagValueModifier
{
	public function getDefaultParameters()
	{
		return [
			'name' => 'sales_notes',
			'max_length' => 50,
		];
	}

    public function modifyTagValues(array $tagValues, array $context)
    {
        if (empty($context['SALES_NOTES'])) { return $tagValues; }

        if (empty($tagValues)) { $tagValues[] = []; }

        foreach ($tagValues as &$tagValue)
        {
            $tagValue['VALUE'] = $context['SALES_NOTES'];
        }
        unset($tagValue);

        return $tagValues;
    }
}