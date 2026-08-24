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

function vilmedSearchSectionFacetCounts(array $elementIds, int $iblockId): array
{
    $sectionMap = vilmedSearchElementSectionMap($elementIds, $iblockId);
    $counts = [];

    foreach ($sectionMap as $secId) {
        $secId = (int)$secId;
        if ($secId <= 0) {
            continue;
        }
        $rs = CIBlockSection::GetNavChain($iblockId, $secId, ['ID']);
        while ($s = $rs->Fetch()) {
            $id = (int)$s['ID'];
            if ($id <= 0) {
                continue;
            }
            if (!isset($counts[$id])) {
                $counts[$id] = 0;
            }
            $counts[$id]++;
        }
    }

    return $counts;
}

function vilmedSearchLookupTerms(string $lookup): array
{
    $lookup = mb_strtolower(trim($lookup));
    if ($lookup === '') {
        return [];
    }

    $terms = [$lookup];
    $tr = [
        'а' => 'a', 'б' => 'b', 'в' => 'v', 'г' => 'g', 'д' => 'd', 'е' => 'e', 'ё' => 'e',
        'ж' => 'zh', 'з' => 'z', 'и' => 'i', 'й' => 'y', 'к' => 'k', 'л' => 'l', 'м' => 'm',
        'н' => 'n', 'о' => 'o', 'п' => 'p', 'р' => 'r', 'с' => 's', 'т' => 't', 'у' => 'u',
        'ф' => 'f', 'х' => 'h', 'ц' => 'c', 'ч' => 'ch', 'ш' => 'sh', 'щ' => 'sch',
        'ъ' => '', 'ы' => 'y', 'ь' => '', 'э' => 'e', 'ю' => 'yu', 'я' => 'ya',
    ];

    if (preg_match('/[а-яё]/u', $lookup)) {
        $latin = '';
        $len = mb_strlen($lookup);
        for ($i = 0; $i < $len; $i++) {
            $ch = mb_strtolower(mb_substr($lookup, $i, 1));
            $latin .= $tr[$ch] ?? $ch;
        }
        if ($latin !== '' && $latin !== $lookup) {
            $terms[] = $latin;
        }
    }

    if (preg_match('/^[a-z]+$/', $lookup)) {
        if (strlen($lookup) >= 4) {
            $terms[] = substr($lookup, 0, -1);
        }
        if (strlen($lookup) >= 5) {
            $terms[] = substr($lookup, 0, -2);
        }
    }

    return array_values(array_unique(array_filter($terms)));
}

function vilmedSearchLookupSections(string $lookup, array $elementIds, int $iblockId, int $limit = 30): array
{
    if (mb_strlen(trim($lookup)) < 2 || !CModule::IncludeModule('iblock')) {
        return [];
    }

    $terms = vilmedSearchLookupTerms($lookup);
    $found = [];

    foreach ($terms as $term) {
        $rs = CIBlockSection::GetList(
            ['NAME' => 'ASC'],
            [
                'IBLOCK_ID' => $iblockId,
                'ACTIVE' => 'Y',
                'GLOBAL_ACTIVE' => 'Y',
                '%NAME' => $term,
            ],
            false,
            ['ID', 'NAME', 'SECTION_PAGE_URL', 'PICTURE']
        );

        while ($sec = $rs->GetNext()) {
            $id = (int)$sec['ID'];
            if ($id <= 0 || isset($found[$id])) {
                continue;
            }
            $found[$id] = $sec;
        }
    }

    if (empty($found)) {
        return [];
    }

    $items = [];
    foreach ($found as $id => $sec) {
        $count = empty($elementIds)
            ? 0
            : count(vilmedSearchFilterBySection($elementIds, $id, $iblockId));
        $pic = '';
        if (!empty($sec['PICTURE'])) {
            $file = CFile::GetFileArray($sec['PICTURE']);
            if (is_array($file) && !empty($file['SRC'])) {
                $pic = $file['SRC'];
            }
        }
        $items[] = [
            'ID' => $id,
            'NAME' => $sec['NAME'],
            'URL' => $sec['SECTION_PAGE_URL'],
            'COUNT' => $count,
            'PICTURE' => $pic,
        ];
    }

    usort($items, static function ($a, $b) {
        if ($a['COUNT'] !== $b['COUNT']) {
            return $b['COUNT'] <=> $a['COUNT'];
        }
        return strcmp($a['NAME'], $b['NAME']);
    });

    return array_slice($items, 0, $limit);
}

function vilmedSearchSectionFacets(array $elementIds, int $iblockId, int $limit = 18): array
{
    if (empty($elementIds) || !CModule::IncludeModule('iblock')) {
        return [];
    }

    $counts = vilmedSearchSectionFacetCounts($elementIds, $iblockId);
    if (empty($counts)) {
        return [];
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

function vilmedSearchParseSectionIds($raw): array
{
    if (is_array($raw)) {
        $ids = $raw;
    } else {
        $ids = preg_split('/[\s,]+/', trim((string)$raw), -1, PREG_SPLIT_NO_EMPTY);
    }

    $out = [];
    foreach ($ids as $id) {
        $id = (int)$id;
        if ($id > 0) {
            $out[$id] = $id;
        }
    }

    return array_values($out);
}

function vilmedSearchFilterBySections(array $elementIds, array $sectionIds, int $iblockId): array
{
    $sectionIds = vilmedSearchParseSectionIds($sectionIds);
    if (empty($sectionIds) || empty($elementIds)) {
        return $elementIds;
    }

    if (count($sectionIds) === 1) {
        return vilmedSearchFilterBySection($elementIds, $sectionIds[0], $iblockId);
    }

    $allowed = [];
    foreach ($sectionIds as $sectionId) {
        foreach (vilmedSearchFilterBySection($elementIds, (int)$sectionId, $iblockId) as $id) {
            $allowed[(int)$id] = true;
        }
    }

    $out = [];
    foreach ($elementIds as $id) {
        $id = (int)$id;
        if (isset($allowed[$id])) {
            $out[] = $id;
        }
    }

    return $out;
}

function vilmedSearchEnrichProduct(int $productId, int $iblockId): array
{
    $meta = [
        'PRICE_PRINT' => '',
        'PRICE_VALUE' => 0.0,
        'PRICE_FROM' => false,
        'CAN_BUY' => false,
        'BUY_ID' => $productId,
        'NEED_SKU' => false,
    ];

    if ($productId <= 0 || !CModule::IncludeModule('catalog')) {
        return $meta;
    }

    global $USER;
    $groups = (is_object($USER) && method_exists($USER, 'GetUserGroupArray'))
        ? $USER->GetUserGroupArray()
        : [2];

    $offerIds = [];
    if (CModule::IncludeModule('iblock')) {
        $offers = CCatalogSku::getOffersList(
            [$productId],
            $iblockId,
            ['ACTIVE' => 'Y', 'AVAILABLE' => 'Y'],
            ['ID'],
            []
        );
        if (!empty($offers[$productId]) && is_array($offers[$productId])) {
            $offerIds = array_keys($offers[$productId]);
            if (count($offerIds) > 1) {
                $meta['NEED_SKU'] = true;
                $meta['PRICE_FROM'] = true;
            } elseif (count($offerIds) === 1) {
                $meta['BUY_ID'] = (int)$offerIds[0];
            }
        }
    }

    $arPrice = CCatalogProduct::GetOptimalPrice($productId, 1, $groups, 'N');
    if (!empty($arPrice['RESULT_PRICE'])) {
        $rp = $arPrice['RESULT_PRICE'];
        $meta['PRICE_VALUE'] = (float)$rp['DISCOUNT_PRICE'];
        if (CModule::IncludeModule('currency')) {
            $formatted = CCurrencyLang::CurrencyFormat(
                $rp['DISCOUNT_PRICE'],
                $rp['CURRENCY'],
                true
            );
            $meta['PRICE_PRINT'] = html_entity_decode(strip_tags($formatted), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }
        if (!empty($arPrice['PRODUCT_ID'])) {
            $meta['BUY_ID'] = (int)$arPrice['PRODUCT_ID'];
        }
    }

    if ($meta['PRICE_FROM'] && $meta['PRICE_PRINT'] !== '') {
        $meta['PRICE_PRINT'] = 'от ' . $meta['PRICE_PRINT'];
    }

    $catalogProduct = CCatalogProduct::GetByID($meta['BUY_ID']);
    if (is_array($catalogProduct) && $meta['PRICE_VALUE'] > 0) {
        $available = ($catalogProduct['AVAILABLE'] ?? 'N') === 'Y';
        $canBuyZero = ($catalogProduct['CAN_BUY_ZERO'] ?? 'N') === 'Y';
        $qty = (float)($catalogProduct['QUANTITY'] ?? 0);
        $meta['CAN_BUY'] = $available || $canBuyZero || $qty > 0;
    }

    return $meta;
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

        $productId = (int)$el['ID'];
        $commerce = vilmedSearchEnrichProduct($productId, $iblockId);

        $items[] = [
            'ID' => $productId,
            'NAME' => $el['NAME'],
            'URL' => $el['DETAIL_PAGE_URL'],
            'IMAGE' => $pic,
            'SECTION_ID' => (int)($el['IBLOCK_SECTION_ID'] ?? 0),
            'TYPE' => 'product',
            'PRICE_PRINT' => $commerce['PRICE_PRINT'],
            'PRICE_VALUE' => $commerce['PRICE_VALUE'],
            'CAN_BUY' => $commerce['CAN_BUY'],
            'BUY_ID' => $commerce['BUY_ID'],
            'NEED_SKU' => $commerce['NEED_SKU'],
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

function vilmedSearchCatalogUrl(string $query, $sectionIds = []): string
{
    if (!is_array($sectionIds)) {
        $sectionIds = (int)$sectionIds > 0 ? [(int)$sectionIds] : [];
    }
    $sectionIds = vilmedSearchParseSectionIds($sectionIds);

    $params = ['q' => $query];
    foreach ($sectionIds as $id) {
        $params['section_id'][] = $id;
    }

    return '/catalog/?' . http_build_query($params, '', '&', PHP_QUERY_RFC3986);
}

function vilmedSearchToggleSectionUrl(string $query, array $currentIds, int $toggleId): string
{
    $ids = vilmedSearchParseSectionIds($currentIds);
    $pos = array_search($toggleId, $ids, true);
    if ($pos !== false) {
        unset($ids[$pos]);
    } else {
        $ids[] = $toggleId;
    }

    return vilmedSearchCatalogUrl($query, array_values($ids));
}
