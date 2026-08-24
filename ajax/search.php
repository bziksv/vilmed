<?php
define('NO_KEEP_STATISTIC', true);
define('NOT_CHECK_PERMISSIONS', true);
require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php';

require_once $_SERVER['DOCUMENT_ROOT'] . '/local/php_interface/include/vilmed_search_helpers.php';

header('Content-Type: application/json; charset=UTF-8');
header('X-Robots-Tag: noindex');

$query = trim((string)($_REQUEST['q'] ?? ''));
$sectionId = (int)($_REQUEST['section_id'] ?? 0);
$productLimit = min(20, max(4, (int)($_REQUEST['limit'] ?? 10)));
$facetLimit = min(24, max(6, (int)($_REQUEST['facet_limit'] ?? 16)));

$response = [
    'ok' => true,
    'query' => $query,
    'section_id' => $sectionId,
    'total' => 0,
    'sections' => [],
    'facets' => [],
    'products' => [],
    'catalog_url' => '/catalog/',
];

if ($query === '' || mb_strlen($query) < 2) {
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    die();
}

$iblockId = vilmedSearchCatalogIblockId();
$allIds = vilmedSearchRunQuery($query, $iblockId, 900);
$response['total'] = count($allIds);
$response['facets'] = vilmedSearchSectionFacets($allIds, $iblockId, $facetLimit);

$filteredIds = $sectionId > 0
    ? vilmedSearchFilterBySection($allIds, $sectionId, $iblockId)
    : $allIds;

$response['products'] = vilmedSearchBuildProducts($filteredIds, $iblockId, $productLimit);
$response['catalog_url'] = vilmedSearchCatalogUrl($query, $sectionId);

// Direct section hits from search index (category rows in autocomplete).
if (CModule::IncludeModule('search') && CModule::IncludeModule('iblock')) {
    CUtil::decodeURIComponent($query);
    $searchQuery = $query;
    $arLang = CSearchLanguage::GuessLanguage($query);
    if (is_array($arLang) && $arLang['from'] != $arLang['to']) {
        $alt = CSearchLanguage::ConvertKeyboardLayout($query, $arLang['from'], $arLang['to']);
        if (is_string($alt) && $alt !== '') {
            $searchQuery = $alt;
        }
    }

    $exFilter = [
        'MODULE_ID' => 'iblock',
        'PARAM1' => 'catalog',
        'PARAM2' => [$iblockId],
    ];
    $sort = ['CUSTOM_RANK' => 'DESC', 'RANK' => 'DESC'];
    $rawHits = [];
    $obSearch = new CSearch();
    $obSearch->Search(['QUERY' => $searchQuery, 'SITE_ID' => SITE_ID], $sort, $exFilter);
    while ($row = $obSearch->Fetch()) {
        $rawHits[] = $row;
        if (count($rawHits) >= 30) {
            break;
        }
    }
    $response['sections'] = vilmedSearchBuildSectionsFromIndex($rawHits, $iblockId, 4);
}

echo json_encode($response, JSON_UNESCAPED_UNICODE);
die();
