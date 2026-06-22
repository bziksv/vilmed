<?php
namespace Yandex\Market\Psr\Log;

class NullLogger extends AbstractLogger
{
	public function log($level, $message, array $context = array())
	{
		// nothing
	}
}