<?php

namespace Yandex\Market\Data\Holiday;

use Yandex\Market\Reference\Concerns;

class Production extends National
{
	use Concerns\HasMessage;

	public function title()
	{
		return self::getMessage('TITLE');
	}

	public function holidays()
	{
		return array_unique(array_merge(parent::holidays(), [
			'01.05',
			'02.05',
			'03.05',
			'04.05',
			'08.05',
			'09.05',
			'10.05',
			'11.05',
			'12.06',
			'13.06',
			'02.11',
			'03.11',
			'04.11',
			'30.12',
			'31.12',
		]));
	}

	public function workdays()
	{
		return [
			'07.03',
			'30.04',
			'11.06',
			'01.11',
			'28.12',
		];
	}
}