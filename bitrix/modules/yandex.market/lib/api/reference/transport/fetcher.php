<?php
namespace Yandex\Market\Api\Reference\Transport;

use Bitrix\Main;
use Bitrix\Main\Web\HttpClient;
use Bitrix\Main\Web\Json;
use Yandex\Market\Api\Exception\ClientException;
use Yandex\Market\Api\Exception\ParseException;
use Yandex\Market\Api\Reference\Request;
use Yandex\Market\Api\Reference\ResponseWithResult;
use Yandex\Market\Psr\Log\LoggerInterface;
use Yandex\Market\Logger\Trading\Audit;

class Fetcher implements Middleware
{
	private $request;
	private $logger;

	public function __construct(Request $request, LoggerInterface $logger)
	{
		$this->request = $request;
		$this->logger = $logger;
	}

	public function handle(HttpClient $client, Request $request, Queue $queue)
	{
		$method = $this->request->getMethod();
		$url = $this->request->getUrl();
		$urlQuery = $this->request->getUrlQuery();
		$query = $this->request->getQuery();
		$postData = null;

		if ($method === HttpClient::HTTP_GET)
		{
			$fullUrl = $this->appendUrlQuery($url, $query);
		}
		else
		{
			$fullUrl = $url;
			$postData = $this->formatQueryData($query);
		}

		if (!empty($urlQuery))
		{
			$fullUrl = $this->appendUrlQuery($fullUrl, $urlQuery);
		}

		$this->logger->debug($query, [
			'AUDIT' => Audit::OUTGOING_REQUEST,
			'URL' => $url,
		]);

		if (!$client->query($method, $fullUrl, $postData))
		{
			$errors = $client->getError();

			throw new ClientException(
				sprintf(
					'%s %s %s',
					implode(', ', $errors) ?: 'Unknown http client error',
					$this->request->getMethod(),
					$this->request->getUrl()
				),
				(int)$client->getStatus(),
				key($errors)
			);
		}

		$response = $this->parseHttpResponse($client->getResult(), $client->getContentType());

		$this->logger->debug($response, [
			'AUDIT' => Audit::OUTGOING_RESPONSE,
			'URL' => $this->request->getUrl(),
		]);

		return [$response, $client->getStatus()];
	}

	private function appendUrlQuery($url, $query)
	{
		$result = $url;

		if (!empty($query))
		{
			$result .=
				(mb_strpos($result, '?') === false ? '?' : '&')
				. http_build_query($query, '', '&');
		}

		return $result;
	}

	private function formatQueryData($data)
	{
		if ($this->request->getQueryFormat() === Request::DATA_TYPE_JSON)
		{
			if (is_array($data) && empty($data)) { return null; }

			return Json::encode($data, JSON_UNESCAPED_UNICODE);
		}

		return $data;
	}

	private function parseHttpResponse($httpResponse, $contentType = 'application/json')
	{
		try
		{
			$contentType = mb_strtolower($contentType);
			$httpResponse = (string)$httpResponse;

			if ($contentType === '' && $httpResponse === '')
			{
				return [];
			}

			if (mb_strpos($contentType, 'application/json') !== false)
			{
				if ($httpResponse === '') { return []; }

				return Json::decode($httpResponse);
			}

			if (mb_strpos($contentType, 'application/pdf') !== false)
			{
				return [
					'status' => ResponseWithResult::STATUS_OK,
					'type' => $contentType,
					'contents' => $httpResponse,
				];
			}

			return $httpResponse;
		}
		catch (Main\ArgumentException $exception)
		{
			throw new ParseException(
				sprintf('Cant parse http response %s %s', $this->request->getMethod(), $this->request->getUrl()),
				'JSON_ERROR',
				$exception
			);
		}
	}
}