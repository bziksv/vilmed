<?php
namespace Yandex\Market\Export\Xml\Attribute;

use Yandex\Market;

class Id extends Base
{
	public function getDefaultParameters()
	{
		return [
			'name' => 'id',
			'primary' => true,
		];
	}

	public function getSourceRecommendation(array $context = [])
	{
		$result = [];

		if ($context['HAS_OFFER'])
		{
			$result[] = [
				'TYPE' => Market\Export\Entity\Manager::TYPE_IBLOCK_OFFER_FIELD,
				'FIELD' => 'ID'
			];
		}

		$result[] = [
			'TYPE' => Market\Export\Entity\Manager::TYPE_IBLOCK_ELEMENT_FIELD,
			'FIELD' => 'ID'
		];

		return $result;
	}

	public function getDefinedSource(array $context = [])
	{
		if ($context['HAS_OFFER'])
		{
			return [
				'TYPE' => Market\Export\Entity\Manager::TYPE_IBLOCK_OFFER_FIELD,
				'FIELD' => 'ID'
			];
		}

		return [
			'TYPE' => Market\Export\Entity\Manager::TYPE_IBLOCK_ELEMENT_FIELD,
			'FIELD' => 'ID'
		];
	}
}