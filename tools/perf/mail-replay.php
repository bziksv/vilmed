#!/usr/bin/env php
<?php
/**
 * Догоняющая отправка b_event с лимитом (по умолчанию ≤40/час).
 *
 *   php tools/perf/mail-replay.php prepare 14822 14915
 *   php tools/perf/mail-replay.php run
 *   php tools/perf/mail-replay.php status
 *   php tools/perf/mail-replay.php finish
 *
 * На время replay отключить в crontab строку cron_events.php и добавить cron каждые 2 мин:
 *   /opt/php74/bin/php -f .../tools/perf/mail-replay.php run >> .../upload/logs/mail-replay.log 2>&1
 */
if (PHP_SAPI !== 'cli') {
	fwrite(STDERR, "CLI only\n");
	exit(1);
}

$_SERVER['DOCUMENT_ROOT'] = realpath(__DIR__ . '/../..');
$DOCUMENT_ROOT = $_SERVER['DOCUMENT_ROOT'];

define('NO_KEEP_STATISTIC', true);
define('NOT_CHECK_PERMISSIONS', true);
define('BX_CRONTAB', true);
define('BX_WITH_ON_AFTER_EPILOG', true);
define('BX_NO_ACCELERATOR_RESET', true);

require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php';

use Bitrix\Main\Config\Option;
use Bitrix\Main\Mail\Event;
use Bitrix\Main\Mail\Internal\EventAttachmentTable;
use Bitrix\Main\Mail\Internal\EventTable;
use Bitrix\Main\Type\DateTime;

const OPT_ACTIVE = 'vmd_mail_replay_active';
const OPT_FROM = 'vmd_mail_replay_from';
const OPT_TO = 'vmd_mail_replay_to';
const OPT_LAST_RUN = 'vmd_mail_replay_last_run';
const OPT_INTERVAL = 'vmd_mail_replay_interval_sec';
const DEFAULT_INTERVAL = 90; // 40 писем/час

$cmd = $argv[1] ?? 'status';

function logLine(string $msg): void
{
	$ts = date('Y-m-d H:i:s');
	fwrite(STDOUT, "[$ts] $msg\n");
}

function getInterval(): int
{
	$n = (int)Option::get('main', OPT_INTERVAL, (string)DEFAULT_INTERVAL);
	return $n >= 60 ? $n : DEFAULT_INTERVAL;
}

function modifiers(): array
{
	$out = [];
	foreach (EventTable::getFetchModificatorsForFieldsField() as $callable) {
		if (is_callable($callable)) {
			$out[] = $callable;
		}
	}
	return $out;
}

function sendOneInRange(int $fromId, int $toId): ?array
{
	// Как EventManager::executeEvents — raw SQL, иначе ORM уже unserialize'ит C_FIELDS
	// и повторные modificators ломают поля (пустые заказы в письме).
	$connection = \Bitrix\Main\Application::getConnection();
	$arMail = $connection->query("
		SELECT ID, C_FIELDS, EVENT_NAME, MESSAGE_ID, LID,
			DATE_FORMAT(DATE_INSERT, '%d.%m.%Y %H:%i:%s') AS DATE_INSERT,
			DUPLICATE, LANGUAGE_ID
		FROM b_event
		WHERE SUCCESS_EXEC='N' AND ID >= {$fromId} AND ID <= {$toId}
		ORDER BY ID
		LIMIT 1
	")->fetch();

	if (!$arMail) {
		return null;
	}

	foreach (modifiers() as $callable) {
		$arMail['C_FIELDS'] = call_user_func_array($callable, [$arMail['C_FIELDS']]);
	}

	$arFiles = [];
	$fileListDb = EventAttachmentTable::getList([
		'select' => ['FILE_ID'],
		'filter' => ['=EVENT_ID' => $arMail['ID']],
	]);
	while ($file = $fileListDb->fetch()) {
		$arFiles[] = $file['FILE_ID'];
	}
	$arMail['FILE'] = $arFiles;

	if (!is_array($arMail['C_FIELDS'])) {
		$arMail['C_FIELDS'] = [];
	}

	try {
		$flag = Event::handleEvent($arMail);
		EventTable::update($arMail['ID'], [
			'SUCCESS_EXEC' => $flag,
			'DATE_EXEC' => new DateTime(),
		]);
	} catch (\Throwable $e) {
		EventTable::update($arMail['ID'], [
			'SUCCESS_EXEC' => 'E',
			'DATE_EXEC' => new DateTime(),
		]);
		throw $e;
	}

	return ['id' => (int)$arMail['ID'], 'event' => $arMail['EVENT_NAME'], 'flag' => $flag];
}

switch ($cmd) {
	case 'prepare':
		$fromId = (int)($argv[2] ?? 0);
		$toId = (int)($argv[3] ?? 0);
		if ($fromId <= 0 || $toId <= 0 || $fromId > $toId) {
			fwrite(STDERR, "Usage: mail-replay.php prepare FROM_ID TO_ID\n");
			exit(1);
		}

		$connection = \Bitrix\Main\Application::getConnection();
		$connection->queryExecute(
			"UPDATE b_event SET SUCCESS_EXEC='N', DATE_EXEC=NULL "
			. "WHERE ID >= {$fromId} AND ID <= {$toId}"
		);
		$affected = $connection->getAffectedRowsCount();

		Option::set('main', OPT_ACTIVE, 'Y');
		Option::set('main', OPT_FROM, (string)$fromId);
		Option::set('main', OPT_TO, (string)$toId);
		Option::set('main', OPT_LAST_RUN, '0');
		if (!Option::get('main', OPT_INTERVAL, '')) {
			Option::set('main', OPT_INTERVAL, (string)DEFAULT_INTERVAL);
		}

		$pending = EventTable::getCount([
			'=SUCCESS_EXEC' => 'N',
			'>=ID' => $fromId,
			'<=ID' => $toId,
		]);

		logLine("prepare OK: range {$fromId}-{$toId}, reset rows ~{$affected}, pending={$pending}");
		logLine('Отключите cron_events.php в crontab, добавьте cron каждые 2 мин: mail-replay.php run');
		break;

	case 'run':
		if (Option::get('main', OPT_ACTIVE, 'N') !== 'Y') {
			exit(0);
		}

		$fromId = (int)Option::get('main', OPT_FROM, '0');
		$toId = (int)Option::get('main', OPT_TO, '0');
		if ($fromId <= 0 || $toId <= 0) {
			fwrite(STDERR, "Replay range not configured. Run prepare first.\n");
			exit(1);
		}

		$interval = getInterval();
		$lastRun = (int)Option::get('main', OPT_LAST_RUN, '0');
		$now = time();
		if ($lastRun > 0 && ($now - $lastRun) < $interval) {
			exit(0);
		}

		$result = sendOneInRange($fromId, $toId);
		Option::set('main', OPT_LAST_RUN, (string)$now);

		if ($result === null) {
			Option::set('main', OPT_ACTIVE, 'N');
			logLine("DONE: queue empty for {$fromId}-{$toId}. Включите обратно cron_events.php");
			break;
		}

		$pending = EventTable::getCount([
			'=SUCCESS_EXEC' => 'N',
			'>=ID' => $fromId,
			'<=ID' => $toId,
		]);

		logLine("sent id={$result['id']} {$result['event']} flag={$result['flag']}, pending={$pending}");

		if ($pending === 0) {
			Option::set('main', OPT_ACTIVE, 'N');
			logLine('DONE: all sent. Включите обратно cron_events.php');
		}
		break;

	case 'finish':
		Option::set('main', OPT_ACTIVE, 'N');
		logLine('replay deactivated (finish). Включите cron_events.php');
		break;

	case 'status':
	default:
		$active = Option::get('main', OPT_ACTIVE, 'N');
		$fromId = (int)Option::get('main', OPT_FROM, '0');
		$toId = (int)Option::get('main', OPT_TO, '0');
		$interval = getInterval();
		$pending = ($fromId && $toId)
			? EventTable::getCount(['=SUCCESS_EXEC' => 'N', '>=ID' => $fromId, '<=ID' => $toId])
			: 0;
		$err = ($fromId && $toId)
			? EventTable::getCount(['=SUCCESS_EXEC' => 'E', '>=ID' => $fromId, '<=ID' => $toId])
			: 0;
		logLine("active={$active} range={$fromId}-{$toId} pending={$pending} errors={$err} interval={$interval}s");
		break;
}

CMain::FinalActions();
