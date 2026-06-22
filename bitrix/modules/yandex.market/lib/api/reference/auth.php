<?php
namespace Yandex\Market\Api\Reference;

interface Auth
{
	/** @return array{string, string} */
	public function getHeader();
}