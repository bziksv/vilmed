<?php
define('NO_KEEP_STATISTIC', true);
define('NOT_CHECK_PERMISSIONS', true);
require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php';

require_once $_SERVER['DOCUMENT_ROOT'] . '/local/php_interface/include/vilmed_search_helpers.php';

header('Content-Type: application/json; charset=UTF-8');
header('X-Robots-Tag: noindex');

$query = trim((string)($_REQUEST['q'] ?? ''));
$sectionLookup = trim((string)($_REQUEST['section_lookup'] ?? ''));
$sectionIds = vilmedSearchParseSectionIds($_REQUEST['section_id'] ?? $_REQUEST['section_ids'] ?? '');
$productLimit = min(24, max(4, (int)($_REQUEST['limit'] ?? 12)));
$facetLimit = min(50, max(6, (int)($_REQUEST['facet_limit'] ?? 40)));

$response = [
    'ok' => true,
    'query' => $query,
    'section_ids' => $sectionIds,
    'total' => 0,
    'filtered_total' => 0,
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

if ($sectionLookup !== '' && mb_strlen($sectionLookup) >= 2) {
    $response['total'] = count($allIds);
    $response['filtered_total'] = count($allIds);
    $response['facets'] = vilmedSearchLookupSections($sectionLookup, $allIds, $iblockId, 30);
    $response['lookup'] = true;
    $response['catalog_url'] = vilmedSearchCatalogUrl($query, $sectionIds);
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    die();
}

$filteredIds = vilmedSearchFilterBySections($allIds, $sectionIds, $iblockId);

$response['total'] = count($allIds);
$response['filtered_total'] = count($filteredIds);
$response['facets'] = vilmedSearchSectionFacets($allIds, $iblockId, $facetLimit);
$response['products'] = vilmedSearchBuildProducts($filteredIds, $iblockId, $productLimit);
$response['catalog_url'] = vilmedSearchCatalogUrl($query, $sectionIds);

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
