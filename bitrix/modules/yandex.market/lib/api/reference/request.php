<?php
namespace Yandex\Market\Api\Reference;

use Bitrix\Main;
use Yandex\Market\Error;
use Yandex\Market\Psr;
use Yandex\Market\Api;
use Yandex\Market\Logger\Trading\Audit;

/**
 * @template T
 */
abstract class Request implements Psr\Log\LoggerAwareInterface
{
	const DATA_TYPE_JSON = 'json';
	const DATA_TYPE_HTTP = 'http';

	protected $query = [];
    protected $urlQuery = [];
	/** @var Psr\Log\LoggerInterface */
	protected $logger;

	public function __construct(Psr\Log\LoggerInterface $logger = null)
	{
		$this->setLogger($logger);
	}

	public function getUrl()
	{
		return $this->getProtocol() . '://' . $this->getHost() . $this->getPath();
	}

	public function getFullUrl()
	{
		$url = $this->getUrl();

		if ($this->getMethod() === Main\Web\HttpClient::HTTP_GET)
		{
			$query = $this->getQuery();
			$url = $this->appendUrlQuery($url, $query);
		}

        if (!empty($this->urlQuery))
        {
            $url = $this->appendUrlQuery($url, $this->urlQuery);
        }

		return $url;
	}

	protected function appendUrlQuery($url, $query)
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

	public function getProtocol()
	{
		return 'https';
	}

	abstract public function getHost();

	abstract public function getPath();

	public function getQuery()
	{
		return $this->query;
	}

	public function getUrlQuery()
	{
		return $this->urlQuery;
	}

	public function getQueryFormat()
	{
		return static::DATA_TYPE_HTTP;
	}

	public function getMethod()
	{
		return Main\Web\HttpClient::HTTP_GET;
	}

	public function setLogger(Psr\Log\LoggerInterface $logger = null)
	{
		$this->logger = $logger !== null ? $logger : new Psr\Log\NullLogger();
	}

	/** @return T */
	public function execute()
	{
		list($data, $httpStatus) = (new Transport\Queue())
             ->add($this->createCache())
             ->add($this->createLocker())
             ->add(new Transport\Fetcher($this, $this->logger))
             ->handle($this->buildClient(), $this);

		$this->validationQueue()->handle($data, $httpStatus);

		return $this->buildResponse($data);
	}

	/** @use Request::execute() */
	public function send()
	{
		$result = new RequestResult();

		try
		{
			$result->setResponse($this->execute());
		}
		catch (Api\Exception\HttpException $exception)
		{
			$this->logger->debug('HTTP ' . $exception->getHttpCode(), [
				'AUDIT' => Audit::OUTGOING_RESPONSE,
				'URL' => $this->getUrl(),
			]);

			$result->addError(new Error\Base($exception->getMessage(), $exception->getErrorCode()));
		}
		catch (Api\Exception\TransportException $exception)
		{
			$result->addError(new Error\Base($exception->getMessage(), $exception->getErrorCode()));
		}

		return $result;
	}

	/**
	 * @param $data
	 *
	 * @return T
	 */
	public function buildResponse($data)
	{
		return new Response($data);
	}

	protected function buildClient()
	{
		$result = new Internals\HttpClient([
			'version' => '1.1',
			'socketTimeout' => 30,
			'streamTimeout' => 30,
			'redirect' => true,
			'redirectMax' => 5,
		]);

		list($markerName, $markerValue) = Api\Marker::getHeader();

		$result->setHeader($markerName, $markerValue);

		if ($this->getQueryFormat() === static::DATA_TYPE_JSON)
		{
			$result->setHeader('Content-Type', 'application/json');
		}

		return $result;
	}

	protected function createCache()
	{
		return null;
	}

	protected function createLocker()
	{
		return null;
	}

	protected function validationQueue()
	{
		return (new Api\Reference\Validator\Queue())
			->add(new Api\Reference\Validator\ResponseError())
			->add(new Api\Reference\Validator\HttpError())
			->add(new Api\Reference\Validator\FormatArray());
	}
}
