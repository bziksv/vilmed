<?php

namespace Yandex\Market\Api\Model;

use Bitrix\Main;
use Yandex\Market;

class Outlet extends Market\Api\Reference\Model
{
	public function getName()
	{
		return (string)$this->getField('name');
	}

	public function getShopOutletCode()
	{
		return (string)$this->getField('shopOutletCode');
	}

	public function getEmails()
	{
		return (array)$this->getField('emails');
	}

	public function getPhones()
	{
		return (array)$this->getField('phones');
	}

	public function getAddress()
	{
		return $this->getModel('address', Outlet\Address::class);
	}

	public function getCoords()
	{
		return $this->getModel('coordsModel', Outlet\Coords::class);
	}

	public function hasField($name)
	{
		if ($name === 'coordsModel')
		{
			return $this->hasField('coords');
		}

		return parent::hasField($name);
	}

	public function getField($name)
	{
		if ($name === 'coordsModel')
		{
			$coords = $this->getField('coords');
			$coords = explode(',', $coords);

			if (count($coords) !== 2) { return null; }

			$coords = array_map('trim', $coords);

			return [
				'lon' => $coords[0],
				'lat' => $coords[1],
			];
		}

		return parent::getField($name);
	}
}