<?php
namespace Yandex\Market\Api\User\Info;

use Yandex\Market\Api;

class Response extends Api\Reference\Response
{
	public function getId()
	{
		return (string)$this->requireField('id');
	}

	public function getLogin()
	{
		return (string)$this->getField('login');
	}
}