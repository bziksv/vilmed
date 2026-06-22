<?php
namespace Yandex\Market\Trading\Settings\Options;

class SkuMap
{
	/** @var array{IBLOCK: int, FIELD: string}[] */
	private $fieldMap;
	/** @var string|null */
	private $prefix;

	public function __construct($fieldMap, $prefix = null)
	{
		$this->fieldMap = $this->castFieldMap($fieldMap);
		$this->prefix = $this->castPrefix($prefix);
	}

	public function getIblockIds()
	{
		return array_values(array_column($this->fieldMap, 'IBLOCK', 'IBLOCK'));
	}

	public function getFieldMap()
	{
		return $this->fieldMap;
	}

	public function getPrefix()
	{
		return $this->prefix;
	}

	private function castFieldMap($fieldMap)
	{
		if (!is_array($fieldMap)) { return []; }

		$result = [];

		foreach ($fieldMap as $iblockMap)
		{
			if (empty($iblockMap['IBLOCK']) || empty($iblockMap['FIELD'])) { continue; }

			$result[] = [
				'IBLOCK' => (int)$iblockMap['IBLOCK'],
				'FIELD' => (string)$iblockMap['FIELD'],
			];
		}

		return $result;
	}

	private function castPrefix($prefix)
	{
		if ($prefix === null) { return null; }

		$prefix = trim($prefix);

		if ($prefix === '') { return null; }

		return $prefix;
	}
}