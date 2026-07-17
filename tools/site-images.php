<?php
/**
 * Реестр картинок сайта (b_file) — таблица + Excel.
 * Доступ: localhost или админ Bitrix.
 *
 * /tools/site-images.php
 * /tools/site-images.php?export=excel
 * /tools/site-images.php?action=download&id=123
 */
define('NO_KEEP_STATISTIC', true);
define('NO_AGENT_CHECK', true);
define('DisableEventsCheck', true);

require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php';

class VilmedSiteImages
{
	public const IMAGE_EXT = 'jpe?g|png|gif|webp|svg|bmp|ico|tiff?';
	public const PAGE_SIZE_DEFAULT = 100;
	public const PAGE_SIZE_MAX = 500;
	public const EXPORT_MAX = 20000;
	/** Прямые ссылки всегда на боевой домен, чтобы их можно было копировать */
	public const PUBLIC_BASE = 'https://vilmed.ru';

	public static function isAllowed(): bool
	{
		global $USER;

		$addr = (string)($_SERVER['REMOTE_ADDR'] ?? '');
		if (in_array($addr, ['127.0.0.1', '::1'], true)) {
			return true;
		}

		return is_object($USER) && $USER->IsAuthorized() && $USER->IsAdmin();
	}

	public static function accessDeniedReason(): string
	{
		global $USER;
		if (!is_object($USER) || !$USER->IsAuthorized()) {
			return 'Нужна авторизация администратора (/bitrix/admin/).';
		}
		return 'Нужны права администратора.';
	}

	public static function absoluteUrl(string $path): string
	{
		if ($path === '' || $path === '/') {
			return '';
		}
		if (preg_match('#^https?://#i', $path)) {
			return $path;
		}
		return self::PUBLIC_BASE . $path;
	}

	public static function imageWhereSql(): string
	{
		$ext = self::IMAGE_EXT;
		return "(
			CONTENT_TYPE LIKE 'image/%'
			OR FILE_NAME REGEXP '\\\\.({$ext})$'
			OR ORIGINAL_NAME REGEXP '\\\\.({$ext})$'
		)";
	}

	/** @return array{total:int,rows:array<int,array>} */
	public static function fetch(array $opts): array
	{
		global $DB;

		$q = trim((string)($opts['q'] ?? ''));
		$module = trim((string)($opts['module'] ?? ''));
		$usage = trim((string)($opts['usage'] ?? ''));
		$limit = max(1, min(self::PAGE_SIZE_MAX, (int)($opts['limit'] ?? self::PAGE_SIZE_DEFAULT)));
		$offset = max(0, (int)($opts['offset'] ?? 0));
		$forExport = !empty($opts['export']);
		if ($forExport) {
			$limit = self::EXPORT_MAX;
			$offset = 0;
		}

		$where = ['(' . self::imageWhereSql() . ')'];
		if ($q !== '') {
			$safe = $DB->ForSql($q);
			$fileMatch = "(FILE_NAME LIKE '%{$safe}%' OR ORIGINAL_NAME LIKE '%{$safe}%' OR SUBDIR LIKE '%{$safe}%' OR CAST(ID AS CHAR) = '{$safe}')";
			$productFileIds = self::findFileIdsByProductQuery($q);
			if ($productFileIds) {
				$idList = implode(',', $productFileIds);
				$where[] = "({$fileMatch} OR ID IN ({$idList}))";
			} else {
				$where[] = $fileMatch;
			}
		}
		if ($module !== '') {
			$where[] = "MODULE_ID = '" . $DB->ForSql($module) . "'";
		}

		$whereSql = implode(' AND ', $where);

		$total = (int)$DB->Query("SELECT COUNT(*) CNT FROM b_file WHERE {$whereSql}")->Fetch()['CNT'];

		$res = $DB->Query("
			SELECT ID, TIMESTAMP_X, MODULE_ID, HEIGHT, WIDTH, FILE_SIZE,
				CONTENT_TYPE, SUBDIR, FILE_NAME, ORIGINAL_NAME, DESCRIPTION
			FROM b_file
			WHERE {$whereSql}
			ORDER BY ID DESC
			LIMIT {$limit} OFFSET {$offset}
		");

		$rows = [];
		$ids = [];
		while ($row = $res->Fetch()) {
			$id = (int)$row['ID'];
			$ids[] = $id;
			$rows[$id] = $row;
		}

		$usageMap = self::resolveUsage($ids);

		if ($usage !== '') {
			$filtered = [];
			foreach ($rows as $id => $row) {
				$u = $usageMap[$id] ?? [];
				$hay = mb_strtolower(implode(' ', $u));
				if (mb_strpos($hay, mb_strtolower($usage)) !== false) {
					$filtered[$id] = $row;
				}
			}
			$rows = $filtered;
			if ($usage !== '' && !$forExport) {
				$total = count($rows);
			}
		}

		$out = [];
		$docRoot = rtrim((string)$_SERVER['DOCUMENT_ROOT'], '/');
		foreach ($rows as $id => $row) {
			$path = CFile::GetPath($id);
			if (!$path) {
				$subdir = trim((string)$row['SUBDIR'], '/');
				$path = '/upload/' . ($subdir !== '' ? $subdir . '/' : '') . $row['FILE_NAME'];
			}
			$absPath = $docRoot . $path;
			$exists = is_file($absPath);
			$created = $exists ? date('Y-m-d H:i:s', (int)filectime($absPath)) : '';
			$modifiedFs = $exists ? date('Y-m-d H:i:s', (int)filemtime($absPath)) : '';
			$modifiedDb = (string)$row['TIMESTAMP_X'];

			$out[] = [
				'ID' => $id,
				'MODULE_ID' => (string)$row['MODULE_ID'],
				'FILE_NAME' => (string)$row['FILE_NAME'],
				'ORIGINAL_NAME' => (string)($row['ORIGINAL_NAME'] ?: $row['FILE_NAME']),
				'SUBDIR' => (string)$row['SUBDIR'],
				'CONTENT_TYPE' => (string)$row['CONTENT_TYPE'],
				'WIDTH' => (int)$row['WIDTH'],
				'HEIGHT' => (int)$row['HEIGHT'],
				'FILE_SIZE' => (int)$row['FILE_SIZE'],
				'PATH' => $path,
				'URL' => self::absoluteUrl($path),
				'EXISTS' => $exists,
				'CREATED' => $created,
				'MODIFIED_FS' => $modifiedFs,
				'MODIFIED_DB' => $modifiedDb,
				'USAGE' => $usageMap[$id] ?? [],
			];
		}

		return ['total' => $total, 'rows' => $out];
	}

	/**
	 * Умный поиск: название товара/раздела, артикул, ID элемента/раздела.
	 * @return int[] file IDs
	 */
	public static function findFileIdsByProductQuery(string $q): array
	{
		global $DB;

		$q = trim($q);
		if ($q === '') {
			return [];
		}

		$safe = $DB->ForSql($q);
		$ids = [];
		$elementIds = [];

		// Exact element/section ID
		if (ctype_digit($q)) {
			$id = (int)$q;
			$elementIds[$id] = true;

			$res = $DB->Query("
				SELECT PREVIEW_PICTURE, DETAIL_PICTURE
				FROM b_iblock_element
				WHERE ID = {$id}
			");
			if ($row = $res->Fetch()) {
				if ((int)$row['PREVIEW_PICTURE'] > 0) {
					$ids[(int)$row['PREVIEW_PICTURE']] = true;
				}
				if ((int)$row['DETAIL_PICTURE'] > 0) {
					$ids[(int)$row['DETAIL_PICTURE']] = true;
				}
			}

			$res = $DB->Query("
				SELECT PICTURE, DETAIL_PICTURE
				FROM b_iblock_section
				WHERE ID = {$id}
			");
			if ($row = $res->Fetch()) {
				if ((int)$row['PICTURE'] > 0) {
					$ids[(int)$row['PICTURE']] = true;
				}
				if ((int)$row['DETAIL_PICTURE'] > 0) {
					$ids[(int)$row['DETAIL_PICTURE']] = true;
				}
			}
		}

		// Name / code of elements
		$res = $DB->Query("
			SELECT ID, PREVIEW_PICTURE, DETAIL_PICTURE
			FROM b_iblock_element
			WHERE NAME LIKE '%{$safe}%' OR CODE LIKE '%{$safe}%'
			LIMIT 500
		");
		while ($row = $res->Fetch()) {
			$elementIds[(int)$row['ID']] = true;
			if ((int)$row['PREVIEW_PICTURE'] > 0) {
				$ids[(int)$row['PREVIEW_PICTURE']] = true;
			}
			if ((int)$row['DETAIL_PICTURE'] > 0) {
				$ids[(int)$row['DETAIL_PICTURE']] = true;
			}
		}

		// Name / code of sections
		$res = $DB->Query("
			SELECT ID, PICTURE, DETAIL_PICTURE
			FROM b_iblock_section
			WHERE NAME LIKE '%{$safe}%' OR CODE LIKE '%{$safe}%'
			LIMIT 500
		");
		while ($row = $res->Fetch()) {
			if ((int)$row['PICTURE'] > 0) {
				$ids[(int)$row['PICTURE']] = true;
			}
			if ((int)$row['DETAIL_PICTURE'] > 0) {
				$ids[(int)$row['DETAIL_PICTURE']] = true;
			}
		}

		// Article (CML2_ARTICLE / ARTICLS and similar)
		$res = $DB->Query("
			SELECT DISTINCT ep.IBLOCK_ELEMENT_ID, e.PREVIEW_PICTURE, e.DETAIL_PICTURE
			FROM b_iblock_element_property ep
			INNER JOIN b_iblock_property pr ON pr.ID = ep.IBLOCK_PROPERTY_ID
			INNER JOIN b_iblock_element e ON e.ID = ep.IBLOCK_ELEMENT_ID
			WHERE (
				pr.CODE IN ('CML2_ARTICLE', 'ARTICLS', 'ARTNUMBER', 'ARTICLE')
				OR pr.NAME LIKE '%ртикул%'
			)
			AND ep.VALUE LIKE '%{$safe}%'
			LIMIT 500
		");
		while ($row = $res->Fetch()) {
			$elementIds[(int)$row['IBLOCK_ELEMENT_ID']] = true;
			if ((int)$row['PREVIEW_PICTURE'] > 0) {
				$ids[(int)$row['PREVIEW_PICTURE']] = true;
			}
			if ((int)$row['DETAIL_PICTURE'] > 0) {
				$ids[(int)$row['DETAIL_PICTURE']] = true;
			}
		}

		// File properties of matched elements
		if ($elementIds) {
			$elList = implode(',', array_map('intval', array_keys($elementIds)));
			$res = $DB->Query("
				SELECT DISTINCT ep.VALUE
				FROM b_iblock_element_property ep
				INNER JOIN b_iblock_property pr ON pr.ID = ep.IBLOCK_PROPERTY_ID AND pr.PROPERTY_TYPE = 'F'
				WHERE ep.IBLOCK_ELEMENT_ID IN ({$elList})
					AND ep.VALUE REGEXP '^[0-9]+$'
			");
			while ($row = $res->Fetch()) {
				$fid = (int)$row['VALUE'];
				if ($fid > 0) {
					$ids[$fid] = true;
				}
			}
		}

		return array_map('intval', array_keys($ids));
	}

	/** @param int[] $ids @return array<int,string[]> */
	public static function resolveUsage(array $ids): array
	{
		global $DB;
		$map = [];
		foreach ($ids as $id) {
			$map[(int)$id] = [];
		}
		if (!$ids) {
			return $map;
		}

		$idList = implode(',', array_map('intval', $ids));

		$res = $DB->Query("
			SELECT e.ID, e.IBLOCK_ID, e.IBLOCK_SECTION_ID, e.NAME, e.PREVIEW_PICTURE, e.DETAIL_PICTURE, e.CODE,
				b.NAME AS IBLOCK_NAME, b.DETAIL_PAGE_URL
			FROM b_iblock_element e
			LEFT JOIN b_iblock b ON b.ID = e.IBLOCK_ID
			WHERE e.PREVIEW_PICTURE IN ({$idList}) OR e.DETAIL_PICTURE IN ({$idList})
		");
		while ($row = $res->Fetch()) {
			$el = 'IBLOCK «' . $row['IBLOCK_NAME'] . '» #' . $row['IBLOCK_ID']
				. ' / элемент #' . $row['ID'] . ' «' . $row['NAME'] . '»';
			$admin = '/bitrix/admin/iblock_element_edit.php?IBLOCK_ID=' . (int)$row['IBLOCK_ID']
				. '&type=&ID=' . (int)$row['ID'] . '&lang=ru';
			$public = self::buildPublicUrl((string)$row['DETAIL_PAGE_URL'], [
				'#ELEMENT_ID#' => (string)$row['ID'],
				'#ELEMENT_CODE#' => (string)($row['CODE'] ?: $row['ID']),
				'#SECTION_ID#' => (string)$row['IBLOCK_SECTION_ID'],
				'#SECTION_CODE#' => '',
				'#SECTION_CODE_PATH#' => self::sectionCodePath((int)$row['IBLOCK_SECTION_ID']),
			]);
			$links = ($public !== '' ? ' (site: ' . $public . ')' : '') . ' (admin: ' . $admin . ')';
			if ((int)$row['PREVIEW_PICTURE'] && isset($map[(int)$row['PREVIEW_PICTURE']])) {
				$map[(int)$row['PREVIEW_PICTURE']][] = $el . ' — PREVIEW_PICTURE' . $links;
			}
			if ((int)$row['DETAIL_PICTURE'] && isset($map[(int)$row['DETAIL_PICTURE']])) {
				$map[(int)$row['DETAIL_PICTURE']][] = $el . ' — DETAIL_PICTURE' . $links;
			}
		}

		$res = $DB->Query("
			SELECT s.ID, s.IBLOCK_ID, s.IBLOCK_SECTION_ID, s.NAME, s.CODE, s.PICTURE, s.DETAIL_PICTURE,
				b.NAME AS IBLOCK_NAME, b.SECTION_PAGE_URL
			FROM b_iblock_section s
			LEFT JOIN b_iblock b ON b.ID = s.IBLOCK_ID
			WHERE s.PICTURE IN ({$idList}) OR s.DETAIL_PICTURE IN ({$idList})
		");
		while ($row = $res->Fetch()) {
			$sec = 'IBLOCK «' . $row['IBLOCK_NAME'] . '» #' . $row['IBLOCK_ID']
				. ' / раздел #' . $row['ID'] . ' «' . $row['NAME'] . '»';
			$admin = '/bitrix/admin/iblock_section_edit.php?IBLOCK_ID=' . (int)$row['IBLOCK_ID']
				. '&ID=' . (int)$row['ID'] . '&lang=ru';
			$public = self::buildPublicUrl((string)$row['SECTION_PAGE_URL'], [
				'#SECTION_ID#' => (string)$row['ID'],
				'#SECTION_CODE#' => (string)($row['CODE'] ?: $row['ID']),
				'#SECTION_CODE_PATH#' => self::sectionCodePath((int)$row['ID']),
			]);
			$links = ($public !== '' ? ' (site: ' . $public . ')' : '') . ' (admin: ' . $admin . ')';
			if ((int)$row['PICTURE'] && isset($map[(int)$row['PICTURE']])) {
				$map[(int)$row['PICTURE']][] = $sec . ' — PICTURE' . $links;
			}
			if ((int)$row['DETAIL_PICTURE'] && isset($map[(int)$row['DETAIL_PICTURE']])) {
				$map[(int)$row['DETAIL_PICTURE']][] = $sec . ' — DETAIL_PICTURE' . $links;
			}
		}

		$res = $DB->Query("
			SELECT p.IBLOCK_ELEMENT_ID, p.VALUE, p.IBLOCK_PROPERTY_ID,
				e.NAME AS ELEMENT_NAME, e.IBLOCK_ID, e.CODE AS ELEMENT_CODE, e.IBLOCK_SECTION_ID,
				pr.NAME AS PROP_NAME, pr.CODE AS PROP_CODE,
				b.NAME AS IBLOCK_NAME, b.DETAIL_PAGE_URL
			FROM b_iblock_element_property p
			INNER JOIN b_iblock_property pr ON pr.ID = p.IBLOCK_PROPERTY_ID AND pr.PROPERTY_TYPE = 'F'
			INNER JOIN b_iblock_element e ON e.ID = p.IBLOCK_ELEMENT_ID
			LEFT JOIN b_iblock b ON b.ID = e.IBLOCK_ID
			WHERE p.VALUE IN ({$idList})
		");
		while ($row = $res->Fetch()) {
			$fid = (int)$row['VALUE'];
			if (!isset($map[$fid])) {
				continue;
			}
			$admin = '/bitrix/admin/iblock_element_edit.php?IBLOCK_ID=' . (int)$row['IBLOCK_ID']
				. '&type=&ID=' . (int)$row['IBLOCK_ELEMENT_ID'] . '&lang=ru';
			$public = self::buildPublicUrl((string)$row['DETAIL_PAGE_URL'], [
				'#ELEMENT_ID#' => (string)$row['IBLOCK_ELEMENT_ID'],
				'#ELEMENT_CODE#' => (string)($row['ELEMENT_CODE'] ?: $row['IBLOCK_ELEMENT_ID']),
				'#SECTION_ID#' => (string)$row['IBLOCK_SECTION_ID'],
				'#SECTION_CODE#' => '',
				'#SECTION_CODE_PATH#' => self::sectionCodePath((int)$row['IBLOCK_SECTION_ID']),
			]);
			$links = ($public !== '' ? ' (site: ' . $public . ')' : '') . ' (admin: ' . $admin . ')';
			$map[$fid][] = 'IBLOCK «' . $row['IBLOCK_NAME'] . '» #' . $row['IBLOCK_ID']
				. ' / элемент #' . $row['IBLOCK_ELEMENT_ID'] . ' «' . $row['ELEMENT_NAME'] . '»'
				. ' — свойство «' . $row['PROP_NAME'] . '» [' . $row['PROP_CODE'] . ']' . $links;
		}

		// User fields (UF) — file type in b_uts_* / b_utm_*
		$res = $DB->Query("
			SELECT f.ID, f.ENTITY_ID, f.FIELD_NAME, f.USER_TYPE_ID
			FROM b_user_field f
			WHERE f.USER_TYPE_ID IN ('file', 'employee') OR f.USER_TYPE_ID LIKE '%file%'
		");
		$ufMeta = [];
		while ($row = $res->Fetch()) {
			$ufMeta[] = $row;
		}
		foreach ($ufMeta as $uf) {
			$entity = (string)$uf['ENTITY_ID'];
			$field = (string)$uf['FIELD_NAME'];
			if (!preg_match('/^IBLOCK_(\d+)_SECTION$/', $entity, $m) && !preg_match('/^HLBLOCK_/', $entity)) {
				// IBLOCK_n_ELEMENT stored in b_uts_iblock_n_element or similar
			}
			if (preg_match('/^IBLOCK_(\d+)_ELEMENT$/', $entity, $m)) {
				$iblockId = (int)$m[1];
				$table = 'b_uts_iblock_' . $iblockId . '_element';
				if (!$DB->TableExists($table)) {
					continue;
				}
				$col = $DB->ForSql($field);
				$chk = $DB->Query("SHOW COLUMNS FROM `{$table}` LIKE '{$col}'");
				if (!$chk->Fetch()) {
					continue;
				}
				$r2 = $DB->Query("
					SELECT VALUE_ID, `{$col}` AS FILE_VAL
					FROM `{$table}`
					WHERE `{$col}` IN ({$idList})
				");
				while ($row = $r2->Fetch()) {
					$fid = (int)$row['FILE_VAL'];
					if (!isset($map[$fid])) {
						continue;
					}
					$map[$fid][] = "UF {$entity}.{$field} → элемент VALUE_ID #" . (int)$row['VALUE_ID']
						. " (IBLOCK {$iblockId})";
				}
			}
		}

		foreach ($map as $id => $list) {
			$map[$id] = array_values(array_unique($list));
			if (!$map[$id]) {
				$map[$id][] = 'Не найдено в каталоге (возможно шаблон, медиабиблиотека или устаревшая запись)';
			}
		}

		return $map;
	}

	public static function modulesList(): array
	{
		global $DB;
		$out = [];
		$res = $DB->Query("
			SELECT MODULE_ID, COUNT(*) CNT
			FROM b_file
			WHERE " . self::imageWhereSql() . "
			GROUP BY MODULE_ID
			ORDER BY CNT DESC
		");
		while ($row = $res->Fetch()) {
			$out[] = [
				'id' => (string)$row['MODULE_ID'],
				'cnt' => (int)$row['CNT'],
			];
		}
		return $out;
	}

	public static function formatSize(int $bytes): string
	{
		if ($bytes < 1024) {
			return $bytes . ' B';
		}
		if ($bytes < 1048576) {
			return round($bytes / 1024, 1) . ' KB';
		}
		return round($bytes / 1048576, 2) . ' MB';
	}

	public static function sectionCodePath(int $sectionId): string
	{
		static $cache = [];
		if ($sectionId <= 0) {
			return '';
		}
		if (isset($cache[$sectionId])) {
			return $cache[$sectionId];
		}

		global $DB;
		$codes = [];
		$current = $sectionId;
		while ($current > 0) {
			$row = $DB->Query("
				SELECT ID, IBLOCK_SECTION_ID, CODE
				FROM b_iblock_section
				WHERE ID = " . (int)$current . "
				LIMIT 1
			")->Fetch();
			if (!$row) {
				break;
			}
			array_unshift($codes, (string)($row['CODE'] ?: $row['ID']));
			$current = (int)$row['IBLOCK_SECTION_ID'];
		}

		return $cache[$sectionId] = implode('/', $codes);
	}

	public static function buildPublicUrl(string $template, array $replace): string
	{
		$template = trim($template);
		if ($template === '') {
			return '';
		}

		$url = strtr($template, $replace + [
			'#SITE_DIR#' => '/',
			'// ' => '/',
		]);
		$url = preg_replace('#/+#', '/', $url);
		$url = '/' . ltrim($url, '/');

		return self::absoluteUrl($url);
	}

	public static function downloadFile(int $id): void
	{
		$file = CFile::GetFileArray($id);
		if (!$file) {
			http_response_code(404);
			echo 'File not found';
			exit;
		}
		$path = CFile::GetPath($id);
		$abs = rtrim((string)$_SERVER['DOCUMENT_ROOT'], '/') . $path;
		if (!is_file($abs)) {
			http_response_code(404);
			echo 'File missing on disk';
			exit;
		}
		$name = $file['ORIGINAL_NAME'] ?: $file['FILE_NAME'];
		$name = preg_replace('/[^\w.\-а-яА-ЯёЁ]+/u', '_', $name) ?: ('file_' . $id);
		header('Content-Type: ' . ($file['CONTENT_TYPE'] ?: 'application/octet-stream'));
		header('Content-Length: ' . filesize($abs));
		header('Content-Disposition: attachment; filename="' . $name . '"');
		header('X-Content-Type-Options: nosniff');
		readfile($abs);
		exit;
	}

	/** Excel XML Spreadsheet 2003 — открывается в Excel / Numbers / LibreOffice */
	public static function exportExcel(array $rows): void
	{
		$filename = 'vilmed-images-' . date('Y-m-d-His') . '.xls';
		header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
		header('Content-Disposition: attachment; filename="' . $filename . '"');
		header('Cache-Control: max-age=0');

		echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
		echo '<?mso-application progid="Excel.Sheet"?>' . "\n";
		echo '<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet"'
			. ' xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet">' . "\n";
		echo '<Styles><Style ss:ID="h"><Font ss:Bold="1"/></Style></Styles>' . "\n";
		echo '<Worksheet ss:Name="Images"><Table>' . "\n";

		$cols = [
			'ID', 'Где используется', 'Прямая ссылка', 'Путь', 'Имя файла', 'Оригинал',
			'Модуль', 'Ш×В', 'Размер', 'Создан (файл)', 'Изменён (файл)', 'Изменён (БД)', 'На диске',
		];
		echo '<Row ss:StyleID="h">';
		foreach ($cols as $c) {
			echo '<Cell><Data ss:Type="String">' . self::xml($c) . '</Data></Cell>';
		}
		echo '</Row>' . "\n";

		foreach ($rows as $r) {
			$usage = implode("\n", $r['USAGE']);
			$wh = ($r['WIDTH'] || $r['HEIGHT']) ? ($r['WIDTH'] . '×' . $r['HEIGHT']) : '';
			$cells = [
				(string)$r['ID'],
				$usage,
				$r['URL'],
				$r['PATH'],
				$r['FILE_NAME'],
				$r['ORIGINAL_NAME'],
				$r['MODULE_ID'],
				$wh,
				self::formatSize((int)$r['FILE_SIZE']),
				$r['CREATED'],
				$r['MODIFIED_FS'],
				$r['MODIFIED_DB'],
				$r['EXISTS'] ? 'да' : 'нет',
			];
			echo '<Row>';
			foreach ($cells as $i => $val) {
				$type = ($i === 0 && ctype_digit($val)) ? 'Number' : 'String';
				echo '<Cell><Data ss:Type="' . $type . '">' . self::xml($val) . '</Data></Cell>';
			}
			echo '</Row>' . "\n";
		}

		echo '</Table></Worksheet></Workbook>';
		exit;
	}

	private static function xml(string $s): string
	{
		return htmlspecialchars($s, ENT_QUOTES | ENT_XML1, 'UTF-8');
	}
}

if (!VilmedSiteImages::isAllowed()) {
	header('HTTP/1.1 403 Forbidden');
	header('Content-Type: text/html; charset=utf-8');
	echo '<!doctype html><html lang="ru"><meta charset="utf-8"><title>403</title>';
	echo '<body style="font:14px/1.5 system-ui;padding:40px"><h1>Доступ запрещён</h1><p>'
		. htmlspecialchars(VilmedSiteImages::accessDeniedReason())
		. '</p></body></html>';
	exit;
}

$action = (string)($_GET['action'] ?? '');
if ($action === 'download') {
	VilmedSiteImages::downloadFile((int)($_GET['id'] ?? 0));
}

$q = trim((string)($_GET['q'] ?? ''));
$module = trim((string)($_GET['module'] ?? ''));
$usage = trim((string)($_GET['usage'] ?? ''));
$page = max(1, (int)($_GET['page'] ?? 1));
$limit = max(1, min(VilmedSiteImages::PAGE_SIZE_MAX, (int)($_GET['limit'] ?? VilmedSiteImages::PAGE_SIZE_DEFAULT)));
$offset = ($page - 1) * $limit;
$export = isset($_GET['export']) && $_GET['export'] === 'excel';

$result = VilmedSiteImages::fetch([
	'q' => $q,
	'module' => $module,
	'usage' => $usage,
	'limit' => $limit,
	'offset' => $offset,
	'export' => $export,
]);

if ($export) {
	VilmedSiteImages::exportExcel($result['rows']);
}

$total = $result['total'];
$rows = $result['rows'];
$pages = max(1, (int)ceil($total / $limit));
$modules = VilmedSiteImages::modulesList();

$qs = static function (array $extra = []) use ($q, $module, $usage, $limit, $page): string {
	$params = array_filter([
		'q' => $q,
		'module' => $module,
		'usage' => $usage,
		'limit' => $limit,
		'page' => $page,
	], static fn($v) => $v !== '' && $v !== null);
	return '?' . http_build_query(array_merge($params, $extra));
};

header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Pragma: no-cache');
?>
<!doctype html>
<html lang="ru">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title>Картинки сайта — vilmed.ru</title>
	<style>
		:root {
			--bg: #f0f3f7;
			--card: #fff;
			--line: #d9e0ea;
			--text: #1a2332;
			--muted: #66778a;
			--accent: #0b6bcb;
			--accent2: #0a8f5a;
			--danger: #c0392b;
		}
		* { box-sizing: border-box; }
		body {
			margin: 0;
			font: 14px/1.45 "Segoe UI", system-ui, sans-serif;
			background: linear-gradient(160deg, #e8eef6 0%, #f5f7fa 40%, #eef2f6 100%);
			color: var(--text);
			min-height: 100vh;
		}
		.wrap { max-width: 1400px; margin: 0 auto; padding: 28px 20px 60px; }
		h1 { margin: 0 0 6px; font-size: 26px; letter-spacing: -0.02em; }
		.sub { color: var(--muted); margin: 0 0 22px; }
		.toolbar {
			display: flex; flex-wrap: wrap; gap: 10px; align-items: end;
			background: var(--card); border: 1px solid var(--line); border-radius: 12px;
			padding: 16px; margin-bottom: 18px; box-shadow: 0 1px 2px rgba(16,24,40,.04);
		}
		label { display: flex; flex-direction: column; gap: 4px; font-size: 12px; color: var(--muted); }
		input, select {
			min-width: 160px; padding: 8px 10px; border: 1px solid var(--line);
			border-radius: 8px; font: inherit; color: var(--text); background: #fff;
		}
		input:focus, select:focus { outline: 2px solid rgba(11,107,203,.25); border-color: var(--accent); }
		.btn {
			display: inline-flex; align-items: center; gap: 6px;
			padding: 9px 14px; border-radius: 8px; border: 0; cursor: pointer;
			font: inherit; text-decoration: none; color: #fff; background: var(--accent);
		}
		.btn:hover { filter: brightness(1.05); }
		.btn.green { background: var(--accent2); }
		.btn.ghost { background: #eef3f9; color: var(--text); border: 1px solid var(--line); }
		.btn.sm { padding: 6px 10px; font-size: 12px; }
		.meta { display: flex; flex-wrap: wrap; gap: 14px; margin-bottom: 12px; color: var(--muted); font-size: 13px; }
		.meta b { color: var(--text); }
		.table-wrap {
			background: var(--card); border: 1px solid var(--line); border-radius: 12px;
			overflow: auto; box-shadow: 0 1px 2px rgba(16,24,40,.04);
		}
		table { width: 100%; border-collapse: collapse; min-width: 1100px; }
		th, td { padding: 10px 12px; border-bottom: 1px solid var(--line); vertical-align: top; text-align: left; }
		th { background: #f7f9fc; font-size: 12px; text-transform: uppercase; letter-spacing: .03em; color: var(--muted); position: sticky; top: 0; z-index: 1; }
		tr:hover td { background: #fafbfd; }
		th.col-dl, td.col-dl {
			vertical-align: middle;
			text-align: center;
			width: 112px;
			white-space: nowrap;
		}
		.thumb {
			width: 64px; height: 64px; object-fit: contain; background: #f3f5f8;
			border: 1px solid var(--line); border-radius: 8px;
		}
		.missing { color: var(--danger); font-size: 12px; }
		.usage { font-size: 12px; color: var(--text); max-width: 360px; }
		.usage li { margin: 0 0 4px; }
		.usage a { color: var(--accent); }
		.url {
			display: flex; align-items: flex-start; gap: 8px; max-width: 340px;
		}
		.url a {
			word-break: break-all; color: var(--accent); font-size: 12px; flex: 1; min-width: 0;
		}
		.copy-btn {
			flex: 0 0 auto; width: 32px; height: 32px; padding: 0;
			border: 1px solid var(--line); border-radius: 8px; background: #eef3f9;
			color: var(--text); cursor: pointer; display: inline-flex;
			align-items: center; justify-content: center;
		}
		.copy-btn:hover { background: #e2eaf4; }
		.copy-btn.ok { background: #e8f8ef; border-color: #b7e4c7; color: var(--accent2); }
		.copy-btn svg { width: 16px; height: 16px; display: block; }
		.dates { font-size: 12px; color: var(--muted); white-space: nowrap; }
		.dates b { color: var(--text); font-weight: 600; display: block; }
		.pager { display: flex; gap: 8px; flex-wrap: wrap; margin-top: 16px; align-items: center; }
		.pager span { color: var(--muted); font-size: 13px; }
		.actions {
			display: inline-flex;
			align-items: center;
			justify-content: center;
			min-width: 0;
		}
		.actions .btn.sm {
			white-space: nowrap;
			min-width: 88px;
			justify-content: center;
		}
	</style>
</head>
<body>
<div class="wrap">
	<h1>Картинки сайта</h1>
	<p class="sub">Реестр изображений из <code>b_file</code> / <code>/upload/</code> — где используется, прямая ссылка, скачивание, даты.</p>

	<form class="toolbar" method="get" action="/tools/site-images.php">
		<label>Поиск
			<input type="search" name="q" value="<?=htmlspecialchars($q)?>" placeholder="название, артикул, ID товара">
		</label>
		<label>Модуль
			<select name="module">
				<option value="">все</option>
				<?php foreach ($modules as $m): ?>
					<option value="<?=htmlspecialchars($m['id'])?>" <?= $module === $m['id'] ? 'selected' : '' ?>>
						<?=htmlspecialchars(($m['id'] !== '' ? $m['id'] : '(пусто)') . ' — ' . $m['cnt'])?>
					</option>
				<?php endforeach; ?>
			</select>
		</label>
		<label>Где используется (текст)
			<input type="search" name="usage" value="<?=htmlspecialchars($usage)?>" placeholder="PREVIEW, свойство…">
		</label>
		<label>На странице
			<select name="limit">
				<?php foreach ([50, 100, 200, 500] as $n): ?>
					<option value="<?=$n?>" <?= $limit === $n ? 'selected' : '' ?>><?=$n?></option>
				<?php endforeach; ?>
			</select>
		</label>
		<button class="btn" type="submit">Показать</button>
		<a class="btn green" href="<?=htmlspecialchars($qs(['export' => 'excel', 'page' => null]))?>">Скачать Excel</a>
	</form>

	<div class="meta">
		<span>Найдено: <b><?=number_format($total, 0, '.', ' ')?></b></span>
		<span>Страница: <b><?=$page?></b> / <?=$pages?></span>
		<span>Показано: <b><?=count($rows)?></b></span>
	</div>

	<div class="table-wrap">
		<table>
			<thead>
			<tr>
				<th>Превью</th>
				<th>Где используется</th>
				<th>Прямая ссылка</th>
				<th class="col-dl">Скачать</th>
				<th>Даты</th>
				<th>Файл</th>
			</tr>
			</thead>
			<tbody>
			<?php if (!$rows): ?>
				<tr><td colspan="6">Ничего не найдено.</td></tr>
			<?php endif; ?>
			<?php foreach ($rows as $r): ?>
				<tr>
					<td>
						<?php if ($r['EXISTS']): ?>
							<a href="<?=htmlspecialchars($r['PATH'])?>" target="_blank" rel="noopener">
								<img class="thumb" src="<?=htmlspecialchars($r['PATH'])?>" alt="" loading="lazy">
							</a>
						<?php else: ?>
							<div class="missing">нет на диске<br>ID <?=$r['ID']?></div>
						<?php endif; ?>
					</td>
					<td class="usage">
						<ul style="margin:0;padding-left:16px">
							<?php foreach ($r['USAGE'] as $u): ?>
								<?php
								$uHtml = htmlspecialchars($u);
								$uHtml = preg_replace(
									'#\(site: (https?://[^)]+)\)#',
									'(<a href="$1" target="_blank" rel="noopener">сайт</a>)',
									$uHtml
								);
								$uHtml = preg_replace(
									'#\(admin: (/bitrix/admin/[^)]+)\)#',
									'(<a href="$1" target="_blank" rel="noopener">админка</a>)',
									$uHtml
								);
								?>
								<li><?=$uHtml?></li>
							<?php endforeach; ?>
						</ul>
						<div style="margin-top:6px;color:var(--muted)">модуль: <?=htmlspecialchars($r['MODULE_ID'] ?: '—')?></div>
					</td>
					<td>
						<div class="url">
							<a href="<?=htmlspecialchars($r['URL'])?>" target="_blank" rel="noopener"><?=htmlspecialchars($r['URL'])?></a>
							<button type="button" class="copy-btn" data-copy="<?=htmlspecialchars($r['URL'], ENT_QUOTES)?>" title="Копировать ссылку" aria-label="Копировать ссылку">
								<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
									<rect x="9" y="9" width="13" height="13" rx="2"></rect>
									<path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path>
								</svg>
							</button>
						</div>
					</td>
					<td class="col-dl">
						<div class="actions">
						<?php if ($r['EXISTS']): ?>
							<a class="btn sm" href="/tools/site-images.php?action=download&amp;id=<?=$r['ID']?>">Скачать</a>
						<?php else: ?>
							<span class="missing">недоступно</span>
						<?php endif; ?>
						</div>
					</td>
					<td class="dates">
						<b>Создан</b><?=htmlspecialchars($r['CREATED'] ?: '—')?>
						<b style="margin-top:6px">Изменён (файл)</b><?=htmlspecialchars($r['MODIFIED_FS'] ?: '—')?>
						<b style="margin-top:6px">Изменён (БД)</b><?=htmlspecialchars($r['MODIFIED_DB'] ?: '—')?>
					</td>
					<td>
						<div><b>#<?=$r['ID']?></b></div>
						<div><?=htmlspecialchars($r['ORIGINAL_NAME'])?></div>
						<div style="color:var(--muted);font-size:12px;margin-top:4px">
							<?=$r['WIDTH'] && $r['HEIGHT'] ? ($r['WIDTH'] . '×' . $r['HEIGHT'] . ' · ') : ''?>
							<?=htmlspecialchars(VilmedSiteImages::formatSize((int)$r['FILE_SIZE']))?>
							· <?=htmlspecialchars($r['CONTENT_TYPE'] ?: '—')?>
						</div>
					</td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
	</div>

	<div class="pager">
		<?php if ($page > 1): ?>
			<a class="btn ghost sm" href="<?=htmlspecialchars($qs(['page' => $page - 1]))?>">← Назад</a>
		<?php endif; ?>
		<span>стр. <?=$page?> из <?=$pages?></span>
		<?php if ($page < $pages): ?>
			<a class="btn ghost sm" href="<?=htmlspecialchars($qs(['page' => $page + 1]))?>">Вперёд →</a>
		<?php endif; ?>
	</div>
</div>
<script>
document.querySelectorAll('.copy-btn').forEach(function (btn) {
	btn.addEventListener('click', function () {
		var text = btn.getAttribute('data-copy') || '';
		function done() {
			btn.classList.add('ok');
			btn.title = 'Скопировано';
			setTimeout(function () {
				btn.classList.remove('ok');
				btn.title = 'Копировать ссылку';
			}, 1200);
		}
		if (navigator.clipboard && navigator.clipboard.writeText) {
			navigator.clipboard.writeText(text).then(done).catch(function () {
				fallbackCopy(text, done);
			});
		} else {
			fallbackCopy(text, done);
		}
	});
});
function fallbackCopy(text, done) {
	var ta = document.createElement('textarea');
	ta.value = text;
	ta.setAttribute('readonly', '');
	ta.style.position = 'fixed';
	ta.style.left = '-9999px';
	document.body.appendChild(ta);
	ta.select();
	try { document.execCommand('copy'); } catch (e) {}
	document.body.removeChild(ta);
	done();
}
</script>
</body>
</html>
