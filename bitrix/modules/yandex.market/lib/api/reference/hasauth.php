<?php
namespace Yandex\Market\Api\Reference;

interface HasAuth
{
	/** @return Auth */
	public function getApiAuth();
}