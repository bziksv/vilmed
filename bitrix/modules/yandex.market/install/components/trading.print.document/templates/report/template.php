<?php
/** @noinspection PhpVariableIsUsedOnlyInClosureInspection */
if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED!==true) { die(); }

use Bitrix\Main\Localization\Loc;
use Bitrix\Main\Web\Json;

/** @var \CBitrixComponentTemplate $this */
/** @var string $templateFolder */

$langPrefix = 'YANDEX_MARKET_T_TRADING_PRINT_REPORT_';

if (empty($arParams['REPORT_ID']))
{
	ShowError(Loc::getMessage($langPrefix . 'MISSING_REPORT_ID'));
	return;
}

$waitTime = (int)$arParams['REPORT_WAIT'];

if ($waitTime === 10000) { $waitTime = 1000; }

?>
<div id="report-downloader"></div>
<script>
	(function() {
		const downloader = new ReportDownloader('#report-downloader', <?= Json::encode([
			'setupId' => (string)$arParams['SETUP_ID'],
			'reportId' => (string)$arParams['REPORT_ID'],
			'waitTime' => $waitTime,
			'sessid' => bitrix_sessid(),
			'url' => $templateFolder . '/ajax.php',
			'lang' => array_reduce([
				'WAIT',
				'SECOND_1',
				'SECOND_2',
				'SECOND_5',
				'FETCH',
				'FILE',
			], static function(array $carry, $key) use ($langPrefix) {
				$carry[$key] = Loc::getMessage( $langPrefix . $key);
				return $carry;
			}, []),
		]) ?>);

		downloader.start();
	})();
</script>
