<?php
declare(strict_types=1);
require_once __DIR__.'/../../../api/_lib/clinical_study_contract.php';

$check = static function (bool $ok, string $name): void {
    if (!$ok) throw new RuntimeException($name);
    echo $name."=PASS\n";
};
$reject = static function (callable $action, string $error) use ($check): void {
    try { $action(); } catch (InvalidArgumentException $e) {
        $check($e->getMessage() === $error, 'QA_REJECT_'.$error);
        return;
    }
    throw new RuntimeException('Expected '.$error);
};
$authority = clinical_pathology_authority();
$keys = ['histopath_biopsy','histopath_resection','cyto_fna','cyto_bronchial_brushing',
    'cyto_bronchial_washing','ihc_single_marker','ihc_breast_profile','histochemical_special_stain',
    'if_renal','if_skin','pathology_outside_review'];
$check($authority['version'] === 1 && array_keys($authority['rules']) === $keys
    && count(array_unique(array_keys($authority['material_classes']))) === 4
    && count(array_unique(array_keys($authority['ihc_marker_authority']['markers']))) === 4
    && count(array_unique(array_keys($authority['special_stain_authority']['stains']))) === 3
    && $authority['breast_ihc_profile']['components'] === ['ER','PR','HER2','KI67'], 'QA_AUTHORITY');
foreach ($authority['rules'] as $key => $rule) {
    $check(isset($authority['specimen_contexts'][$rule['context']])
        && count($rule['materials']) > 0
        && count(array_diff($rule['materials'], array_keys($authority['material_classes']))) === 0
        && $rule['max_specimens'] >= 1 && $rule['max_specimens'] <= 10,
        'QA_RULE_'.strtoupper($key));
}
$check(clinical_specimen_authority()['version'] === 1, 'QA_CAT03B_V1_UNCHANGED');

$s = static fn(string $material, string $site = '', array $extra = []): array =>
    ['material_key'=>$material, ...($site === '' ? [] : ['anatomic_site_text'=>$site]), ...$extra];
$p = static fn(array $specimens, array $extra = []): array => ['version'=>1,'specimens'=>$specimens,...$extra];
$cases = [
    'histopath_biopsy'=>$p([$s('TISSUE','Lesión mamaria A',['anatomic_site_key'=>'BREAST','laterality'=>'RIGHT']),
        $s('TISSUE','Lesión mamaria B',['anatomic_site_key'=>'BREAST','laterality'=>'RIGHT'])]),
    'histopath_resection'=>$p([$s('TISSUE','Colon ascendente',['anatomic_site_key'=>'GI_TRACT','description'=>'Pieza de hemicolectomía'])]),
    'cyto_fna'=>$p([$s('CYTOLOGY_SPECIMEN','Nódulo tiroideo',['anatomic_site_key'=>'THYROID'])]),
    'cyto_bronchial_brushing'=>$p([$s('CYTOLOGY_SPECIMEN','Bronquio derecho')]),
    'cyto_bronchial_washing'=>$p([$s('CYTOLOGY_SPECIMEN','Bronquio derecho')]),
    'ihc_single_marker'=>$p([$s('PARAFFIN_BLOCK','Mama derecha',['anatomic_site_key'=>'BREAST'])],['marker_key'=>'ER']),
    'ihc_breast_profile'=>$p([$s('PARAFFIN_BLOCK','Mama derecha')],['profile_version'=>1]),
    'histochemical_special_stain'=>$p([$s('TISSUE','Riñón',['anatomic_site_key'=>'KIDNEY'])],['stain_key'=>'PAS']),
    'if_renal'=>$p([$s('TISSUE','Riñón izquierdo',['laterality'=>'LEFT'])]),
    'if_skin'=>$p([$s('TISSUE','Piel antebrazo')]),
    'pathology_outside_review'=>$p([$s('GLASS_SLIDE','Mama derecha'),$s('PARAFFIN_BLOCK','Mama derecha')],
        ['source_institution_name'=>'Hospital de origen','prior_report_status'=>'PENDING']),
];
foreach ($cases as $key => $raw) {
    $normalized = clinical_pathology_validate($key, $raw);
    $check(is_array($normalized) && count($normalized['specimens']) === count($raw['specimens'])
        && is_string(clinical_pathology_summary($normalized)), 'QA_VALID_'.strtoupper($key));
}
$check($cases['histopath_biopsy']['specimens'][0]['anatomic_site_text']
    !== $cases['histopath_biopsy']['specimens'][1]['anatomic_site_text'], 'QA_TWO_LESIONS_SEPARATE');
$check(clinical_pathology_validate('cyto_bronchial_brushing',$cases['cyto_bronchial_brushing'])['specimens'][0]['anatomic_site_key']==='BRONCHUS'
    && clinical_pathology_validate('cyto_bronchial_washing',$cases['cyto_bronchial_washing'])['specimens'][0]['anatomic_site_key']==='BRONCHUS', 'QA_BRONCHIAL_IDENTITIES_DISTINCT');

$bad=$cases['histopath_biopsy'];unset($bad['specimens'][0]['anatomic_site_text']);
$reject(static fn()=>clinical_pathology_validate('histopath_biopsy',$bad),'PATHOLOGY_SITE_REQUIRED');
$bad=$cases['ihc_single_marker'];unset($bad['marker_key']);
$reject(static fn()=>clinical_pathology_validate('ihc_single_marker',$bad),'PATHOLOGY_MARKER_INVALID');
$bad=$cases['ihc_single_marker'];$bad['marker_key']='UNKNOWN';
$reject(static fn()=>clinical_pathology_validate('ihc_single_marker',$bad),'PATHOLOGY_MARKER_INVALID');
$bad=$cases['ihc_single_marker'];$bad['specimens'][0]['material_key']='GLASS_SLIDE';
$reject(static fn()=>clinical_pathology_validate('ihc_single_marker',$bad),'PATHOLOGY_MATERIAL_INVALID');
$bad=$cases['histochemical_special_stain'];unset($bad['stain_key']);
$reject(static fn()=>clinical_pathology_validate('histochemical_special_stain',$bad),'PATHOLOGY_STAIN_INVALID');
$bad=$cases['if_renal'];$bad['specimens'][0]['laterality']='SIDEWAYS';
$reject(static fn()=>clinical_pathology_validate('if_renal',$bad),'PATHOLOGY_LATERALITY_INVALID');
$bad=$cases['if_renal'];$bad['specimens'][0]['laterality']='MIDLINE';
$reject(static fn()=>clinical_pathology_validate('if_renal',$bad),'PATHOLOGY_LATERALITY_SITE_CONFLICT');
$bad=$cases['if_renal'];$bad['specimens'][0]['laterality']='UNSPECIFIED_IF_ALLOWED';
$reject(static fn()=>clinical_pathology_validate('if_renal',$bad),'PATHOLOGY_LATERALITY_REASON_REQUIRED');
$bad['specimens'][0]['laterality_unspecified_reason']='No documentada en solicitud externa';
$check(clinical_pathology_validate('if_renal',$bad)['specimens'][0]['laterality_unspecified_reason']
    ==='No documentada en solicitud externa','QA_LATERALITY_UNSPECIFIED_EXPLICIT');
$bad=$cases['if_renal'];$bad['specimens'][0]['block_id']='A1';
$reject(static fn()=>clinical_pathology_validate('if_renal',$bad),'PATHOLOGY_SPECIMEN_FIELD_INVALID');
$bad=$cases['if_renal'];$bad['fixation_time']='09:00';
$reject(static fn()=>clinical_pathology_validate('if_renal',$bad),'PATHOLOGY_PARAMETER_FIELD_INVALID');
$bad=$cases['if_renal'];$bad['version']=2;
$reject(static fn()=>clinical_pathology_validate('if_renal',$bad),'PATHOLOGY_PARAMETER_VERSION_INVALID');
$bad=$cases['pathology_outside_review'];unset($bad['source_institution_name']);
$reject(static fn()=>clinical_pathology_validate('pathology_outside_review',$bad),'PATHOLOGY_SOURCE_INSTITUTION_REQUIRED');
$bad=$cases['histopath_biopsy'];$bad['specimens'][]=$bad['specimens'][0];
$reject(static fn()=>clinical_pathology_validate('histopath_biopsy',$bad),'PATHOLOGY_SPECIMEN_DUPLICATE');
$reject(static fn()=>clinical_pathology_validate('cyto_pap',$cases['cyto_fna']),'PATHOLOGY_PARAMETERS_STUDY_INCOMPATIBLE');

// Disposable SQLite catalog: proposed keys exist only in memory for writer QA, never in a real catalog.
$pdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$pdo->exec('CREATE TABLE clinical_study_types (study_type_id INTEGER PRIMARY KEY,study_type_key TEXT,display_name_es TEXT,category_key TEXT,is_active INTEGER)');
$insert=$pdo->prepare('INSERT INTO clinical_study_types VALUES (?,?,?,?,1)');
$extras=['cyto_pap'=>'PATOLOGIA','cyto_liquid_based'=>'PATOLOGIA','urine_cytology'=>'PATOLOGIA',
    'csf_cytology'=>'PATOLOGIA','serous_fluid_cytology'=>'PATOLOGIA','glucose'=>'LABORATORIO',
    'chest_xray'=>'IMAGEN','ecg_12_lead'=>'CARDIOVASCULAR','spirometry'=>'FUNCION_PULMONAR',
    'upper_endoscopy'=>'ENDOSCOPIA','dental_panoramic_xray'=>'IMAGEN'];
$i=0;
foreach ($keys as $key) $insert->execute([++$i,$key,$key,'PATOLOGIA']);
foreach ($extras as $key=>$category) $insert->execute([++$i,$key,$key,$category]);
foreach ($cases as $key=>$raw) {
    $snapshot=clinical_study_normalize_order_payload($pdo,'orders',['order_items'=>[['study_type_key'=>$key,'pathology_order_parameters'=>$raw]]]);
    $item=$snapshot['order_items'][0];
    $check(($item['pathology_order_parameters']['version']??null)===1
        && is_string($item['pathology_order_parameters_label']??null), 'QA_SNAPSHOT_'.strtoupper($key));
    if (str_starts_with($key, 'if_')) {
        $check(str_contains($item['pathology_order_parameters_label'], 'Coordinar manejo y transporte'),
            'QA_IF_HANDLING_NOTICE_'.strtoupper($key));
    }
    $same=clinical_study_normalize_order_payload($pdo,'orders',['order_items'=>[$item]],$snapshot);
    $check($same['order_items'][0]['order_item_id']===$item['order_item_id'], 'QA_REPLACEMENT_'.strtoupper($key));
    $changed=$item;$changed['pathology_order_parameters']['specimens'][0]['anatomic_site_text']='Sitio distinto';
    $reject(static fn()=>clinical_study_normalize_order_payload($pdo,'orders',['order_items'=>[$changed]],$snapshot),'ORDER_ITEM_ID_MEANING_CHANGED');
}
foreach (['cyto_pap','cyto_liquid_based','urine_cytology','csf_cytology','serous_fluid_cytology',
    'glucose','chest_xray','ecg_12_lead','spirometry','upper_endoscopy','dental_panoramic_xray'] as $key) {
    $input=['study_type_key'=>$key];
    if ($key==='serous_fluid_cytology') $input['specimen_collection_requirements']=['version'=>1,'specimen_type_key'=>'PLEURAL_FLUID'];
    $item=clinical_study_normalize_order_payload($pdo,'orders',['order_items'=>[$input]])['order_items'][0];
    $check(!array_key_exists('pathology_order_parameters',$item), 'QA_UNRELATED_'.strtoupper($key));
}
$legacy=clinical_study_normalize_order_payload($pdo,'orders',['order_items'=>[['study_category'=>'OTROS','study_display_name'=>'Procedimiento histórico']]]);
$check(!isset($legacy['order_items'][0]['pathology_order_parameters']),'QA_PROCEDURE_REGRESSION');
