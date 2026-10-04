<?php
declare(strict_types=1);
require_once __DIR__.'/clinical_study_search.php';

/** TAX03B: read-only, active catalog projection for the future selector. */
function clinical_study_catalog_read(PDO $pdo, array $query): array
{
    if (isset($query['category']) && !is_string($query['category'])) throw new InvalidArgumentException('STUDY_CATEGORY_INVALID');
    if (isset($query['search']) && !is_string($query['search'])) throw new InvalidArgumentException('STUDY_SEARCH_INVALID');
    $category = trim((string)($query['category'] ?? ''));
    if ($category !== '' && !in_array($category, clinical_study_categories(), true)) {
        throw new InvalidArgumentException('STUDY_CATEGORY_INVALID');
    }
    $search = trim((string)($query['search'] ?? ''));
    if (mb_strlen($search) > 100) throw new InvalidArgumentException('STUDY_SEARCH_INVALID');
    if ((isset($query['limit']) && !is_scalar($query['limit']))
        || (isset($query['offset']) && !is_scalar($query['offset']))) throw new InvalidArgumentException('STUDY_PAGE_INVALID');
    $limitRaw = (string)($query['limit'] ?? '50');
    $offsetRaw = (string)($query['offset'] ?? '0');
    if (!ctype_digit($limitRaw) || !ctype_digit($offsetRaw)
        || (int)$limitRaw < 1 || (int)$limitRaw > 100 || (int)$offsetRaw > 10000) {
        throw new InvalidArgumentException('STUDY_PAGE_INVALID');
    }
    $limit = (int)$limitRaw;
    $offset = (int)$offsetRaw;
    $where = ['is_active=1'];
    $params = [];
    if ($category !== '') {
        $where[] = 'category_key=?';
        $params[] = $category;
    }
    $sql = 'SELECT study_type_id,study_type_key,display_name_es,category_key,aliases_json '
        .'FROM clinical_study_types WHERE '.implode(' AND ', $where)
        .' ORDER BY display_name_es,study_type_id';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $authority = clinical_study_search_authority();
    if ($search !== '') $rows = clinical_study_search_ranked_rows($rows, $search, $authority);
    $rows = array_slice($rows, $offset, $limit + 1);
    $hasMore = count($rows) > $limit;
    if ($hasMore) array_pop($rows);
    $items = [];
    foreach ($rows as $row) {
        $items[] = [
            'study_type_id' => (int)$row['study_type_id'],
            'study_type_key' => (string)$row['study_type_key'],
            'display_name_es' => (string)$row['display_name_es'],
            'category_key' => (string)$row['category_key'],
            'category_label_es' => clinical_study_category_labels_es()[(string)$row['category_key']],
            'aliases' => json_decode((string)$row['aliases_json'], true, 512, JSON_THROW_ON_ERROR),
            'common_display_name' => $authority['by_key'][(string)$row['study_type_key']]['common_display_name'] ?? null,
        ];
    }
    $counts=$pdo->query('SELECT category_key,COUNT(*) AS active_count FROM clinical_study_types WHERE is_active=1 GROUP BY category_key')->fetchAll(PDO::FETCH_KEY_PAIR);
    $categories=[];
    foreach(clinical_study_categories() as $key){
        $categories[]=['category_key'=>$key,'label_es'=>clinical_study_category_labels_es()[$key],
            'active_count'=>(int)($counts[$key]??0)];
    }
    return ['items'=>$items,'has_more'=>$hasMore,'offset'=>$offset,'limit'=>$limit,'categories'=>$categories,
        'search_authority_version'=>$authority['config']['version']];
}
