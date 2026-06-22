<?php
namespace Yandex\Market\Trading\Service\Marketplace\Api\VerifyEac;

use Bitrix\Main;
use Yandex\Market;

class Request extends Market\Api\Partner\Reference\Request
{
	use Market\Reference\Concerns\HasMessage;

	protected $orderId;
	protected $code;

	public function getPath()
	{
		return sprintf(
			'/v2/campaigns/%s/orders/%s/verifyEac.json',
			$this->getCampaignId(),
			$this->getOrderId()
		);
	}

	public function getQuery()
	{
		return [
			'code' => $this->getCode(),
		];
	}

	public function getMethod()
	{
		return Main\Web\HttpClient::HTTP_PUT;
	}

	public function getQueryFormat()
	{
		return static::DATA_TYPE_JSON;
	}

	public function buildResponse($data)
	{
		return new Response($data);
	}

	public function setOrderId($orderId)
	{
		$this->orderId = $orderId;
	}

	public function getOrderId()
	{
		Market\Reference\Assert::notNull($this->orderId, 'orderId');

		return (string)$this->orderId;
	}

	public function setCode($code)
	{
		$this->code = $code;
	}

	public function getCode()
	{
		Market\Reference\Assert::notNull($this->code, 'code');

		return $this->code;
	}

	protected function validationQueue()
	{
		$queue = parent::validationQueue();
		$queue->add(new Market\Api\Reference\Validator\RequiredKeys([
			'result.verificationResult' => function($verificationResult, $response) {
				if ($verificationResult === Response::VERIFICATION_RESULT_ACCEPTED) { return; }

				if ($verificationResult === Response::VERIFICATION_RESULT_REJECTED)
				{
					throw new Market\Api\Exception\ResponseError(self::getMessage('VERIFICATION_REJECTED', [
						'#LEFT#' => (int)$response['result']['attemptsLeft'],
					]));
				}

				if ($verificationResult === Response::VERIFICATION_RESULT_NEED_UPDATE)
				{
					throw new Market\Api\Exception\ResponseError(self::getMessage('VERIFICATION_NEED_UPDATE'));
				}

				throw new Market\Api\Exception\ResponseError(self::getMessage('VERIFICATION_UNKNOWN', [
					'#RESULT#' => $verificationResult,
				]));
			}
		]));

		return $queue;
	}
}