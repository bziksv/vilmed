<?php
namespace Yandex\Market\Api\Categories\Tree;

use Yandex\Market;

class Response extends Market\Api\Reference\Response
{
    public function getRoot()
    {
        return $this->requireModel('result', Model\Category::class);
    }
}