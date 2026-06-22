<?php
namespace Yandex\Market\Catalog\Run\Steps\Transport;

use Bitrix\Main;
use Yandex\Market\Api\Exception\ClientException;
use Yandex\Market\Api\Exception\HttpException;
use Yandex\Market\Api\Exception\MethodFailureException;
use Yandex\Market\Api\Exception\ServerErrorException;
use Yandex\Market\Catalog\Glossary;
use Yandex\Market\Data\Run\Waterfall;
use Yandex\Market\Data\Run\Offset;
use Yandex\Market\Data\Run\PauseException;
use Yandex\Market\Logger\Trading\Audit;
use Yandex\Market\Logger\Trading\Logger;
use Yandex\Market\Psr\Log\LogLevel;

class HttpCatcher
{
	const TIMEOUT_MINUTES_POW = 2;
	const TIMEOUT_MINUTES_LIMIT = 30;

	const SERVER_REPEAT = 100;
	const METHOD_REPEAT = 10;
	const CLIENT_REPEAT = 5;

	private $stepName;
	private $logger;

	public function __construct($stepName, Logger $logger = null)
	{
		$this->stepName = $stepName;
		$this->logger = $logger;
	}

	public function __invoke(Waterfall $waterfall, ...$arguments)
	{
		$offset = $this->argumentsOffset($arguments);
		$logger = $this->argumentsLogger($arguments);
		$repeat = (int)$offset->get('httpRepeat');

		try
		{
			$this->bootInternalLogger();

			$waterfall->next(...$arguments);

			$this->clearInternalLogger();
			$offset->override('httpRepeat', 0);
		}
		catch (ServerErrorException $exception)
		{
			if ($repeat >= self::SERVER_REPEAT)
			{
				$this->log(LogLevel::ERROR, $exception, $logger);
				throw $exception;
			}

			$this->log(LogLevel::WARNING, $exception, $logger);
			throw $this->makePause($exception, $repeat, $offset);
		}
		catch (MethodFailureException $exception)
		{
			if ($repeat >= self::METHOD_REPEAT)
			{
				$this->log(LogLevel::ERROR, $exception, $logger);
				throw $exception;
			}

			$this->log(LogLevel::WARNING, $exception, $logger);
			throw $this->makePause($exception, $repeat, $offset);
		}
		catch (ClientException $exception)
		{
			if ($repeat >= self::CLIENT_REPEAT)
			{
				$this->log(LogLevel::ERROR, $exception, $logger);
				throw $exception;
			}

			$this->log(LogLevel::WARNING, $exception, $logger);
			throw $this->makePause($exception, $repeat, $offset);
		}
	}

	private function argumentsOffset(array $arguments)
	{
		foreach ($arguments as $argument)
		{
			if ($argument instanceof Offset)
			{
				return $argument;
			}
		}

		throw new Main\ArgumentException('missing offset argument');
	}

	private function argumentsLogger(array $arguments)
	{
		foreach ($arguments as $argument)
		{
			if ($argument instanceof Logger)
			{
				return $argument;
			}
		}

		return null;
	}

	private function bootInternalLogger()
	{
		if ($this->logger === null) { return; }

		$this->logger->setContext('AUDIT', Audit::CATALOG_OFFER);
		$this->logger->allowCheckExists([
			'=LEVEL' => [ LogLevel::ERROR, LogLevel::WARNING ],
		]);
		$this->logger->allowRelease();
		$this->logger->registerElement(Glossary::ENTITY_SKU, 0);
	}

	private function clearInternalLogger()
	{
		if ($this->logger === null) { return; }

		$this->logger->flush();
	}

	private function log($level, HttpException $exception, Logger $logger = null)
	{
		if ($logger === null) { $logger = $this->logger; }

		$logger->log($level, $exception, $logger->firstElement() ?: []);
		$logger->flush();
	}

	private function makePause(HttpException $exception, $repeat, Offset $offset)
	{
		$timeout = min(self::TIMEOUT_MINUTES_POW ** $repeat, self::TIMEOUT_MINUTES_LIMIT) * 60;

		$offset->override('httpRepeat', ++$repeat);

		return new PauseException(
			$this->stepName,
			(string)$offset,
			$timeout,
			$exception->getMessage(),
			$exception->getCode(),
			$exception
		);
	}
}