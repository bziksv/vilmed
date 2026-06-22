<?php
namespace Yandex\Market\Logger;

use Yandex\Market\Config;

class Logger extends Reference\Logger
{
	protected $canTouchRows = false;
	private $setupId;

	public function __construct($setupId = null)
	{
		parent::__construct();
		$this->setupId = (int)$setupId;
		$this->level = Config::getOption('export_log_level', Level::WARNING);
	}

	public function getDataClass()
	{
		return Table::class;
	}

	protected function getRowDefaults()
	{
		return [
			'ENTITY_PARENT' => $this->setupId,
		];
	}

	protected function getContextFields()
	{
		return [
			'ENTITY_PARENT',
			'ENTITY_TYPE',
			'ENTITY_ID',
			'ERROR_CODE',
		];
	}

	protected function existsCommonFilter(array $rows)
	{
		return [
			'=ENTITY_PARENT' => $this->setupId > 0
				? $this->setupId
				: array_values(array_column($rows, 'ENTITY_PARENT', 'ENTITY_PARENT')),
		];
	}
}
