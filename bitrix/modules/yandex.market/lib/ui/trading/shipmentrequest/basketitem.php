<?php
namespace Yandex\Market\Ui\Trading\ShipmentRequest;

use Yandex\Market;

class BasketItem extends Market\Api\Reference\Model
{
	/** @return string */
	public function getId()
	{
		return (string)$this->requireField('ID');
	}

	/** @return string[][] */
	public function getIdentifiers()
	{
		return (array)$this->getField('IDENTIFIERS.ITEMS');
	}

	public function getInitialIdentifiersCount()
	{
		return (int)$this->getField('IDENTIFIERS.INITIAL_COUNT');
	}

	/** @return string */
	public function getIdentifierType()
	{
		return $this->getField('IDENTIFIERS.TYPE');
	}

	/** @return float|null */
	public function getCount()
	{
		return Market\Data\Number::normalize($this->getField('COUNT'));
	}

	/** @return float|null */
	public function getInitialCount()
	{
		return Market\Data\Number::normalize($this->getField('INITIAL_COUNT'));
	}

	public function getPartCurrent()
	{
		return Market\Data\Number::normalize($this->getField('PARTIAL_CURRENT'));
	}

	public function getPartTotal()
	{
		return Market\Data\Number::normalize($this->getField('PARTIAL_TOTAL'));
	}

	/** @return bool */
	public function needDelete()
	{
		return $this->getField('DELETE') === 'Y';
	}

	public function getInitialBox()
	{
		return (int)$this->getField('INITIAL_BOX');
	}

	public function getDigital()
	{
		return $this->getModel('DIGITAL', Digital::class);
	}
}