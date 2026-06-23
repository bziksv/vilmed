<?php

class CategoryFinderService
{
    const DUPLICATE_NONE = '';
    const DUPLICATE_NAME = 'name';
    const DUPLICATE_URL = 'url';
    const DUPLICATE_BOTH = 'both';

    const DEFAULT_URL_SIMILARITY = 85;

    const STOREFRONT_ALL = '';
    const STOREFRONT_EMPTY = 'empty';
    const STOREFRONT_FROM_SUB = 'from_sub';
    const STOREFRONT_OWN = 'own';
    const STOREFRONT_ANY = 'any';

    /** @var bool */
    protected $includeSubsections = true;

    /**
     * @param array $filters
     * @return array<int, array>
     */
    public function getList(array $filters)
    {
        $iblockId = (int)($filters['iblock_id'] ?? 0);
        if (!$iblockId) {
            return [];
        }

        $minDepth = max(1, (int)($filters['level'] ?? 1));
        $countFilter = $filters['cnt'] ?? '';
        $active = $filters['active'] ?? '';
        $redirect = $filters['redirect'] ?? '';
        $withoutProd = $filters['without_prod'] ?? '';
        $nameFilter = trim((string)($filters['name'] ?? ''));
        $storefront = (string)($filters['storefront'] ?? '');
        $duplicateMode = (string)($filters['duplicate'] ?? '');
        $duplicateSimilarity = max(50, min(100, (int)($filters['duplicate_similarity'] ?? self::DEFAULT_URL_SIMILARITY)));

        $sections = $this->loadSections($iblockId, $minDepth);
        if (!$sections) {
            return [];
        }

        $byCount = [];
        foreach ($sections as $id => $section) {
            if (!$this->matchesActiveFilter($section, $active)) {
                continue;
            }
            if (!$this->matchesRedirectFilter($section, $redirect)) {
                continue;
            }
            if (!$this->matchesWithoutProdFilter($section, $withoutProd)) {
                continue;
            }
            if (!$this->matchesNameFilter($section, $nameFilter)) {
                continue;
            }

            $count = (int)$section['ELEMENT_CNT'];
            $byCount[$count][] = (int)$id;
        }

        $ids = $this->resolveCountFilter($byCount, $countFilter);
        if (!$ids) {
            return [];
        }

        $subtreeProductsMap = $this->getSubtreeProductsMap($sections, $ids);

        if ($storefront !== '') {
            $ids = array_values(array_filter($ids, function ($id) use ($sections, $subtreeProductsMap, $storefront) {
                return isset($sections[$id])
                    && $this->matchesStorefrontFilter($sections[$id], $subtreeProductsMap, $storefront);
            }));
            if (!$ids) {
                return [];
            }
        }

        $duplicateLabels = [];
        $duplicateGroups = [];
        $duplicateMatches = [];
        if ($duplicateMode !== self::DUPLICATE_NONE) {
            $duplicateResult = $this->findDuplicateMatches(
                $sections,
                $ids,
                $duplicateMode,
                $duplicateSimilarity
            );
            $duplicateLabels = $duplicateResult['labels'];
            $duplicateGroups = $duplicateResult['groups'];
            $duplicateMatches = $duplicateResult['matches'];
            $ids = array_keys($duplicateLabels);
            if (!$ids) {
                return [];
            }
        }

        $rows = [];
        foreach ($ids as $id) {
            if (!isset($sections[$id])) {
                continue;
            }

            $section = $sections[$id];
            $ownCount = (int)$section['ELEMENT_CNT'];
            $subtreeCount = (int)($subtreeProductsMap[$id] ?? 0);

            $rows[] = [
                'id' => (int)$id,
                'iblock_id' => $iblockId,
                'depth' => (int)$section['DEPTH_LEVEL'],
                'count' => $ownCount,
                'include_sub_categories' => $this->includeSubsections,
                'subtree_count' => $subtreeCount,
                'storefront_label' => $this->getStorefrontLabel($ownCount, $subtreeCount),
                'active' => $section['ACTIVE'] === 'Y',
                'name' => (string)$section['NAME'],
                'code' => (string)$section['CODE'],
                'admin_url' => '/bitrix/admin/iblock_section_edit.php?IBLOCK_ID=' . $iblockId . '&ID=' . $id . '&lang=ru',
                'public_url' => (string)$section['SECTION_PAGE_URL'],
                'without_prod' => !empty($section['UF_WITHOUT_PROD']),
                'duplicate_label' => $duplicateLabels[$id] ?? '',
                'duplicate_group' => (int)($duplicateGroups[$id] ?? 0),
                'duplicate_matches' => $duplicateMatches[$id] ?? [],
                'sort_name' => $this->normalizeName($section['NAME']),
            ];
        }

        usort($rows, function ($a, $b) use ($duplicateMode) {
            if ($duplicateMode !== self::DUPLICATE_NONE) {
                $nameCmp = strcmp($a['sort_name'], $b['sort_name']);
                if ($nameCmp !== 0) {
                    return $nameCmp;
                }
            }

            if ($a['depth'] !== $b['depth']) {
                return $a['depth'] - $b['depth'];
            }

            return strcasecmp($a['name'], $b['name']);
        });

        return $rows;
    }

    /**
     * @param int $sectionId
     * @param bool $withoutProd
     * @return bool
     */
    public function setWithoutProd($sectionId, $withoutProd)
    {
        $sectionId = (int)$sectionId;
        if (!$sectionId) {
            return false;
        }

        return (bool)(new CIBlockSection())->Update($sectionId, ['UF_WITHOUT_PROD' => $withoutProd ? 1 : 0]);
    }

    /**
     * @param int $iblockId
     * @param int $minDepth
     * @return array<int, array>
     */
    protected function loadSections($iblockId, $minDepth)
    {
        $sections = [];
        $rs = CIBlockSection::GetList(
            ['DEPTH_LEVEL' => 'ASC', 'NAME' => 'ASC'],
            [
                'IBLOCK_ID' => $iblockId,
                '>=DEPTH_LEVEL' => $minDepth,
            ],
            false,
            [
                'ID',
                'IBLOCK_ID',
                'NAME',
                'CODE',
                'DEPTH_LEVEL',
                'ACTIVE',
                'LEFT_MARGIN',
                'RIGHT_MARGIN',
                'SECTION_PAGE_URL',
                'UF_WITHOUT_PROD',
            ]
        );

        while ($row = $rs->GetNext()) {
            $row['ELEMENT_CNT'] = 0;
            $sections[(int)$row['ID']] = $row;
        }

        if ($sections) {
            foreach ($this->loadDirectElementCounts($iblockId, array_keys($sections)) as $sectionId => $cnt) {
                if (isset($sections[$sectionId])) {
                    $sections[$sectionId]['ELEMENT_CNT'] = $cnt;
                }
            }
        }

        return $sections;
    }

    /**
     * One grouped query instead of CIBlockSection::GetList(..., true) per section.
     *
     * @param int $iblockId
     * @param int[] $sectionIds
     * @return array<int, int>
     */
    protected function loadDirectElementCounts($iblockId, array $sectionIds)
    {
        $iblockId = (int)$iblockId;
        if ($iblockId <= 0 || !$sectionIds) {
            return [];
        }

        global $DB;

        $needed = array_fill_keys(array_map('intval', $sectionIds), true);
        $counts = [];

        // Same join logic as CIBlockSection::GetList(..., true), one query for all sections.
        $sql = "
            SELECT SE.IBLOCK_SECTION_ID AS SECTION_ID, COUNT(DISTINCT SE.IBLOCK_ELEMENT_ID) AS CNT
            FROM b_iblock_section_element SE
            INNER JOIN b_iblock_element BE ON BE.ID = SE.IBLOCK_ELEMENT_ID
            WHERE BE.IBLOCK_ID = " . $iblockId . "
              AND BE.WF_STATUS_ID = 1
              AND (BE.WF_PARENT_ELEMENT_ID IS NULL OR BE.WF_PARENT_ELEMENT_ID = 0)
              AND BE.ACTIVE = 'Y'
              AND (BE.ACTIVE_TO >= " . $DB->CurrentTimeFunction() . " OR BE.ACTIVE_TO IS NULL)
              AND (BE.ACTIVE_FROM <= " . $DB->CurrentTimeFunction() . " OR BE.ACTIVE_FROM IS NULL)
            GROUP BY SE.IBLOCK_SECTION_ID
        ";

        $res = $DB->Query($sql);
        while ($row = $res->Fetch()) {
            $sectionId = (int)($row['SECTION_ID'] ?? 0);
            if ($sectionId > 0 && isset($needed[$sectionId])) {
                $counts[$sectionId] = (int)($row['CNT'] ?? 0);
            }
        }

        return $counts;
    }

    protected function matchesActiveFilter(array $section, $active)
    {
        if ($active === '' || $active === null) {
            return true;
        }

        $isActive = $section['ACTIVE'] === 'Y';

        if ((string)$active === '1') {
            return $isActive;
        }
        if ((string)$active === '0') {
            return !$isActive;
        }

        return true;
    }

    protected function matchesRedirectFilter(array $section, $redirect)
    {
        if ($redirect === '' || $redirect === null) {
            return true;
        }

        $code = (string)$section['CODE'];
        $isRedirect = (bool)preg_match('/-r$/', $code);

        if ((string)$redirect === '1') {
            return !$isRedirect;
        }
        if ((string)$redirect === '0') {
            return $isRedirect;
        }

        return true;
    }

    protected function matchesWithoutProdFilter(array $section, $withoutProd)
    {
        if ($withoutProd === '' || $withoutProd === null) {
            return true;
        }

        $flag = !empty($section['UF_WITHOUT_PROD']);

        if ((string)$withoutProd === '1') {
            return $flag;
        }
        if ((string)$withoutProd === '0') {
            return !$flag;
        }

        return true;
    }

    protected function matchesNameFilter(array $section, $nameFilter)
    {
        if ($nameFilter === '') {
            return true;
        }

        $name = (string)$section['NAME'];
        if ($name === '') {
            return false;
        }

        if (function_exists('mb_stripos')) {
            return mb_stripos($name, $nameFilter, 0, 'UTF-8') !== false;
        }

        return stripos($name, $nameFilter) !== false;
    }

    /**
     * @param array<int, array> $sections
     * @param int[] $sectionIds
     * @return array<int, int>
     */
    protected function getSubtreeProductsMap(array $sections, array $sectionIds)
    {
        if (!$sectionIds || !$sections) {
            return [];
        }

        $targetIds = [];
        foreach ($sectionIds as $id) {
            $id = (int)$id;
            if ($id > 0 && isset($sections[$id])) {
                $targetIds[$id] = true;
            }
        }

        if (!$targetIds) {
            return [];
        }

        $map = array_fill_keys(array_keys($targetIds), 0);

        $ordered = $sections;
        uasort($ordered, static function ($a, $b) {
            return (int)$a['LEFT_MARGIN'] <=> (int)$b['LEFT_MARGIN'];
        });

        $stack = [];

        foreach ($ordered as $section) {
            $id = (int)$section['ID'];
            $left = (int)$section['LEFT_MARGIN'];
            $cnt = (int)$section['ELEMENT_CNT'];

            while ($stack !== []) {
                $topId = $stack[count($stack) - 1];
                if ($left <= (int)$sections[$topId]['RIGHT_MARGIN']) {
                    break;
                }
                array_pop($stack);
            }

            foreach ($stack as $ancestorId) {
                $map[$ancestorId] += $cnt;
            }

            if (isset($targetIds[$id])) {
                $stack[] = $id;
            }
        }

        return $map;
    }

    protected function matchesStorefrontFilter(array $section, array $subtreeProductsMap, $storefront)
    {
        if ($storefront === '' || $storefront === null) {
            return true;
        }

        $id = (int)$section['ID'];
        $ownCount = (int)$section['ELEMENT_CNT'];
        $subtreeCount = (int)($subtreeProductsMap[$id] ?? 0);
        $visible = $this->isVisibleOnStorefront($ownCount, $subtreeCount);

        switch ($storefront) {
            case self::STOREFRONT_EMPTY:
                return !$visible;
            case self::STOREFRONT_FROM_SUB:
                return $ownCount === 0 && $this->includeSubsections && $subtreeCount > 0;
            case self::STOREFRONT_OWN:
                return $ownCount > 0;
            case self::STOREFRONT_ANY:
                return $visible;
        }

        return true;
    }

    protected function isVisibleOnStorefront($ownCount, $subtreeCount)
    {
        if ($ownCount > 0) {
            return true;
        }

        return $this->includeSubsections && $subtreeCount > 0;
    }

    protected function getStorefrontLabel($ownCount, $subtreeCount)
    {
        if ($ownCount > 0 && $this->includeSubsections && $subtreeCount > 0) {
            return 'Свои+подк.';
        }
        if ($ownCount > 0) {
            return 'Свои';
        }
        if ($this->includeSubsections && $subtreeCount > 0) {
            return 'Из подкат.';
        }

        return 'Пусто';
    }

    /**
     * @param array<int, int[]> $byCount
     * @param mixed $countFilter
     * @return int[]
     */
    protected function resolveCountFilter(array $byCount, $countFilter)
    {
        if ($countFilter === '' || $countFilter === null) {
            $ids = [];
            array_walk_recursive($byCount, function ($item) use (&$ids) {
                $ids[] = (int)$item;
            });

            return $ids;
        }

        if (!is_numeric($countFilter)) {
            return [];
        }

        $count = (int)$countFilter;

        return $byCount[$count] ?? [];
    }

    /**
     * @param array<int, array> $sections
     * @param int[] $ids
     * @param string $mode
     * @param int $similarityThreshold
     * @return array{labels: array<int,string>, groups: array<int,int>, matches: array<int,array<int,array{id:int,reason:string}>>}
     */
    protected function findDuplicateMatches(array $sections, array $ids, $mode, $similarityThreshold)
    {
        $ids = array_values(array_unique(array_map('intval', $ids)));
        $labels = [];
        $matches = [];
        $parent = [];

        foreach ($ids as $id) {
            $parent[$id] = $id;
        }

        $nameGroups = [];
        foreach ($ids as $id) {
            if (!isset($sections[$id])) {
                continue;
            }
            $nameKey = $this->normalizeName($sections[$id]['NAME']);
            if ($nameKey === '') {
                continue;
            }
            $nameGroups[$nameKey][] = $id;
        }

        $nameDuplicateMap = [];
        foreach ($nameGroups as $group) {
            if (count($group) < 2) {
                continue;
            }
            foreach ($group as $id) {
                $nameDuplicateMap[$id] = $group;
            }
        }

        $urlSimilarityMap = [];
        if ($mode === self::DUPLICATE_URL) {
            $urlSimilarityMap = $this->buildUrlSimilarityMap($sections, $ids, $similarityThreshold);
        }

        foreach ($ids as $id) {
            if (!isset($sections[$id])) {
                continue;
            }

            $rowMatches = [];

            if ($mode === self::DUPLICATE_NAME) {
                if (empty($nameDuplicateMap[$id])) {
                    continue;
                }
                foreach ($nameDuplicateMap[$id] as $otherId) {
                    if ($otherId === $id) {
                        continue;
                    }
                    $rowMatches[] = ['id' => $otherId, 'reason' => 'имя'];
                    $this->mergeDuplicateGroups($parent, $id, $otherId);
                }
            } elseif ($mode === self::DUPLICATE_URL) {
                if (empty($urlSimilarityMap[$id])) {
                    continue;
                }
                arsort($urlSimilarityMap[$id]);
                foreach ($urlSimilarityMap[$id] as $otherId => $percent) {
                    $rowMatches[] = [
                        'id' => $otherId,
                        'reason' => 'URL ' . round($percent) . '%',
                    ];
                    $this->mergeDuplicateGroups($parent, $id, $otherId);
                }
            } elseif ($mode === self::DUPLICATE_BOTH) {
                if (empty($nameDuplicateMap[$id])) {
                    continue;
                }
                $myUrl = $this->normalizeUrl($sections[$id]['CODE'] ?? '');
                foreach ($nameDuplicateMap[$id] as $otherId) {
                    if ($otherId === $id) {
                        continue;
                    }
                    $otherUrl = $this->normalizeUrl($sections[$otherId]['CODE'] ?? '');
                    $percent = $this->getUrlSimilarity($myUrl, $otherUrl);
                    if ($percent >= $similarityThreshold) {
                        $rowMatches[] = [
                            'id' => $otherId,
                            'reason' => 'имя+URL ' . round($percent) . '%',
                        ];
                        $this->mergeDuplicateGroups($parent, $id, $otherId);
                    }
                }
                if (!$rowMatches) {
                    continue;
                }
            } else {
                continue;
            }

            if ($rowMatches) {
                $labelParts = [];
                foreach ($rowMatches as $match) {
                    $labelParts[] = $this->formatDuplicateLabel($match['id'], $match['reason']);
                }
                $matches[$id] = $rowMatches;
                $labels[$id] = $this->truncateDuplicateLabels($labelParts);
            }
        }

        return [
            'labels' => $labels,
            'groups' => $this->normalizeDuplicateGroups($parent, array_keys($labels)),
            'matches' => $matches,
        ];
    }

    protected function mergeDuplicateGroups(array &$parent, $a, $b)
    {
        $rootA = $this->findDuplicateRoot($parent, $a);
        $rootB = $this->findDuplicateRoot($parent, $b);
        if ($rootA !== $rootB) {
            $parent[$rootB] = $rootA;
        }
    }

    protected function findDuplicateRoot(array &$parent, $id)
    {
        if (!isset($parent[$id])) {
            $parent[$id] = $id;
        }
        if ($parent[$id] !== $id) {
            $parent[$id] = $this->findDuplicateRoot($parent, $parent[$id]);
        }

        return $parent[$id];
    }

    protected function normalizeDuplicateGroups(array $parent, array $ids)
    {
        $clusters = [];
        foreach ($ids as $id) {
            $root = $this->findDuplicateRoot($parent, $id);
            $clusters[$root][] = $id;
        }

        $groups = [];
        $index = 1;
        foreach ($clusters as $members) {
            if (count($members) < 2) {
                continue;
            }
            foreach ($members as $id) {
                $groups[$id] = $index;
            }
            $index++;
        }

        return $groups;
    }

    protected function buildUrlSimilarityMap(array $sections, array $ids, $similarityThreshold)
    {
        $map = [];
        $urlById = [];

        foreach ($ids as $id) {
            if (!isset($sections[$id])) {
                continue;
            }
            $url = $this->normalizeUrl($sections[$id]['CODE'] ?? '');
            if ($url === '') {
                continue;
            }
            $urlById[$id] = $url;
        }

        if (!$urlById) {
            return $map;
        }

        $exactGroups = [];
        foreach ($urlById as $id => $url) {
            $exactGroups[$url][] = $id;
        }
        foreach ($exactGroups as $group) {
            if (count($group) < 2) {
                continue;
            }
            $this->storeUrlSimilarityPairs($map, $group, 100.0);
        }

        if ($similarityThreshold >= 100) {
            return $map;
        }

        $lenBuckets = [];
        foreach ($urlById as $id => $url) {
            $lenBuckets[strlen($url)][] = $id;
        }

        $lengths = array_keys($lenBuckets);
        sort($lengths, SORT_NUMERIC);

        foreach ($lengths as $idx => $lenA) {
            $this->compareUrlSimilarityBucket($map, $urlById, $lenBuckets[$lenA], $similarityThreshold);

            for ($j = $idx + 1; $j < count($lengths); $j++) {
                $lenB = $lengths[$j];
                if ($lenB - $lenA > max($lenA, $lenB) * 0.5) {
                    break;
                }
                $this->compareUrlSimilarityBucketsCross(
                    $map,
                    $urlById,
                    $lenBuckets[$lenA],
                    $lenBuckets[$lenB],
                    $similarityThreshold
                );
            }
        }

        return $map;
    }

    protected function compareUrlSimilarityBucket(array &$map, array $urlById, array $ids, $similarityThreshold)
    {
        if (count($ids) < 2) {
            return;
        }

        $prefixBuckets = [];
        foreach ($ids as $id) {
            $url = $urlById[$id];
            $prefix = substr($url, 0, min(3, strlen($url)));
            $prefixBuckets[$prefix][] = $id;
        }

        foreach ($prefixBuckets as $group) {
            $this->compareUrlSimilarityGroup($map, $urlById, $group, $similarityThreshold);
        }
    }

    protected function compareUrlSimilarityBucketsCross(
        array &$map,
        array $urlById,
        array $idsA,
        array $idsB,
        $similarityThreshold
    ) {
        if (!$idsA || !$idsB) {
            return;
        }

        $prefixBucketsA = [];
        foreach ($idsA as $id) {
            $url = $urlById[$id];
            $prefix = substr($url, 0, min(3, strlen($url)));
            $prefixBucketsA[$prefix][] = $id;
        }

        foreach ($idsB as $idB) {
            $urlB = $urlById[$idB];
            $prefix = substr($urlB, 0, min(3, strlen($urlB)));
            if (empty($prefixBucketsA[$prefix])) {
                continue;
            }
            foreach ($prefixBucketsA[$prefix] as $idA) {
                if ($idA === $idB || isset($map[$idA][$idB])) {
                    continue;
                }
                $percent = $this->getUrlSimilarity($urlById[$idA], $urlB);
                if ($percent >= $similarityThreshold) {
                    $map[$idA][$idB] = $percent;
                    $map[$idB][$idA] = $percent;
                }
            }
        }
    }

    protected function compareUrlSimilarityGroup(array &$map, array $urlById, array $group, $similarityThreshold)
    {
        $count = count($group);
        if ($count < 2) {
            return;
        }

        for ($i = 0; $i < $count; $i++) {
            $idA = $group[$i];
            for ($j = $i + 1; $j < $count; $j++) {
                $idB = $group[$j];
                if (isset($map[$idA][$idB])) {
                    continue;
                }
                $percent = $this->getUrlSimilarity($urlById[$idA], $urlById[$idB]);
                if ($percent >= $similarityThreshold) {
                    $map[$idA][$idB] = $percent;
                    $map[$idB][$idA] = $percent;
                }
            }
        }
    }

    protected function storeUrlSimilarityPairs(array &$map, array $group, $percent)
    {
        $count = count($group);
        for ($i = 0; $i < $count; $i++) {
            $idA = $group[$i];
            for ($j = $i + 1; $j < $count; $j++) {
                $idB = $group[$j];
                $map[$idA][$idB] = $percent;
                $map[$idB][$idA] = $percent;
            }
        }
    }

    protected function getUrlSimilarity($urlA, $urlB)
    {
        if ($urlA === '' || $urlB === '') {
            return 0.0;
        }
        if ($urlA === $urlB) {
            return 100.0;
        }

        $lenA = strlen($urlA);
        $lenB = strlen($urlB);
        if (abs($lenA - $lenB) > max($lenA, $lenB) * 0.5) {
            return 0.0;
        }

        $percent = 0.0;
        similar_text($urlA, $urlB, $percent);

        return (float)$percent;
    }

    protected function normalizeName($name)
    {
        $name = trim((string)$name);
        if ($name === '') {
            return '';
        }

        if (function_exists('mb_strtolower')) {
            $name = mb_strtolower($name, 'UTF-8');
        } else {
            $name = strtolower($name);
        }

        return preg_replace('/\s+/u', ' ', $name);
    }

    protected function normalizeUrl($url)
    {
        return trim(strtolower((string)$url));
    }

    protected function formatDuplicateLabel($sectionId, $reason)
    {
        return (int)$sectionId . ' (' . $reason . ')';
    }

    protected function truncateDuplicateLabels(array $labels)
    {
        $labels = array_values(array_unique($labels));
        if (count($labels) > 5) {
            return implode(', ', array_slice($labels, 0, 5)) . '…';
        }

        return implode(', ', $labels);
    }
}
