<?php
namespace Yandex\Market\Api\OAuth2\AccessToken;

use Bitrix\Main;
use Yandex\Market\Api\Reference\RequestSigned;
use Yandex\Market\Api\Reference\Validator;

class Request extends RequestSigned
{
	protected $verificationCode;

	public function getHost()
	{
		return 'oauth.yandex.ru';
	}

	public function getPath()
	{
		return '/token';
	}

	public function getQuery()
	{
		return [
			'grant_type' => 'authorization_code',
			'code' => $this->verificationCode
		];
	}

	public function getMethod()
	{
		return Main\Web\HttpClient::HTTP_POST;
	}

	public function setVerificationCode($code)
	{
		$this->verificationCode = $code;
	}

	protected function validationQueue()
	{
		$queue = parent::validationQueue();

		return $queue->add(new Validator\RequiredKeys([
			'token_type' => Response::TOKEN_TYPE,
			'access_token' => Validator\RequiredKeys::NON_EMPTY_STRING,
			'refresh_token' => Validator\RequiredKeys::NON_EMPTY_STRING,
			'expires_in' => Validator\RequiredKeys::POSITIVE_NUMBER,
		]));
	}

	public function buildResponse($data)
	{
		return new Response($data);
	}
}