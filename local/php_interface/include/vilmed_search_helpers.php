<?php
if (!defined('B_PROLOG_INCLUDED') && php_sapi_name() !== 'cli') {
    return;
}

/**
 * Shared search helpers: ID normalization, section facets, CSearch query.
 */
function vilmedSearchCatalogIblockId(): int
{
    return 24;
}

function vilmedSearchNormalizeElementIds(array $rawIds, int $iblockId): array
{
    if (empty($rawIds) || !CModule::IncludeModule('catalog')) {
        return [];
    }

    $out = [];
    foreach ($rawIds as $id) {
        $id = (int)$id;
        if ($id <= 0) {
            continue;
        }
        $mx = CCatalogSku::GetProductInfo($id);
        $out[] = is_array($mx) ? (int)$mx['ID'] : $id;
    }

    return array_values(array_unique(array_filter($out)));
}

function vilmedSearchRunQuery(string $query, int $iblockId, int $limit = 900): array
{
    $query = trim($query);
    if ($query === '' || !CModule::IncludeModule('search') || !CModule::IncludeModule('iblock')) {
        return [];
    }

    CUtil::decodeURIComponent($query);

    $searchQuery = $query;
    if (CModule::IncludeModule('search')) {
        $arLang = CSearchLanguage::GuessLanguage($query);
        if (is_array($arLang) && $arLang['from'] != $arLang['to']) {
            $alt = CSearchLanguage::ConvertKeyboardLayout($query, $arLang['from'], $arLang['to']);
            if (is_string($alt) && $alt !== '') {
                $searchQuery = $alt;
            }
        }
    }

    $exFilter = [
        'MODULE_ID' => 'iblock',
        'PARAM1' => 'catalog',
        'PARAM2' => [$iblockId],
    ];

    $sort = ['CUSTOM_RANK' => 'DESC', 'RANK' => 'DESC', 'DATE_CHANGE' => 'DESC'];
    $ids = [];
    $seen = [];

    $obSearch = new CSearch();
    $obSearch->Search(
        [
            'QUERY' => $searchQuery,
            'SITE_ID' => SITE_ID,
        ],
        $sort,
        $exFilter
    );

    while ($row = $obSearch->Fetch()) {
        if (($row['MODULE_ID'] ?? '') !== 'iblock') {
            continue;
        }
        $itemId = $row['ITEM_ID'] ?? '';
        if (!is_numeric($itemId)) {
            continue;
        }
        $id = (int)$itemId;
        if (isset($seen[$id])) {
            continue;
        }
        $seen[$id] = true;
        $ids[] = $id;
        if (count($ids) >= $limit) {
            break;
        }
    }

    return vilmedSearchNormalizeElementIds($ids, $iblockId);
}

function vilmedSearchElementSectionMap(array $elementIds, int $iblockId): array
{
    $map = [];
    if (empty($elementIds) || !CModule::IncludeModule('iblock')) {
        return $map;
    }

    $rs = CIBlockElement::GetList(
        [],
        [
            'ID' => $elementIds,
            'IBLOCK_ID' => $iblockId,
            'ACTIVE' => 'Y',
            'SECTION_GLOBAL_ACTIVE' => 'Y',
        ],
        false,
        false,
        ['ID', 'IBLOCK_SECTION_ID']
    );

    while ($el = $rs->Fetch()) {
        $secId = (int)($el['IBLOCK_SECTION_ID'] ?? 0);
        if ($secId > 0) {
            $map[(int)$el['ID']] = $secId;
        }
    }

    return $map;
}

function vilmedSearchSectionFacets(array $elementIds, int $iblockId, int $limit = 18): array
{
    if (empty($elementIds) || !CModule::IncludeModule('iblock')) {
        return [];
    }

    $sectionMap = vilmedSearchElementSectionMap($elementIds, $iblockId);
    if (empty($sectionMap)) {
        return [];
    }

    $counts = [];
    foreach ($sectionMap as $secId) {
        if (!isset($counts[$secId])) {
            $counts[$secId] = 0;
        }
        $counts[$secId]++;
    }

    arsort($counts);
    $counts = array_slice($counts, 0, $limit, true);

    $sections = [];
    $rs = CIBlockSection::GetList(
        ['SORT' => 'ASC', 'NAME' => 'ASC'],
        [
            'ID' => array_keys($counts),
            'IBLOCK_ID' => $iblockId,
            'ACTIVE' => 'Y',
            'GLOBAL_ACTIVE' => 'Y',
        ],
        false,
        ['ID', 'NAME', 'SECTION_PAGE_URL', 'PICTURE']
    );

    while ($sec = $rs->GetNext()) {
        $id = (int)$sec['ID'];
        if (!isset($counts[$id])) {
            continue;
        }
        $pic = '';
        if (!empty($sec['PICTURE'])) {
            $file = CFile::GetFileArray($sec['PICTURE']);
            if (is_array($file) && !empty($file['SRC'])) {
                $pic = $file['SRC'];
            }
        }
        $sections[] = [
            'ID' => $id,
            'NAME' => $sec['NAME'],
            'URL' => $sec['SECTION_PAGE_URL'],
            'COUNT' => $counts[$id],
            'PICTURE' => $pic,
        ];
    }

    usort($sections, static function ($a, $b) {
        if ($a['COUNT'] === $b['COUNT']) {
            return strcmp($a['NAME'], $b['NAME']);
        }
        return $b['COUNT'] <=> $a['COUNT'];
    });

    return $sections;
}

function vilmedSearchFilterBySection(array $elementIds, int $sectionId, int $iblockId): array
{
    if ($sectionId <= 0 || empty($elementIds) || !CModule::IncludeModule('iblock')) {
        return $elementIds;
    }

    $allowed = [];
    $rs = CIBlockElement::GetList(
        [],
        [
            'ID' => $elementIds,
            'IBLOCK_ID' => $iblockId,
            'ACTIVE' => 'Y',
            'SECTION_ID' => $sectionId,
            'INCLUDE_SUBSECTIONS' => 'Y',
            'SECTION_GLOBAL_ACTIVE' => 'Y',
        ],
        false,
        false,
        ['ID']
    );
    while ($el = $rs->Fetch()) {
        $allowed[(int)$el['ID']] = true;
    }

    $out = [];
    foreach ($elementIds as $id) {
        if (isset($allowed[(int)$id])) {
            $out[] = (int)$id;
        }
    }

    return $out;
}

function vilmedSearchBuildProducts(array $elementIds, int $iblockId, int $limit = 12): array
{
    if (empty($elementIds) || !CModule::IncludeModule('iblock')) {
        return [];
    }

    $elementIds = array_slice(array_values($elementIds), 0, $limit);
    $order = array_flip($elementIds);
    $items = [];

    $rs = CIBlockElement::GetList(
        [],
        [
            'ID' => $elementIds,
            'IBLOCK_ID' => $iblockId,
            'ACTIVE' => 'Y',
            'SECTION_GLOBAL_ACTIVE' => 'Y',
        ],
        false,
        false,
        ['ID', 'NAME', 'DETAIL_PAGE_URL', 'PREVIEW_PICTURE', 'IBLOCK_SECTION_ID']
    );

    while ($el = $rs->GetNext()) {
        $pic = '';
        if (!empty($el['PREVIEW_PICTURE'])) {
            $file = CFile::GetFileArray($el['PREVIEW_PICTURE']);
            if (is_array($file) && !empty($file['SRC'])) {
                if ($file['WIDTH'] > 80 || $file['HEIGHT'] > 80) {
                    $resized = CFile::ResizeImageGet(
                        $file,
                        ['width' => 80, 'height' => 80],
                        BX_RESIZE_IMAGE_PROPORTIONAL,
                        true
                    );
                    $pic = $resized['src'] ?? $file['SRC'];
                } else {
                    $pic = $file['SRC'];
                }
            }
        }
        if ($pic === '') {
            $pic = SITE_TEMPLATE_PATH . '/images/no-photo.jpg';
        }

        $items[] = [
            'ID' => (int)$el['ID'],
            'NAME' => $el['NAME'],
            'URL' => $el['DETAIL_PAGE_URL'],
            'IMAGE' => $pic,
            'SECTION_ID' => (int)($el['IBLOCK_SECTION_ID'] ?? 0),
            'TYPE' => 'product',
        ];
    }

    usort($items, static function ($a, $b) use ($order) {
        return ($order[$a['ID']] ?? 9999) <=> ($order[$b['ID']] ?? 9999);
    });

    return $items;
}

function vilmedSearchBuildSectionsFromIndex(array $rawHits, int $iblockId, int $limit = 5): array
{
    if (empty($rawHits) || !CModule::IncludeModule('iblock')) {
        return [];
    }

    $ids = [];
    foreach ($rawHits as $hit) {
        $itemId = is_array($hit) ? ($hit['ITEM_ID'] ?? '') : (string)$hit;
        if (is_string($itemId) && substr($itemId, 0, 1) === 'S') {
            $ids[] = (int)substr($itemId, 1);
        }
    }
    $ids = array_values(array_unique(array_filter($ids)));
    if (empty($ids)) {
        return [];
    }

    $items = [];
    $rs = CIBlockSection::GetList(
        ['SORT' => 'ASC'],
        [
            'ID' => array_slice($ids, 0, $limit),
            'IBLOCK_ID' => $iblockId,
            'ACTIVE' => 'Y',
            'GLOBAL_ACTIVE' => 'Y',
        ],
        false,
        ['ID', 'NAME', 'SECTION_PAGE_URL', 'PICTURE']
    );

    while ($sec = $rs->GetNext()) {
        $pic = '';
        if (!empty($sec['PICTURE'])) {
            $file = CFile::GetFileArray($sec['PICTURE']);
            if (is_array($file) && !empty($file['SRC'])) {
                $pic = $file['SRC'];
            }
        }
        $items[] = [
            'ID' => (int)$sec['ID'],
            'NAME' => $sec['NAME'],
            'URL' => $sec['SECTION_PAGE_URL'],
            'IMAGE' => $pic ?: (SITE_TEMPLATE_PATH . '/images/no-photo.jpg'),
            'TYPE' => 'section',
        ];
    }

    return $items;
}

function vilmedSearchCatalogUrl(string $query, int $sectionId = 0): string
{
    $params = ['q' => $query];
    if ($sectionId > 0) {
        $params['section_id'] = $sectionId;
    }

    return '/catalog/?' . http_build_query($params, '', '&', PHP_QUERY_RFC3986);
}
