<?php
namespace Yandex\Market\Trading\Service\Marketplace\Api\VerifyEac;

use Yandex\Market;

class Response extends Market\Api\Reference\ResponseWithResult
{
	const VERIFICATION_RESULT_ACCEPTED = 'ACCEPTED';
	const VERIFICATION_RESULT_REJECTED = 'REJECTED';
	const VERIFICATION_RESULT_NEED_UPDATE = 'NEED_UPDATE';
}