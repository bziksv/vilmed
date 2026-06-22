<?php
namespace Yandex\Market\Api\Reference\Transport;

use Bitrix\Main\Application;
use Bitrix\Main\Web\HttpClient;
use Yandex\Market\Api\Exception;
use Yandex\Market\Api\Reference\ApiKey;
use Yandex\Market\Api\Reference\OAuth;
use Yandex\Market\Api\Reference\Request;
use Yandex\Market\Trading;

class Cache implements Middleware
{
	/** @var int */
	private $ttl;
	/** @var array|null */
	private $significant;
	/** @var string */
	private $tableName;
	/** @var array|null */
    private $errorCodes;

	public function __construct($ttl = 86400, array $significant = null, $tableName = null)
	{
		$this->ttl($ttl);
        $this->significant($significant);
		$this->tableName($tableName);
	}

    public function ttl($ttl)
    {
        $this->ttl = (int)$ttl;
        return $this;
    }

    public function significant(array $significant = null)
    {
        $this->significant = $significant;
        return $this;
    }

    public function tableName($tableName = null)
    {
        $this->tableName = $tableName ?: Trading\Setup\Table::getTableName();
        return $this;
    }

    public function errorCodes(array $codes = null)
    {
        $this->errorCodes = $codes;
        return $this;
    }

	public function handle(HttpClient $client, Request $request, Queue $queue)
	{
        if ($this->ttl <= 0) { return $queue->next($client, $request); }

		$cache = Application::getInstance()->getManagedCache();
		$key = $this->key($request, $client);

		if ($cache->read($this->ttl, $key, $this->tableName))
		{
			$cached = $cache->get($key);

            if (isset($cached['errorCode']))
            {
                throw isset($cached['httpStatus'])
	                ? Exception\HttpExceptionFactory::make($cached['httpStatus'], $cached['errorMessage'], $cached['errorCode'])
	                : new Exception\TransportException($cached['errorMessage'], $cached['errorCode']);
            }

			if (!isset($cached['httpStatus'])) // old format
			{
				return [ $cached, 200 ];
			}

            if ($cached !== false)
            {
                return [ $cached['response'], $cached['httpStatus'] ];
            }
        }

        try
        {
            list($response, $httpStatus) = $queue->next($client, $request);

            $cache->setImmediate($key, [
				'response' => $response,
                'httpStatus' => $httpStatus,
            ]);
        }
        catch (Exception\TransportException $exception)
        {
            if ($this->errorCodes !== null && in_array($exception->getErrorCode(), $this->errorCodes, true))
            {
                $cache->setImmediate($key, [
                    'errorCode' => $exception->getErrorCode(),
                    'errorMessage' => $exception->getMessage(),
	                'httpStatus' => $exception instanceof Exception\HttpException ? $exception->getHttpCode() : null,
                ]);
            }

            throw $exception;
        }

		return [ $response, $httpStatus ];
	}

	private function key(Request $request, HttpClient $client)
	{
		$auth = $this->authHeader($client);
		$query = $request->getQuery();

        if (!is_array($query)) { $query = []; }

		if ($this->significant !== null) { $query = array_intersect_key($query, array_flip($this->significant)); }

		$query += [
			'method' => $request->getMethod(),
			'url' => $request->getUrl(),
		];

		if ($auth !== null)
		{
			$query['auth'] = $auth;
		}

		return 'API_' . md5(serialize($query));
	}

    private function authHeader(HttpClient $client)
    {
        if (is_callable([$client, 'getRequestHeaders']))
        {
            $headers = $client->getRequestHeaders();
        }
        else
        {
            $property = new \ReflectionProperty($client, 'requestHeaders');
            $property->setAccessible(true);

            $headers = $property->getValue($client);
        }

        foreach ([ OAuth::HEADER_NAME, ApiKey::HEADER_NAME ] as $name)
        {
            $value = $headers->get($name);

            if ($value === null) { continue; }

            return $value;
        }

        return null;
    }
}