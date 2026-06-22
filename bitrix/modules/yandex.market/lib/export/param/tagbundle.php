<?php
namespace Yandex\Market\Export\Param;

use Yandex\Market\Export\Xml\Tag;

class TagBundle
{
	private $tag;
	private $map;

	public function __construct(Tag\Base $tag, TagMap $map)
	{
		$this->tag = $tag;
		$this->map = $map;
	}

	public function getTag()
	{
		return $this->tag;
	}

	public function getMap()
	{
		return $this->map;
	}

	public function extract(array $sourceValues, array $context = [])
	{
		return $this->map->extract($sourceValues, $this->tag, $context);
	}
}