<?php
namespace Yandex\Market\Trading\Service\Reference\Options;

abstract class Fieldset extends Skeleton
{
	public function getFieldDescription()
	{
		return [
			'MULTIPLE' => 'N',
			'FIELDS' => $this->getFields(),
		];
	}

	public function isMatchPlacement($placement)
	{
		return true;
	}
}