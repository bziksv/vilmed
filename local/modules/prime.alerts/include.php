<?php

use Bitrix\Main\Config\Option;
use Bitrix\Main\Loader;

Loader::registerAutoLoadClasses('prime.alerts', [
	'Prime\\Alerts\\EmailPolicy' => 'lib/EmailPolicy.php',
	'Prime\\Alerts\\Handlers' => 'lib/Handlers.php',
	'Prime\\Alerts\\Config' => 'lib/Config.php',
	'Prime\\Alerts\\Frontend' => 'lib/Frontend.php',
]);

// Перерегистрация обработчиков при обновлении файлов модуля без переустановки
$eventsVer = '1.2.1';
if (Option::get('prime.alerts', 'events_version', '') !== $eventsVer) {
	$installFile = __DIR__ . '/install/index.php';
	if (is_file($installFile)) {
		include_once $installFile;
		if (class_exists('prime_alerts', false)) {
			$ob = new prime_alerts();
			$ob->InstallEvents();
			Option::set('prime.alerts', 'events_version', $eventsVer);
		}
	}
}
