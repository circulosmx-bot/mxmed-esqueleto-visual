<?php
declare(strict_types=1);
require_once __DIR__.'/../../../api/_lib/clinical_vital_references.php';
$today=new DateTimeImmutable('2026-09-27',new DateTimeZone('America/Mexico_City'));
$checks=0;
function check_reference(bool $condition,string $name): void { global $checks; if (!$condition) throw new RuntimeException($name); ++$checks; }
function resolve_test(?string $dob): array { global $today; return clinical_vital_references_resolve($dob,$today); }
function item_test(array $result,string $code): array { foreach ($result['items'] as $item) if ($item['measurement_code']===$code) return $item; throw new RuntimeException($code); }
$adult=resolve_test('1990-09-27');
$expected=['blood_pressure'=>'Referencia adulta: normal <120/<80 mmHg','heart_rate'=>'Referencia en reposo: 60–100 bpm','respiratory_rate'=>'Referencia en reposo: 12–18 rpm','temperature'=>'Referencia habitual: 36.5–37.3 °C','oxygen_saturation'=>'Referencia habitual: 95–100 %','pain'=>'Escala: 0–10','weight'=>'','height'=>'','waist'=>''];
check_reference(count($adult['items'])===9,'complete existing catalog');
foreach ($expected as $code=>$display) check_reference(item_test($adult,$code)['display_reference']===$display,'adult '.$code);
foreach (['2008-09-27'=>'adult','2008-09-28'=>'adolescent','2013-09-27'=>'adolescent','2013-09-28'=>'child','2025-09-27'=>'child','2025-09-28'=>'infant','2026-09-27'=>'infant'] as $dob=>$population) {
    $r=resolve_test($dob);check_reference($r['patient_context']['population']===$population,'birthday boundary '.$dob);
    $bp=item_test($r,'blood_pressure');
    check_reference($bp['population']===$population,'BP population '.$dob);
    if ($population==='adolescent') check_reference($bp['source']['source_id']==='aap_bp_2017' && str_contains($bp['display_reference'],'<120/<80'),'adolescent source');
    if ($population==='child') check_reference($bp['reference_available']===false && $bp['display_reference']==='Referencia pediátrica: requiere edad, sexo y talla.' && $bp['sex_requirement']==='biological_sex' && $bp['height_requirement']==='validated_height_percentile','child fallback');
    if ($population==='infant') check_reference(!$bp['reference_available'] && !str_contains($bp['display_reference'],'120'),'infant never adult');
    if ($population!=='adult') foreach (['heart_rate','respiratory_rate','temperature'] as $code) check_reference(!item_test($r,$code)['reference_available'],'pediatric deferred '.$code);
}
foreach ([null,'','2026-09-28','2024-02-30','2025-02-29','0000-00-00','0000-01-01','2008-9-27','1990-09-27 00:00:00','not-a-date'] as $dob) {
    $r=resolve_test($dob);check_reference($r['patient_context']['population']==='unknown','invalid DOB');
    foreach (['blood_pressure','heart_rate','respiratory_rate','temperature','pain'] as $code) check_reference(!item_test($r,$code)['reference_available'],'invalid demographics '.$code);
}
check_reference(resolve_test('2008-02-29')['patient_context']['population']==='adult','valid leap day');
check_reference(!item_test(resolve_test('2019-09-27'),'pain')['reference_available'],'young pain scale unavailable');
check_reference(item_test(resolve_test('2018-09-27'),'pain')['reference_kind']==='scale','age 8 scale only');
foreach (clinical_vital_reference_registry()['definitions'] as $definition) {
    foreach (['reference_id','measurement_code','population','age_min','age_max','sex_requirement','height_requirement','context','reference_kind','display_reference','unit','source','caveats'] as $field) check_reference(array_key_exists($field,$definition),'metadata '.$field);
    if ($definition['reference_available']) {
        $source=$definition['source'];
        check_reference((bool)$source['source_id'] && (bool)$source['source_year'] && (bool)$source['source_version'] && str_starts_with($source['url'],'https://') && (bool)$definition['context'],'sourced active numeric authority');
        check_reference($definition['sex_requirement']==='none','no sex table activated');
    }
}
$json=json_encode($adult);
foreach (['birthdate','display_name','gender','patient_id','value_numeric','systolic_mm_hg','effective_at','provenance'] as $field) check_reference(!str_contains($json,'"'.$field.'"'),'minimum read result '.$field);
check_reference(item_test($adult,'pain')['reference_kind']==='scale','pain not a normal value');
echo "VITALREF01_REGISTRY_GATE=PASS checks=$checks\n";
