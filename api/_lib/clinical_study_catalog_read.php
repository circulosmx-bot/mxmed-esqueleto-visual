<?php
declare(strict_types=1);

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
    if ($search !== '') {
        $term = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $search).'%';
        $where[] = "(display_name_es LIKE ? ESCAPE '!' OR study_type_key LIKE ? ESCAPE '!' OR CAST(aliases_json AS CHAR) LIKE ? ESCAPE '!')";
        array_push($params, $term, $term, $term);
    }
    $sql = 'SELECT study_type_id,study_type_key,display_name_es,category_key,aliases_json '
        .'FROM clinical_study_types WHERE '.implode(' AND ', $where)
        .' ORDER BY display_name_es,study_type_id LIMIT '.($limit + 1).' OFFSET '.$offset;
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
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
        ];
    }
    return ['items'=>$items,'has_more'=>$hasMore,'offset'=>$offset,'limit'=>$limit];
}
