<?php
namespace Yandex\Market\Reference\Storage\Batch;

use Bitrix\Main;

class InsertBatch
{
	/** @var class-string<Main\Entity\DataManager> */
	private $dataClass;

	public function __construct($dataClass)
	{
		$this->dataClass = $dataClass;
	}

	/**
	 * @param array[] $dataList
	 * @param array|bool $updateOnDuplicate
	 */
	public function run(array $dataList, $updateOnDuplicate = false)
	{
		if (empty($dataList)) { return; }

		$dataClass = $this->dataClass;
		$entity = $dataClass::getEntity();
		$fields = $entity->getFields();
		$connection = $entity->getConnection();
		$helper = $connection->getSqlHelper();
		$tableName = $entity->getDBTableName();
		$sqlFieldPart = '';
		$sqlValuePart = '';
		$issetFieldsPart = false;
		$usedFields = [];

		foreach ($dataList as $data)
		{
			foreach ($data as $fieldName => $value)
			{
				if (!isset($fields[$fieldName]))
				{
					throw new Main\ArgumentException(sprintf(
						'%s Entity has no `%s` field.', $entity->getName(), $fieldName
					));
				}

				$field = $fields[$fieldName];

				$data[$fieldName] = $field->modifyValueBeforeSave($value, $data);

				if (!$issetFieldsPart)
				{
					$usedFields[] = $fieldName;
				}
			}

			$insert = $helper->prepareInsert($tableName, $data);

			if (!$issetFieldsPart)
			{
				$issetFieldsPart = true;
				$sqlFieldPart = $insert[0];
			}

			$sqlValuePart .= ($sqlValuePart !== '' ? ',' . PHP_EOL : '') . '(' . $insert[1] . ')';
		}

		if (!$issetFieldsPart) { return; }

		$insertRule = 'INSERT INTO';
		$duplicateSql = '';

		if ($updateOnDuplicate !== false)
		{
			if (is_array($updateOnDuplicate))
			{
				$duplicateFields = $updateOnDuplicate;
			}
			else
			{
				$tableFields = $connection->getTableFields($tableName);
				$primaryArray = $entity->getPrimaryArray();
				$primaryMap = array_flip($primaryArray);
				$duplicateFields = [];

				foreach ($usedFields as $fieldName)
				{
					if (!isset($primaryMap[$fieldName]) && isset($tableFields[$fieldName]))
					{
						$duplicateFields[] = $fieldName;
					}
				}
			}

			foreach ($duplicateFields as $fieldName)
			{
				$fieldNameQuoted = $helper->quote($fieldName);

				if ($duplicateSql !== '')
				{
					$duplicateSql .= ', ';
				}

				$duplicateSql .= $fieldNameQuoted . ' = VALUES(' . $fieldNameQuoted . ')';
			}

			if ($duplicateSql === '')
			{
				$insertRule = 'INSERT IGNORE INTO';
			}
			else
			{
				$duplicateSql =
					PHP_EOL . 'ON DUPLICATE KEY UPDATE'
					. PHP_EOL . $duplicateSql;
			}
		}

		$sql =
			$insertRule . ' ' . $tableName . '(' . $sqlFieldPart . ') ' .
			'VALUES ' . $sqlValuePart
			. $duplicateSql;

		$connection->queryExecute($sql);
	}
}