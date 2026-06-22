<?php
namespace Yandex\Market\Trading\Entity\Sale\Internals;

use Bitrix\Main;
use Bitrix\Sale;
use Yandex\Market\Trading\Entity as TradingEntity;

if (!Main\Loader::includeModule('sale') || !class_exists(Sale\TradingPlatform\Platform::class)) { return; }

class Platform extends Sale\TradingPlatform\Platform
{
	protected static $instanceCode;
	protected static $ourCache = [];

	public static function isOur($id)
	{
		$id = (int)$id;

		if ($id <= 0) { return false; }
		if (isset(static::$ourCache[$id])) { return static::$ourCache[$id]; }

		$result = false;

		$query = Sale\TradingPlatformTable::getList([
			'select' => [ 'CODE' ],
			'filter' => [ '=ID' => $id ],
		]);

		if ($row = $query->fetch())
		{
			$result = (mb_strpos((string)$row['CODE'], TradingEntity\Sale\Platform::PLATFORM_PREFIX) === 0);
		}

		static::$ourCache[$id] = $result;

		return $result;
	}

	/**
	 * @deprecated
	 * @param string|null $code
	 *
	 * @return Sale\TradingPlatform\Platform|null
	 */
	public static function getInstance($code = null)
	{
		if ($code !== null)
		{
			return static::getInstanceCompatible($code);
		}

		if (static::$instanceCode === null)
		{
			static::$instanceCode = static::resolveInstanceCode();
		}

		return static::$instanceCode !== false
			? static::getInstanceCompatible(static::$instanceCode)
			: null;
	}

	/** @noinspection PhpUndefinedMethodInspection */
	protected static function getInstanceCompatible($code)
	{
		return \method_exists(static::class, 'getInstanceByCode')
			? static::getInstanceByCode($code)
			: parent::getInstance($code); // old bitrix behavior
	}

	protected static function resolveInstanceCode()
	{
		$result = false;

		$query = Sale\TradingPlatformTable::getList([
			'select' => [
				'CODE',
			],
			'filter' => [
				'=CLASS' => '\\' . static::class,
			],
			'order' => [
				'ACTIVE' => 'desc',
				'ID' => 'asc',
			],
			'limit' => 1,
		]);

		if ($row = $query->fetch())
		{
			$result = $row['CODE'];
		}

		return $result;
	}

	public function installExtended(array $data = [])
	{
		$fields = $this->prepareRecordData($data);

		return $this->addPlatformRecord($fields);
	}

	public function install()
	{
		return $this->installExtended()->getId();
	}

	public function updateExtended(array $data)
	{
		$fields = $this->prepareRecordData($data);

		return $this->updatePlatformRecord($fields);
	}

	public function migrate($id, array $data)
	{
		$this->id = $id;
		$this->isInstalled = true;

		return $this->updatePlatformRecord($data + [
			'CODE' => $this->getCode(),
		]);
	}

	protected function addPlatformRecord(array $fields = [])
	{
		$defaults = [
			'CODE' => $this->getCode(),
			'CLASS' => '\\' . static::class,
			'ACTIVE' => 'N',
			'SETTINGS' => '',
		];
		$fields = $defaults + $fields;

		$addResult = Sale\TradingPlatformTable::add($fields);

		if ($addResult->isSuccess())
		{
			$this->id = $addResult->getId();
			$this->isInstalled = true;
		}

		return $addResult;
	}

	protected function updatePlatformRecord(array $fields)
	{
		return Sale\TradingPlatformTable::update($this->id, $fields);
	}

	protected function prepareRecordData(array $data)
	{
		return array_intersect_key($data, [
			'NAME' => true,
			'DESCRIPTION' => true,
		]);
	}
}