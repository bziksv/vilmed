<?php
namespace Yandex\Market\Api\Reference\Transport;

use Bitrix\Main\Web\HttpClient;
use Yandex\Market\Api\Reference\Request;

interface Middleware
{
	public function handle(HttpClient $client, Request $request, Queue $queue);
}