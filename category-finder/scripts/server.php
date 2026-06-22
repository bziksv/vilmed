<?php
define('NOT_CHECK_FILE_PERMISSIONS', true);
define('PUBLIC_AJAX_MODE', true);
define('NO_KEEP_STATISTIC', 'Y');
define('STOP_STATISTICS', true);
define('BX_SECURITY_SHOW_MESSAGE', true);

require_once($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php');
require_once __DIR__ . '/CategoryFinderService.php';

use Bitrix\Main\Loader;
use Bitrix\Main\Context;

Loader::includeModule('iblock');

global $USER;
if (!$USER->IsAuthorized()) {
    http_response_code(403);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => 'auth required']);
    exit;
}

$request = Context::getCurrent()->getRequest();
$protocol = $request->isHttps() ? 'https' : 'http';
$domain = $request->getHttpHost();
$siteUrlPrefix = $protocol . '://' . $domain;

$duplicateMode = (string)($_POST['filter_duplicate'] ?? '');

$service = new CategoryFinderService();
$rows = $service->getList([
    'iblock_id' => (int)($_POST['filter_iblock'] ?? 24),
    'level' => (int)($_POST['filter_level'] ?? 1),
    'cnt' => $_POST['filter_cnt'] ?? '',
    'active' => $_POST['filter_active'] ?? '',
    'redirect' => $_POST['filter_redirect'] ?? '',
    'without_prod' => $_POST['filter_without_prod'] ?? '',
    'name' => trim((string)($_POST['filter_name'] ?? '')),
    'storefront' => (string)($_POST['filter_storefront'] ?? ''),
    'duplicate' => $duplicateMode,
    'duplicate_similarity' => (int)($_POST['filter_duplicate_similarity'] ?? CategoryFinderService::DEFAULT_URL_SIMILARITY),
]);

$data = [];
$duplicateGroupCount = 0;

foreach ($rows as $i => $row) {
    $name = htmlspecialchars($row['name'], ENT_QUOTES, 'UTF-8');
    $adminUrl = htmlspecialchars($row['admin_url'], ENT_QUOTES, 'UTF-8');
    $publicPath = htmlspecialchars($row['public_url'], ENT_QUOTES, 'UTF-8');
    $publicTitle = htmlspecialchars($siteUrlPrefix . $row['public_url'], ENT_QUOTES, 'UTF-8');
    $code = htmlspecialchars($row['code'], ENT_QUOTES, 'UTF-8');
    $storefrontLabel = htmlspecialchars($row['storefront_label'], ENT_QUOTES, 'UTF-8');
    $duplicateGroup = (int)$row['duplicate_group'];

    if ($duplicateGroup > $duplicateGroupCount) {
        $duplicateGroupCount = $duplicateGroup;
    }

    $data[] = [
        $i + 1,
        $row['depth'],
        $row['id'],
        $row['count'],
        !empty($row['include_sub_categories']) ? 'Да' : 'Нет',
        (int)$row['subtree_count'],
        $storefrontLabel,
        !empty($row['active']) ? 'Да' : 'Нет',
        '<a href="' . $adminUrl . '" target="_blank" rel="noopener">' . $name . '</a>',
        $code,
        '<a href="' . $publicPath . '" target="_blank" rel="noopener" title="' . $publicTitle . '">' . $publicPath . '</a>',
        renderDuplicateMatches($row['duplicate_matches'] ?? []),
        !empty($row['without_prod']),
        $duplicateGroup,
    ];
}

header('Content-Type: application/json; charset=utf-8');
$total = count($data);
echo json_encode([
    'draw' => (int)($_POST['draw'] ?? 0),
    'data' => $data,
    'recordsTotal' => $total,
    'recordsFiltered' => $total,
    'duplicateMode' => $duplicateMode,
    'duplicateGroupCount' => $duplicateGroupCount,
]);

/**
 * @param array<int, array{id:int,reason:string}> $matches
 */
function renderDuplicateMatches(array $matches)
{
    if (!$matches) {
        return '';
    }

    $parts = [];
    foreach ($matches as $match) {
        $id = (int)($match['id'] ?? 0);
        if (!$id) {
            continue;
        }
        $reason = htmlspecialchars($match['reason'] ?? '', ENT_QUOTES, 'UTF-8');
        $parts[] = '<a href="#" class="cf-dup-link" data-cf-id="' . $id . '">' . $id . ' (' . $reason . ')</a>';
    }

    if (!$parts) {
        return '';
    }

    if (count($parts) > 5) {
        return implode(', ', array_slice($parts, 0, 5)) . '…';
    }

    return implode(', ', $parts);
}
