<?php

namespace Titlo\Relevance;

/**
 * «Оставить как есть» для групп дублей (товары E / категории S): группа скрывается из открытых.
 * Хранится набор ID; группа считается отложенной, если все её ID входят в один сохранённый набор —
 * появится новый участник, и группа снова станет открытой.
 */
class DupIgnore
{
	public const STATUS = 'ignored';

	/** @var bool */
	protected static $schemaReady = false;

	public static function ensureSchema(): void
	{
		if (self::$schemaReady) {
			return;
		}
		global $DB;
		$DB->Query("
			CREATE TABLE IF NOT EXISTS titlo_relevance_dup_ignore (
				ID int(11) NOT NULL AUTO_INCREMENT,
				ENTITY_TYPE char(1) NOT NULL DEFAULT 'E',
				IDS text NOT NULL,
				USER_ID int(11) NOT NULL DEFAULT 0,
				CREATED_AT datetime DEFAULT NULL,
				PRIMARY KEY (ID),
				KEY ix_type (ENTITY_TYPE)
			)
		");
		self::$schemaReady = true;
	}

	/**
	 * @return array<int, array<int, true>> recordId => set of ids
	 */
	public static function sets(string $entityType): array
	{
		self::ensureSchema();
		global $DB;
		$out = [];
		$res = $DB->Query("SELECT ID, IDS FROM titlo_relevance_dup_ignore WHERE ENTITY_TYPE = '" . $DB->ForSql(self::type($entityType)) . "'");
		while ($row = $res->Fetch()) {
			$set = [];
			foreach (explode(',', (string) $row['IDS']) as $id) {
				if ((int) $id > 0) {
					$set[(int) $id] = true;
				}
			}
			$out[(int) $row['ID']] = $set;
		}
		return $out;
	}

	/**
	 * @param array<int, array<int, true>> $sets
	 * @param int[] $ids
	 */
	public static function covers(array $sets, array $ids): bool
	{
		if ($ids === []) {
			return false;
		}
		foreach ($sets as $set) {
			$all = true;
			foreach ($ids as $id) {
				if (!isset($set[(int) $id])) {
					$all = false;
					break;
				}
			}
			if ($all) {
				return true;
			}
		}
		return false;
	}

	/**
	 * @param int[] $ids
	 */
	public static function add(string $entityType, array $ids): bool
	{
		$ids = self::cleanIds($ids);
		if (count($ids) < 2) {
			return false;
		}
		self::ensureSchema();
		global $DB, $USER;
		$userId = is_object($USER) ? (int) $USER->GetID() : 0;
		$DB->Query("
			INSERT INTO titlo_relevance_dup_ignore (ENTITY_TYPE, IDS, USER_ID, CREATED_AT)
			VALUES ('" . $DB->ForSql(self::type($entityType)) . "', '" . implode(',', $ids) . "', {$userId}, NOW())
		");
		AuditLog::write('dup_ignore', ['entity' => self::type($entityType), 'ids' => $ids]);
		return true;
	}

	/**
	 * Вернуть в работу: удалить все наборы, покрывающие эти ID.
	 *
	 * @param int[] $ids
	 */
	public static function remove(string $entityType, array $ids): int
	{
		$ids = self::cleanIds($ids);
		if ($ids === []) {
			return 0;
		}
		global $DB;
		$n = 0;
		foreach (self::sets($entityType) as $recId => $set) {
			if (self::covers([$set], $ids)) {
				$DB->Query('DELETE FROM titlo_relevance_dup_ignore WHERE ID = ' . (int) $recId);
				$n++;
			}
		}
		AuditLog::write('dup_unignore', ['entity' => self::type($entityType), 'ids' => $ids, 'removed' => $n]);
		return $n;
	}

	protected static function type(string $entityType): string
	{
		return $entityType === 'S' ? 'S' : 'E';
	}

	/**
	 * @param mixed[] $ids
	 * @return int[]
	 */
	protected static function cleanIds(array $ids): array
	{
		$ids = array_values(array_unique(array_filter(array_map('intval', $ids), static function ($x) {
			return $x > 0;
		})));
		sort($ids);
		return array_slice($ids, 0, 500);
	}
}
