<?php
namespace Yandex\Market\Api\Business\OfferCards;

use Yandex\Market\Api\Reference\Model;
use Yandex\Market\Reference\Concerns;

class Recommendation extends Model
{
	use Concerns\HasMessage;

	public function getType()
	{
		return (string)$this->requireField('type');
	}

	public function typeComment()
	{
		return self::getMessage(mb_strtoupper($this->getType()), null, '');
	}

	public function getRemainingRatingPoints()
	{
		return (int)$this->getField('remainingRatingPoints');
	}
}