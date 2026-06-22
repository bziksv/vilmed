<?php
namespace Yandex\Market\Catalog\Endpoint;

use Yandex\Market\Api\Reference\Auth;
use Yandex\Market\Psr\Log\LoggerInterface;
use Yandex\Market\Result;

interface Driver
{
	/** @return string */
	public function type();

	/** @return int */
	public function campaignId();

	/** @return string */
	public function audit();

	/** @return int */
	public function priority($placementStatus, array $prepared, array $submitted = null);

	/** @return int */
	public function limit();

	/** @return array<string, Result\Base> */
	public function submit(array $payloadBag, Auth $auth, LoggerInterface $logger);
}