<?php
namespace Yandex\Market\Api\Category\Parameters;

use Yandex\Market;

class Response extends Market\Api\Reference\Response
{
    public function getCategoryParameters()
    {
        return $this->requireCollection('result.parameters', Model\CategoryParameterCollection::class);
    }
}