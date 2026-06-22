<?php
namespace Yandex\Market\Api\Reference;

/** @use RequestTokenized */
abstract class RequestClientTokenized extends RequestTokenized
{
	/** @deprecated */
	public function setOauthClientId($oauthClientId) {}

	/** @deprecated */
	public function getOauthClientId() {}
}