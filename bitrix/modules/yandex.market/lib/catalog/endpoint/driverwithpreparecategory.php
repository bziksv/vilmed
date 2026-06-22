<?php
namespace Yandex\Market\Catalog\Endpoint;

use Yandex\Market\Api\Reference\Auth;
use Yandex\Market\Psr\Log\LoggerInterface;
use Yandex\Market\Result;

interface DriverWithPrepareCategory extends Driver
{
    /** @return Result\Base[] */
    public function prepareCategory($categoryId, array $bag, Auth $auth, LoggerInterface $logger);
}