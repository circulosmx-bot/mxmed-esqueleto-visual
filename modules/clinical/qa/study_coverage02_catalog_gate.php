<?php
declare(strict_types=1);
require_once __DIR__.'/../../../api/_lib/clinical_study_contract.php';
require_once __DIR__.'/../../../api/_lib/clinical_study_catalog_read.php';

$root = dirname(__DIR__, 3);
$pdo = new PDO('mysql:host=localhost;dbname=mxmed_director_review_lon07c;charset=utf8mb4', 'root', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$rows = $pdo->query('SELECT study_type_key, display_name_es, aliases_json, category_key FROM clinical_study_types WHERE is_active=1')->fetchAll(PDO::FETCH_ASSOC);
$routing = json_decode(file_get_contents($root.'/modules/clinical/catalog/study_order_routing_v1.json'), true, 512, JSON_THROW_ON_ERROR);
$featured = json_decode(file_get_contents($root.'/modules/clinical/catalog/study_featured_navigation_v1.json'), true, 512, JSON_THROW_ON_ERROR);
$search = clinical_study_search_authority();
$byKey = array_column($rows, null, 'study_type_key');
$expectedCount = count($routing['studies']);
if (count($rows) !== $expectedCount || count($byKey) !== $expectedCount || count($search['by_key']) !== $expectedCount) throw new RuntimeException('CATALOG_COUNT_MISMATCH');
$families = []; $leaves = [];
foreach ($featured['leaves'] as $leaf => $entry) {
    if (!$entry['study_keys']) throw new RuntimeException('EMPTY_LEAF '.$leaf);
    foreach ($entry['study_keys'] as $key) {
        if (!isset($byKey[$key])) throw new RuntimeException('LEAF_NONACTIVE '.$key);
        $families[$key][$entry['root_family']] = true;
        $leaves[$key][] = $leaf;
    }
}
$misses = []; $termsChecked = 0; $familyCounts = [];
$canonicalNames = []; $exactIdentities = [];
foreach ($byKey as $key => $row) {
    if (!isset($routing['groups'][$routing['studies'][$key] ?? ''])) throw new RuntimeException('UNROUTED '.$key);
    if (count($families[$key] ?? []) !== 1) throw new RuntimeException('FAMILY_UNREACHABLE_OR_AMBIGUOUS '.$key);
    $family = array_key_first($families[$key]);
    $familyCounts[$family] = ($familyCounts[$family] ?? 0) + 1;
    $authority = $search['by_key'][$key] ?? null;
    if ($authority === null || $authority['canonical_display_name'] !== $row['display_name_es']) throw new RuntimeException('SEARCH_IDENTITY '.$key);
    $canonicalName = clinical_study_search_normalize($row['display_name_es']);
    if (isset($canonicalNames[$canonicalName])) throw new RuntimeException('DUPLICATE_CANONICAL_NAME '.$key.' '.$canonicalNames[$canonicalName]);
    $canonicalNames[$canonicalName] = $key;
    foreach (array_merge([$row['display_name_es']], $authority['abbreviations'], $authority['equivalent_aliases']) as $identity) {
        $normalized = clinical_study_search_normalize($identity);
        if (isset($exactIdentities[$normalized]) && $exactIdentities[$normalized] !== $key) throw new RuntimeException('CROSS_STUDY_EXACT_IDENTITY_COLLISION '.$key.' '.$exactIdentities[$normalized]);
        $exactIdentities[$normalized] = $key;
    }
    $terms = array_merge([$row['display_name_es']], json_decode($row['aliases_json'] ?: '[]', true, 512, JSON_THROW_ON_ERROR), $authority['abbreviations'], $authority['equivalent_aliases'], $authority['discovery_terms']);
    if ($authority['common_display_name'] !== null) $terms[] = $authority['common_display_name'];
    foreach (array_unique($terms) as $term) {
        $termsChecked++;
        $found = false;
        for ($offset = 0; ; $offset += 100) {
            $page = clinical_study_catalog_read($pdo, ['search' => $term, 'limit' => '100', 'offset' => (string)$offset]);
            foreach ($page['items'] as $item) if ($item['study_type_key'] === $key) $found = true;
            if ($found || !$page['has_more']) break;
        }
        if (!$found) $misses[] = [$key, $term];
    }
}
$representative = [
    'hem'=>'cbc', 'gli'=>'hba1c', 'glu'=>'glucose', 'hba'=>'hba1c', 'a1c'=>'hba1c',
    'psa'=>'psa_total', 'ant pro'=>'psa_total', 'tac'=>'ct_chest', 'rmn'=>'mr_brain', 'eco'=>'echo_tte',
    'periapical'=>'dental_periapical_xray', 'bitewing'=>'dental_bitewing_xray', 'cbct'=>'dental_cbct',
    'colpos'=>'colposcopy_diagnostic', 'cisto'=>'cystoscopy_diagnostic', 'histero'=>'hysteroscopy_diagnostic',
    'holter'=>'holter', 'mapa'=>'abpm_mapa', 'baep'=>'evoked_auditory_baep',
    'manometria'=>'esophageal_manometry', 'phmetria'=>'esophageal_ph_monitoring',
];
foreach ($representative as $query => $key) {
    $found = false;
    for ($offset = 0; ; $offset += 100) {
        $page = clinical_study_catalog_read($pdo, ['search' => $query, 'limit' => '100', 'offset' => (string)$offset]);
        foreach ($page['items'] as $item) if ($item['study_type_key'] === $key) $found = true;
        if ($found || !$page['has_more']) break;
    }
    if (!$found) throw new RuntimeException('REPRESENTATIVE_SEARCH_MISS '.$query.' '.$key);
}
ksort($familyCounts);
$authorities = [
    'specimen' => [$root.'/modules/clinical/catalog/study_specimen_requirements_v1.json', 'studies'],
    'pathology' => [$root.'/modules/clinical/catalog/pathology_order_parameters_v1.json', 'rules'],
    'imaging' => [$root.'/modules/clinical/catalog/imaging_order_parameters_v1.json', 'rules'],
    'functional' => [$root.'/modules/clinical/catalog/functional_order_parameters_v1.json', 'rules'],
    'dental' => [$root.'/assets/data/clinical/dental-study-location-policies-v1.json', 'study_policies'],
];
$authorityCounts = [];
foreach ($authorities as $name => [$path, $field]) {
    $definition = json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
    if (($definition['version'] ?? $definition['contract_version'] ?? null) !== 1) throw new RuntimeException('INVALID_AUTHORITY_VERSION '.$name);
    foreach (array_keys($definition[$field]) as $key) if (!isset($byKey[$key])) throw new RuntimeException('INACTIVE_AUTHORITY_BINDING '.$name.' '.$key);
    $authorityCounts[$name] = count($definition[$field]);
}
$dentalV2 = json_decode(file_get_contents($root.'/assets/data/clinical/dental-location-authority-v2.json'), true, 512, JSON_THROW_ON_ERROR);
if ($dentalV2['contract_version'] !== 2 || $dentalV2['numbering_system'] !== 'FDI_ISO_3950') throw new RuntimeException('DENTAL_LOCATION_VERSION');
$panels = json_decode(file_get_contents($root.'/modules/clinical/catalog/lab_panel_definitions_v1.json'), true, 512, JSON_THROW_ON_ERROR);
$presets = json_decode(file_get_contents($root.'/modules/clinical/catalog/lab_order_presets_v1.json'), true, 512, JSON_THROW_ON_ERROR);
if (array_diff(['panel_quimica_6', 'arterial_blood_gas'], array_keys($panels['panels'])) || count($presets['presets']) !== 3) throw new RuntimeException('PANEL_PRESET_AUTHORITY');
foreach ($presets['presets'] as $key => $preset) {
    if (isset($byKey[$key])) throw new RuntimeException('PRESET_CANONICAL_IDENTITY '.$key);
    foreach ($preset['component_study_keys'] as $component) if (!isset($byKey[$component])) throw new RuntimeException('PRESET_INVALID_COMPONENT '.$component);
}
echo 'ACTIVE_STUDIES='.count($rows)."\n";
echo 'FAMILY_COUNTS='.json_encode($familyCounts, JSON_UNESCAPED_UNICODE)."\n";
echo 'PARAMETER_AUTHORITY_COUNTS='.json_encode($authorityCounts)."\n";
echo 'DISCOVERY_TERMS_CHECKED='.$termsChecked."\n";
echo 'REPRESENTATIVE_SEARCH_QUERIES='.count($representative)."\n";
echo 'UNSEARCHABLE_TERMS='.json_encode($misses, JSON_UNESCAPED_UNICODE)."\n";
if ($misses) throw new RuntimeException('SEARCH_COVERAGE_FAILURE');
echo "CATALOG_ACCOUNTING_AND_SEARCH=PASS\n";
