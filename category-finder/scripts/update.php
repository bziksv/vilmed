<?php
define('NOT_CHECK_FILE_PERMISSIONS', true);
define('PUBLIC_AJAX_MODE', true);
define('NO_KEEP_STATISTIC', 'Y');
define('STOP_STATISTICS', true);
define('BX_SECURITY_SHOW_MESSAGE', true);

require_once($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php');
require_once __DIR__ . '/CategoryFinderService.php';

use Bitrix\Main\Loader;

Loader::includeModule('iblock');

global $USER;
header('Content-Type: application/json; charset=utf-8');

if (!$USER->IsAuthorized()) {
    http_response_code(403);
    echo json_encode(['status' => 'fail', 'error' => 'auth required']);
    exit;
}

$id = (int)($_POST['id'] ?? $_POST['ID'] ?? 0);
$withoutProd = filter_var($_POST['without_prod'] ?? $_POST['WITHOUT_PROD'] ?? false, FILTER_VALIDATE_BOOLEAN);

if (!$id) {
    http_response_code(400);
    echo json_encode(['status' => 'fail', 'error' => 'id required']);
    exit;
}

$ok = (new CategoryFinderService())->setWithoutProd($id, $withoutProd);

echo json_encode(['status' => $ok ? 'ok' : 'fail']);
