<?php
/**
 * Фоновый пересчёт групп дублей: php dup-build.php <DOCUMENT_ROOT> <E|S> <min_score>
 * Запускается из DupCache::start().
 */
if (PHP_SAPI !== 'cli') {
	exit;
}

$docRoot = rtrim((string) ($argv[1] ?? ''), '/');
if ($docRoot === '' || !is_file($docRoot . '/bitrix/modules/main/include/prolog_before.php')) {
	fwrite(STDERR, "bad DOCUMENT_ROOT\n");
	exit(1);
}
$_SERVER['DOCUMENT_ROOT'] = $docRoot;
$entity = ($argv[2] ?? 'E') === 'S' ? 'S' : 'E';
$minScore = (int) ($argv[3] ?? 0);

define('NO_KEEP_STATISTIC', true);
define('NO_AGENT_STATISTIC', true);
define('NO_AGENT_CHECK', true);
define('NOT_CHECK_PERMISSIONS', true);
define('BX_CRONTAB', true);

require $docRoot . '/bitrix/modules/main/include/prolog_before.php';

if (!\Bitrix\Main\Loader::includeModule('iblock') || !\Bitrix\Main\Loader::includeModule('titlo.relevance')) {
	exit(1);
}

\Titlo\Relevance\DupCache::run($entity, $minScore);
