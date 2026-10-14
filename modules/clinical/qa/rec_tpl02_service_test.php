<?php
declare(strict_types=1);
require __DIR__.'/../../../api/_lib/clinical_prescription_templates.php';

function check(string $name, bool $condition): void {
    if (!$condition) throw new RuntimeException($name);
    echo $name."=PASS\n";
}
function expectReason(string $reason, callable $operation): void {
    try { $operation(); }
    catch (ClinicalPrescriptionTemplateException $error) {
        check($reason, $error->reason === $reason); return;
    }
    throw new RuntimeException('expected '.$reason);
}

$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->exec("CREATE TABLE clinical_prescription_templates (
 template_id INTEGER PRIMARY KEY AUTOINCREMENT, template_uuid TEXT NOT NULL UNIQUE,
 doctor_id TEXT NOT NULL, template_name TEXT NOT NULL, content_json TEXT NOT NULL,
 status TEXT NOT NULL DEFAULT 'active', version INTEGER NOT NULL DEFAULT 1,
 created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP)");

$medication = ['name'=>'Amoxicilina','presentation'=>'Cápsula 500 mg','dose'=>'1 cápsula','route'=>'Oral',
    'frequency'=>'Cada 8 horas','duration'=>'7 días','quantity'=>'21 cápsulas','instructions'=>'Después de alimentos'];
$content = ['medications'=>[$medication], 'diagnostic_context'=>['mode'=>'diagnostico','text'=>'Faringitis habitual'],
    'observations'=>'Completar el tratamiento.', 'professional_header'=>'shown'];
$created = clinical_prescription_template_create($pdo,'doctor-a',['template_name'=>'Faringitis — esquema habitual','content'=>$content]);
check('T01_CREATE', $created['status'] === 'active' && $created['version'] === 1);
check('T02_LIST', count(clinical_prescription_template_list($pdo,'doctor-a',false)) === 1);
check('T04_GET', clinical_prescription_template_get($pdo,'doctor-a',$created['uuid'])['content'] === $content);
check('S10_PHYSICIAN_ISOLATION', clinical_prescription_template_list($pdo,'doctor-b',true) === []);
expectReason('not_found', fn()=>clinical_prescription_template_get($pdo,'doctor-b',$created['uuid']));
expectReason('not_found', fn()=>clinical_prescription_template_update($pdo,'doctor-b',$created['uuid'],[
    'template_name'=>'No','content'=>$content,'expected_version'=>1]));
check('S11_CROSS_DOCTOR_READ_DENIED', true);
check('S12_CROSS_DOCTOR_MUTATION_DENIED', true);

$updatedContent = $content; $updatedContent['observations'] = 'Control si persisten síntomas.';
$updated = clinical_prescription_template_update($pdo,'doctor-a',$created['uuid'],[
    'template_name'=>'Faringitis — control habitual','content'=>$updatedContent,'expected_version'=>1]);
check('T05_UPDATE', $updated['version'] === 2 && $updated['content'] === $updatedContent);
expectReason('version_conflict', fn()=>clinical_prescription_template_update($pdo,'doctor-a',$created['uuid'],[
    'template_name'=>'Stale','content'=>$content,'expected_version'=>1]));
check('T06_STALE_VERSION_REJECTED', true);
$duplicate = clinical_prescription_template_duplicate($pdo,'doctor-a',$created['uuid']);
check('T07_DUPLICATE', $duplicate['uuid'] !== $created['uuid'] && $duplicate['template_name'] === 'Copia de Faringitis — control habitual');
$archived = clinical_prescription_template_archive($pdo,'doctor-a',$created['uuid'],['expected_version'=>2]);
check('T08_ARCHIVE', $archived['status'] === 'archived');
check('T09_ARCHIVED_HIDDEN', count(clinical_prescription_template_list($pdo,'doctor-a',false)) === 1);
check('T10_INCLUDE_ARCHIVED', count(clinical_prescription_template_list($pdo,'doctor-a',true)) === 2);
$deleted = clinical_prescription_template_delete($pdo,'doctor-a',$duplicate['uuid'],['expected_version'=>1]);
check('T12_DELETE_TEMPLATE_ONLY', $deleted['uuid'] === $duplicate['uuid'] && count(clinical_prescription_template_list($pdo,'doctor-a',true)) === 1);

foreach (['patient_id','patient_name','age','sex','encounter_id','encounter_ref_id','appointment_id','allergies',
    'current_medications','active_problems','patient_snapshot','signature','qr','fingerprint','document_uuid','issued_version',
    'private_notes','idempotency_key'] as $forbidden) {
    expectReason('unsupported_content_field', fn()=>clinical_prescription_template_validate([
        'template_name'=>'Unsafe','content'=>['medications'=>[$medication],$forbidden=>'leak']]));
}
foreach (['private_notes','patient_id','signature','qr','fingerprint'] as $forbidden) {
    expectReason('unsupported_medication_field', fn()=>clinical_prescription_template_validate([
        'template_name'=>'Unsafe','content'=>['medications'=>[array_merge($medication,[$forbidden=>'leak'])]]]));
}
check('S01_S09_PATIENT_DATA_EXCLUDED', true);
check('PRIVATE_NOTES_EXCLUDED', !array_key_exists('private_notes',$created['content']['medications'][0]));
echo "REC-TPL02_SERVICE=PASS\n";
