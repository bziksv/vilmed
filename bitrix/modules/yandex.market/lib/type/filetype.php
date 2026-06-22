<?php
namespace Yandex\Market\Type;

class FileType extends UrlType
{
    public function type()
    {
        return Manager::TYPE_FILE;
    }
}