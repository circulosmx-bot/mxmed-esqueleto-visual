<?php
declare(strict_types=1);

$root = dirname(__DIR__, 3);
require_once $root.'/api/_lib/clinical_prescription_templates.php';

function reg03(bool $ok, string $case): void
{
    if (!$ok) throw new RuntimeException('FAIL '.$case);
    echo $case."=PASS\n";
}

function regimen(array $administration, array $duration): array
{
    return ['administration'=>$administration,'duration'=>$duration];
}

function medication(array $regimen, string $name = 'Medicamento QA'): array
{
    return ['medication_schema_version'=>2,'name'=>$name,'presentation'=>'Tabletas 10 mg',
        'dose'=>'1 tableta','route'=>'Oral','frequency'=>'','duration'=>'','quantity'=>'14 tabletas',
        'instructions'=>'Tomar con agua','regimen'=>$regimen];
}

$scheduled=regimen(['pattern'=>'SCHEDULED','instruction'=>'Cada 12 horas'],
    ['mode'=>'DEFINED_DURATION','value'=>7,'unit'=>'DAY']);
$prn=regimen(['pattern'=>'AS_NEEDED','indication'=>'En caso de dolor','repeat_mode'=>'REPEATABLE',
    'minimum_interval'=>['value'=>6,'unit'=>'HOUR'],
    'maximum_dose_per_period'=>['value'=>3,'unit'=>'tabletas','period'=>['value'=>24,'unit'=>'HOUR']],
    'maximum_administrations_per_period'=>['count'=>3,'period'=>['value'=>24,'unit'=>'HOUR']]],
    ['mode'=>'DEFINED_DURATION','value'=>3,'unit'=>'DAY']);
$continuous=regimen(['pattern'=>'SCHEDULED','instruction'=>'Cada mañana'],['mode'=>'CONTINUOUS']);
$reassessment=regimen(['pattern'=>'SCHEDULED','instruction'=>'Cada noche'],['mode'=>'UNTIL_REASSESSMENT']);
$untilDate=regimen(['pattern'=>'SCHEDULED','instruction'=>'Cada 24 horas'],['mode'=>'UNTIL_DATE','date'=>'2026-10-20']);
$defined=regimen(['pattern'=>'PHYSICIAN_DEFINED','instruction'=>'Reducir una tableta cada tercer día'],
    ['mode'=>'PHYSICIAN_DEFINED','instruction'=>'Terminar al completar la reducción']);

foreach (['REG03_01'=>$scheduled,'REG03_02'=>$prn,'REG03_03'=>$continuous,'REG03_04'=>$reassessment,
    'REG03_05'=>$untilDate,'REG03_06'=>$defined] as $case=>$value) {
    $clean=clinical_prescription_regimen_normalize($value,1,'2026-10-14');
    reg03(clinical_prescription_regimen_normalize($clean,1,'2026-10-14')===$clean,$case);
}

$partial=clinical_prescription_regimen_draft_normalize(
    regimen(['pattern'=>'AS_NEEDED','indication'=>'','repeat_mode'=>'REPEATABLE','minimum_interval'=>['value'=>'','unit'=>'HOUR']],
        ['mode'=>'DEFINED_DURATION','value'=>'','unit'=>'DAY']),1);
reg03($partial['administration']['pattern']==='AS_NEEDED'
    && $partial['administration']['minimum_interval']['unit']==='HOUR'
    && $partial['duration']['mode']==='DEFINED_DURATION','REG03_07');

$template=['template_name'=>'Pauta estructurada','content'=>['content_schema_version'=>2,
    'medications'=>[medication($prn)],'diagnostic_context'=>['mode'=>'sin_diagnostico','text'=>''],
    'observations'=>'Vigilar respuesta.','professional_header'=>'data_only']];
$clean=clinical_prescription_template_validate($template);
$canonicalPrn=clinical_prescription_regimen_normalize($prn,1,null);
reg03($clean['content']['content_schema_version']===2
    && $clean['content']['medications'][0]['regimen']===$canonicalPrn
    && str_contains($clean['content']['medications'][0]['frequency'],'Intervalo mínimo'), 'REG03_12');
reg03($clean['content']['medications'][0]['regimen']===$canonicalPrn,'REG03_13');

$legacyMedication=['name'=>'Legado','presentation'=>'Tabletas','dose'=>'1 tableta','route'=>'Oral',
    'frequency'=>'Según indicación histórica','duration'=>'Duración histórica','quantity'=>'10 tabletas','instructions'=>''];
$legacy=clinical_prescription_template_validate(['template_name'=>'Legada','content'=>['medications'=>[$legacyMedication]]]);
reg03($legacy['content']['medications'][0]===$legacyMedication && !isset($legacy['content']['content_schema_version']), 'REG03_14');

$mixed=$template;
$mixed['content']['medications'][]=['medication_schema_version'=>1,...$legacyMedication];
$mixedClean=clinical_prescription_template_validate($mixed);
reg03($mixedClean['content']['medications'][1]['medication_schema_version']===1
    && !isset($mixedClean['content']['medications'][1]['regimen']), 'REG03_MIXED_TEMPLATE_ITEM_SEMANTICS');

foreach ([
    ['content','patient_id'],['content','encounter_id'],['content','signature'],['medication','private_notes'],
    ['medication','patient_id'],['regimen','unknown_field'],
] as [$level,$field]) {
    $unsafe=$template;
    if($level==='content')$unsafe['content'][$field]='forbidden';
    elseif($level==='medication')$unsafe['content']['medications'][0][$field]='forbidden';
    else $unsafe['content']['medications'][0]['regimen']['administration'][$field]='forbidden';
    try { clinical_prescription_template_validate($unsafe); $rejected=false; }
    catch (ClinicalPrescriptionTemplateException) { $rejected=true; }
    reg03($rejected,'REG03_REJECT_'.strtoupper($level).'_'.strtoupper($field));
}
reg03(!str_contains(json_encode($clean,JSON_THROW_ON_ERROR),'private_notes'),'REG03_18');

$js=(string)file_get_contents($root.'/assets/js/app.js');
reg03(str_contains($js,"newStructuredPrescriptionMedication")
    && str_contains($js,"pattern: 'SCHEDULED'") && str_contains($js,"duration: { mode: ''"),'REG03_EDITOR_DEFAULTS');
reg03(str_contains($js,'Convertir a pauta estructurada')
    && str_contains($js,'legacy_frequency_reference'),'REG03_EXPLICIT_LEGACY_CONVERSION');
reg03(!str_contains($js,'La receta combina pautas heredadas y estructuradas')
    && str_contains($js,'mixed?{medication_schema_version:2}')
    && str_contains($js,'mixed?{medication_schema_version:1}'),'REG04C_MIXED_DRAFT_BOUND_V2');
reg03(str_contains($js,"if (row.dataset.neRxSchema === '2')")
    && str_contains($js,"quantity_mode:'manual'")
    && str_contains($js,'Indica la cantidad total que debe dispensarse.')
    && !str_contains($js,'Cantidad finita indicada por el médico'),'REG03_19_20');
reg03(str_contains($js,'cloneStructuredRegimen(item.regimen)')
    && str_contains($js,'cloneStructuredRegimen(item?.regimen)'),'REG03_09_10_11');

echo "REC_REG03_CONTRACT=PASS\n";
