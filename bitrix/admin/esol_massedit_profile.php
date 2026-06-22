<?php
/**
 * esol.massedit — отключён: модуль не в репо, точка входа атаки по логам.
 * На prod удалить bitrix/modules/esol.massedit если установлен.
 */
http_response_code(403);
header('Content-Type: text/plain; charset=utf-8');
die('Access denied: esol.massedit disabled');
