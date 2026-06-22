<?php
namespace Yandex\Market\Export\Setup\Internals;

use Bitrix\Main;
use Yandex\Market;

class GroupTable extends Market\Reference\Storage\Table
{
	use Market\Reference\Concerns\HasMessage;

	private static function includeSelfMessages()
	{
		Main\Localization\Loc::loadMessages(__FILE__);
	}

	public static function getTableName()
	{
		return 'yamarket_export_setup_group';
	}

	public static function getMap()
	{
		return [
			new Main\Entity\IntegerField('ID', [
				'autocomplete' => true,
				'primary' => true,
			]),
			new Main\Entity\StringField('NAME', [
				'required' => true,
			]),
			new Main\Entity\IntegerField('PARENT_ID', [
				'default_value' => 0,
				'validation' => function() {
					return [
						function ($value, $primary) {
							$value = (int)$value;
							$primaryId = (int)static::getPrimaryId($primary);

							if ($primaryId <= 0) { return true; }

							if ($primaryId === $value)
							{
								return self::getMessage('PARENT_ID_VALIDATE_MATCH_SELF');
							}

							if (static::isGroupInside(static::getTree(), $primaryId, $value))
							{
								return self::getMessage('PARENT_ID_VALIDATE_INSIDE_CHILDREN');
							}

							return true;
						},
					];
				},
			]),
			new Main\Entity\ReferenceField('PARENT', static::class, [
				'=this.PARENT_ID' => 'ref.ID',
			]),
			new Main\Entity\ReferenceField('SETUP_LINK', GroupLinkTable::class, [
				'=this.ID' => 'ref.GROUP_ID',
			]),
			new Main\Entity\ReferenceField('SETUP', Market\Export\Setup\Table::class, [
				'=this.SETUP_LINK.SETUP_ID' => 'ref.ID',
			]),
		];
	}

	public static function getMapDescription()
	{
		self::includeSelfMessages();

		return parent::getMapDescription();
	}

	public static function migrate(Main\DB\Connection $connection)
	{
		$tableFields = $connection->getTableFields(self::getTableName());

		self::dropUiServiceColumn($connection, $tableFields);
	}

	private static function dropUiServiceColumn(Main\DB\Connection $connection, array $tableFields)
	{
		if (!isset($tableFields['UI_SERVICE'])) { return; }

		$connection->dropColumn(self::getTableName(), 'UI_SERVICE');
	}

	protected static function isGroupInside($tree, $parentId, $searchId)
	{
		$parentDepth = null;
		$result = false;

		foreach ($tree as $group)
		{
			$groupId = (int)$group['ID'];

			if ($groupId === $parentId)
			{
				$parentDepth = $group['DEPTH_LEVEL'];
			}
			else if ($parentDepth !== null && $group['DEPTH_LEVEL'] <= $parentDepth)
			{
				$parentDepth = null;
				break;
			}

			if ($searchId === $groupId)
			{
				$result = ($parentDepth !== null);
				break;
			}
		}

		return $result;
	}

	public static function getTree(array $parameters = [])
	{
		$rows = static::getList($parameters)->fetchAll();

		return static::sortTreeRows($rows);
	}

	protected static function sortTreeRows($rows, $iterationGroup = 0, $depthLevel = 1)
	{
		$result = [];

		foreach ($rows as $row)
		{
			$parentId = (int)$row['PARENT_ID'];

			if ($parentId === $iterationGroup)
			{
				// add self

				$result[] = $row + [ 'DEPTH_LEVEL' => $depthLevel ];

				// insert children

				$children = static::sortTreeRows($rows, (int)$row['ID'], $depthLevel + 1);

				if (!empty($children))
				{
					array_push($result, ...$children);
				}
			}
		}

		return $result;
	}

	protected static function deleteReference($primary)
	{
		parent::deleteReference($primary);
		static::deleteReferenceGroupLink($primary);
	}

	protected static function deleteReferenceGroupLink($primary)
	{
		GroupLinkTable::deleteBatch([
			'filter' => [ '=GROUP_ID' => $primary ],
		]);
	}
}
