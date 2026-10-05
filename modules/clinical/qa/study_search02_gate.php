<?php
declare(strict_types=1);
require_once __DIR__.'/../../../api/_lib/clinical_study_contract.php';
require_once __DIR__.'/../../../api/_lib/clinical_study_catalog_read.php';

function search02_check(bool $condition, string $label): void
{
    if (!$condition) throw new RuntimeException('FAIL '.$label);
    echo $label."=PASS\n";
}
function search02_rows(string $path): array
{
    $file = fopen($path, 'rb');
    if ($file === false) throw new RuntimeException('QA_FILE_UNAVAILABLE');
    $header = fgetcsv($file, null, ',', '"', '');
    $rows = [];
    while (($values = fgetcsv($file, null, ',', '"', '')) !== false) $rows[] = array_combine($header, $values);
    fclose($file);
    return $rows;
}
function search02_all(PDO $pdo, string $query): array
{
    $keys = [];
    for ($offset = 0; ; $offset += 100) {
        $page = clinical_study_catalog_read($pdo, ['search' => $query, 'limit' => '100', 'offset' => (string)$offset]);
        foreach ($page['items'] as $item) $keys[] = $item['study_type_key'];
        if (!$page['has_more']) break;
    }
    return $keys;
}

$root = dirname(__DIR__, 3);
$matrix = search02_rows($root.'/docs/clinical/STUDY_SEARCH01_AUTHORITY_MATRIX.csv');
$expectations = search02_rows($root.'/docs/clinical/STUDY_SEARCH01_QUERY_EXPECTATIONS.csv');
$authority = clinical_study_search_authority();
search02_check($authority['config']['version'] === 1 && count($authority['by_key']) === 289, 'QA_AUTHORITY_VERSION_AND_COUNT');
$approvedAdditiveKeys=array_fill_keys(['abpm_mapa','audiometry_speech','ecg_12lead','eeg_routine','emg_ncs',
    'evoked_auditory_baep','evoked_ssep','evoked_visual','full_pft','holter','otoacoustic_emissions',
    'spirometry','stress_test','vng'],true);
search02_check(count($matrix) === 252 && count($expectations) === 47, 'QA_SOURCE_AUDIT_COUNTS');
$pdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
$pdo->exec('CREATE TABLE clinical_study_types (study_type_id INTEGER PRIMARY KEY, study_type_key TEXT, display_name_es TEXT, category_key TEXT, aliases_json TEXT, is_active INTEGER)');
$insert = $pdo->prepare('INSERT INTO clinical_study_types VALUES (?,?,?,?,?,1)');
$seen = [];
foreach ($matrix as $index => $row) {
    $key = $row['study_key'];
    if (isset($seen[$key]) || !isset($authority['by_key'][$key])) throw new RuntimeException('QA_AUTHORITY_KEY '.$key);
    $seen[$key] = true;
    $entry = $authority['by_key'][$key];
    $expectedDiscovery=json_decode($row['discovery_terms'], true, 512, JSON_THROW_ON_ERROR);
    if ($key === 'dental_cbct') $expectedDiscovery=array_merge($expectedDiscovery,['ATM','articulación temporomandibular']);
    if ($key === 'dental_cephalometric_xray') $expectedDiscovery[]='Cefalometría';
    if ($key === 'egd_eda_base') $expectedDiscovery=array_merge($expectedDiscovery,['panendoscopia','gastroscopia','endoscopia alta']);
    $baselineAbbreviations=array_values(array_unique(array_merge(json_decode($row['abbreviations'], true, 512, JSON_THROW_ON_ERROR), json_decode($row['proposed_abbreviations'], true, 512, JSON_THROW_ON_ERROR))));
    $baselineAliases=array_values(array_unique(array_merge(json_decode($row['equivalent_aliases'], true, 512, JSON_THROW_ON_ERROR), json_decode($row['proposed_equivalent_aliases'], true, 512, JSON_THROW_ON_ERROR))));
    $observedAliases=array_values(array_diff($entry['equivalent_aliases'], $key === 'cyto_pap' ? ['Papanicolau'] : []));
    if ($entry['canonical_display_name'] !== $row['canonical_display_name']
        || $entry['common_display_name'] !== ($row['common_display_name'] ?: null)
        || array_diff($baselineAbbreviations,$entry['abbreviations'])
        || array_diff($baselineAliases,$observedAliases)
        || array_diff($expectedDiscovery,$entry['discovery_terms'])
        || (!isset($approvedAdditiveKeys[$key]) && ($entry['abbreviations']!==$baselineAbbreviations
            || $observedAliases!==$baselineAliases || $entry['discovery_terms']!==$expectedDiscovery))) {
        throw new RuntimeException('QA_AUTHORITY_MATRIX_MISMATCH '.$key);
    }
    $insert->execute([$index + 1, $key, $row['canonical_display_name'], $row['category'], $row['current_aliases']]);
}
search02_check(count($seen) === 252, 'QA_ALL_ACTIVE_STUDIES_COVERED');
$exact = []; $abbreviations = []; $prefixes = []; $discovery = []; $commonCount = 0;
foreach ($authority['by_key'] as $key => $entry) {
    if (!isset($seen[$key])) continue; // SEARCH02's original 252-row fixture remains a historical regression set.
    $identity = array_merge([$entry['canonical_display_name']], $entry['abbreviations'], $entry['equivalent_aliases']);
    if ($entry['common_display_name'] !== null) {
        $commonCount++;
        $prefixTerms = array_merge($identity, [$entry['common_display_name']]);
    } else $prefixTerms = $identity;
    foreach ($identity as $term) $exact[clinical_study_search_normalize($term)][$key] = true;
    foreach ($entry['abbreviations'] as $term) $abbreviations[clinical_study_search_normalize($term)][$key] = true;
    foreach ($entry['discovery_terms'] as $term) $discovery[clinical_study_search_normalize($term)][$key] = true;
    foreach ($prefixTerms as $term) {
        foreach (explode(' ', clinical_study_search_normalize($term)) as $token) {
            if (strlen($token) >= 3) $prefixes[substr($token, 0, 3)][$key] = true;
        }
    }
}
$collisionCount = static fn(array $values): int => count(array_filter($values, static fn(array $keys): bool => count($keys) > 1));
search02_check($collisionCount($exact) === 0 && $collisionCount($abbreviations) === 0, 'QA_EXACT_IDENTITY_COLLISIONS_ZERO');
$allExact=[];
foreach ($authority['by_key'] as $key=>$entry) foreach (array_merge([$entry['canonical_display_name']],$entry['abbreviations'],$entry['equivalent_aliases']) as $term)
    $allExact[clinical_study_search_normalize($term)][$key]=true;
search02_check($collisionCount($allExact)===0,'QA_ALL_289_EXACT_IDENTITY_COLLISIONS_ZERO');
search02_check($collisionCount($prefixes) >= 147 && $collisionCount($discovery) >= 6, 'QA_EXPECTED_DISCOVERY_COLLISIONS');
search02_check($commonCount === 46, 'QA_COMMON_DISPLAY_COUNT');
$passed = 0;
foreach ($expectations as $case) {
    $expected = json_decode($case['expected_result_keys'], true, 512, JSON_THROW_ON_ERROR);
    $actual = search02_all($pdo, $case['query']);
    if ($actual !== $expected) {
        throw new RuntimeException('QA_QUERY_EXPECTATION '.$case['query'].' expected='.json_encode($expected).' actual='.json_encode($actual));
    }
    $passed++;
}
search02_check($passed === 47, 'QA_47_QUERY_EXPECTATIONS');
foreach ([['antigeno','antígeno'], ['proteina','proteína'], ['glicosilada','glicósilada'], ['ant.','ant'], ['PRO.','pro'], ['anti-ccp','anti ccp'], ['ca-125','ca 125'], ['ca-19-9','ca 19 9'], ['t4-l','t4 l']] as [$left, $right]) {
    search02_check(search02_all($pdo, $left) === search02_all($pdo, $right), 'QA_NORMALIZATION_'.clinical_study_search_normalize($left));
}
search02_check(search02_all($pdo, 'BH') === ['cbc'], 'QA_EXISTING_SHORT_ABBREVIATION');
search02_check(search02_all($pdo, 'ct') === ['ct_chest','ct_head','ct_uro','ct_abdomen_pelvis'], 'QA_SHORT_CT_SCOPED');
search02_check(!in_array('crp_hs', search02_all($pdo, 'us'), true), 'QA_SHORT_US_NO_PCR_US');
search02_check(search02_all($pdo, 'ca 125') === ['ca125_serum'], 'QA_NUMERIC_CA125');
search02_check(search02_all($pdo, 'ca 19 9') === ['ca199_serum'], 'QA_NUMERIC_CA199');
search02_check(search02_all($pdo, 'Cristales en líquido sinovial') === ['synovial_crystals'], 'QA_CANONICAL_WITH_CONNECTOR');
search02_check(search02_all($pdo, 'Cristales en líq') === ['synovial_crystals'], 'QA_PARTIAL_WITH_CONNECTOR');
search02_check(search02_all($pdo, 'TC de abdomen') === ['ct_abdomen_pelvis'], 'QA_QUALIFIED_SHORT_MODALITY');
search02_check(search02_all($pdo, 'co') === [], 'QA_NO_UNLISTED_TWO_LETTER_PREFIX');
foreach (['EKG 12 derivaciones' => 'ecg_12lead', 'Holter' => 'holter', 'MAPA' => 'abpm_mapa',
    'EEG' => 'eeg_routine', 'EMG + VCN' => 'emg_ncs'] as $term => $key) {
    search02_check(in_array($key, search02_all($pdo, $term), true), 'QA_FUNCTIONAL_'.clinical_study_search_normalize($term));
}
foreach (['Esofagogastroduodenoscopia' => 'egd_eda_base', 'colonoscopia' => 'colonoscopy_base',
    'broncoscopia' => 'bronchoscopy_base', 'laringoscopia' => 'laryngoscopy_base'] as $term => $key) {
    search02_check(in_array($key, search02_all($pdo, $term), true), 'QA_PROCEDURE_'.clinical_study_search_normalize($term));
}
foreach (['ortopantomografía' => 'dental_panoramic_xray', 'CBCT' => 'dental_cbct',
    'Cone Beam' => 'dental_cbct', 'cefalométrica' => 'dental_cephalometric_xray'] as $term => $key) {
    search02_check(in_array($key, search02_all($pdo, $term), true), 'QA_DENTAL_'.clinical_study_search_normalize($term));
}
search02_check(search02_all($pdo, 'USG mama') === ['breast_us']
    && search02_all($pdo, 'ecografía de mama') === ['breast_us'], 'QA_IMAGING_US_TERMS');
search02_check(search02_all($pdo, 'xyz-unlisted-study') === [], 'QA_NO_FUZZY_GUESS');
search02_check(clinical_study_catalog_read($pdo, ['search' => 'PSA'])['search_authority_version'] === 1, 'QA_SERVER_VERSION_EXPOSED');
$pdo->exec("UPDATE clinical_study_types SET is_active=0 WHERE study_type_key='hba1c'");
search02_check(!in_array('hba1c', search02_all($pdo, 'gli'), true), 'QA_INACTIVE_CANNOT_SURFACE');
echo "STUDY_SEARCH02_GATE=PASS\n";
