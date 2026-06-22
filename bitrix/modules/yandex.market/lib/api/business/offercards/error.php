<?php
namespace Yandex\Market\Api\Business\OfferCards;

use Yandex\Market\Api\Reference\Model;

class Error extends Model
{
	public function getComment()
	{
		return (string)$this->getField('comment');
	}

	public function getMessage()
	{
		return (string)$this->getField('message');
	}
}