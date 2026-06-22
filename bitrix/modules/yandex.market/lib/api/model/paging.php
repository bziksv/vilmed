<?php
namespace Yandex\Market\Api\Model;

use Yandex\Market;

class Paging extends Market\Api\Reference\Model
{
	public function hasNext()
	{
		return ((string)$this->getField('nextPageToken') !== '');
	}

	public function getNextPageToken()
	{
		$token = (string)$this->requireField('nextPageToken');

        Market\Reference\Assert::nonEmptyString($token, 'nextPageToken');

        return $token;
    }
}