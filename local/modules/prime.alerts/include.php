<?php

use Bitrix\Main\Loader;

Loader::registerAutoLoadClasses('prime.alerts', [
	'Prime\\Alerts\\EmailPolicy' => 'lib/EmailPolicy.php',
	'Prime\\Alerts\\Handlers' => 'lib/Handlers.php',
	'Prime\\Alerts\\Config' => 'lib/Config.php',
	'Prime\\Alerts\\Frontend' => 'lib/Frontend.php',
]);
