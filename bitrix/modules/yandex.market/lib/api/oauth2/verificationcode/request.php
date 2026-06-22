<?php

namespace Yandex\Market\Api\OAuth2\VerificationCode;

use Bitrix\Main;
use Yandex\Market;

class Request extends Market\Api\Reference\RequestSigned
{
	use Market\Reference\Concerns\HasMessage;

	protected $state;
	protected $scope;
	protected $redirectUri;

	public function getHost()
	{
		return 'oauth.yandex.ru';
	}

	public function getPath()
	{
		return '/authorize';
	}

	public function getQuery()
	{
		$state = $this->getState();
		$queryData = [
			'response_type' => 'code',
			'client_id' => $this->getOauthClientId(),
			'scope' => implode(' ', $this->getScope()),
			'redirect_uri' => $this->getRedirectUri(),
		];

		if (!empty($state))
		{
			$queryData['state'] = http_build_query($state);
		}

		return $queryData;
	}

	public function setScope($scope)
	{
		$this->scope = (array)$scope;
	}

	public function getScope()
	{
		if ($this->scope === null)
		{
			throw new Main\SystemException('scope not set');
		}

		return $this->scope;
	}

	public function setState($state)
	{
		return $this->state = $state;
	}

	public function getState()
	{
		return $this->state !== null ? $this->state : $this->getDefaultState();
	}

	public function getDefaultState()
	{
		return [
			'sessid' => bitrix_sessid(),
		];
	}

	public function setRedirectUri($uri)
	{
		$this->redirectUri = (string)$uri;
	}

	public function getRedirectUri()
	{
		if ($this->redirectUri === null)
		{
			throw new Main\SystemException('redirectUri not set');
		}

		return $this->redirectUri;
	}

	public function buildResponse($data)
	{
		return new Response($data);
	}

	public function emulateResponse($data)
	{
		$this->validationQueue()->handle($data, new Main\Web\HttpClient());

		return $this->buildResponse($data);
	}

	protected function validationQueue()
	{
		$queue = new Market\Api\Reference\Validator\Queue();

		return $queue
			->add(new Market\Api\Reference\Validator\ResponseError())
			->add(new Market\Api\Reference\Validator\FormatArray())
			->add(new Market\Api\Reference\Validator\RequiredKeys([
				'code',
				'state' => function($raw) {
					parse_str($raw, $state);

					if (!isset($state['sessid']) || $state['sessid'] !== bitrix_sessid())
					{
						throw new Market\Api\Exception\ResponseError(self::getMessage('SESSION_EXPIRED'));
					}
				},
			]));
	}
}