<?php
namespace Yandex\Market\Catalog\Run\Steps\Collector;

use Yandex\Market\Export;
use Yandex\Market\Catalog;
use Yandex\Market\Data;
use Yandex\Market\Result;
use Yandex\Market\Logger\Trading\Logger;

class State
{
	// -- Processor

    /** @var Catalog\Setup\Model */
    public $catalog;
	/** @var string */
	public $runAction;
	/** @var array */
	public $changes;
	/** @var Data\Type\CanonicalDateTime */
	public $initTime;

	// -- Models
    /** @var Catalog\Product\Model */
    public $catalogProduct;

	// -- Query

	/** @var Export\Param\TagMapGroup */
	public $sourceMapGroup;
	/** @var array */
	public $sourceSelect;
	/** @var array */
	public $querySelect;
	/** @var array */
	public $queryFilter;

	// -- Elements

	/** @var Logger */
	public $logger;
	/** @var array */
	public $elements;
	/** @var array */
	public $sourceValues;
	/** @var array */
	public $offers;
    /** @var Result\XmlNode[] */
    public $tagNodes;
    /** @var array */
    public $tasks;
    /** @var array */
    public $hashChanged;

	// -- Common

	/** @var array */
	public $context;
}