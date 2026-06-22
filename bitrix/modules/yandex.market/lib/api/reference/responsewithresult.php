<?php
namespace Yandex\Market\Api\Reference;

class ResponseWithResult extends Response
{
	const STATUS_OK = 'OK';
	const STATUS_ERROR = 'ERROR';

	public function getStatus()
	{
		return (string)$this->getField('status');
	}
}