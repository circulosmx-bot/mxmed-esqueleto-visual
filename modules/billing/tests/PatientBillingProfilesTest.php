<?php
declare(strict_types=1);
require_once __DIR__.'/../services/PatientBillingProfilesService.php';

use Billing\Repositories\PatientBillingProfilesRepository;
use Billing\Services\PatientBillingProfilesService;
use Billing\Services\SatCfdiCatalog;

function check(bool $condition, string $message): void { if (!$condition) throw new RuntimeException($message); }
function rejects(callable $work, string $expected): void {
    try { $work(); } catch (Throwable $error) { check($error->getMessage()===$expected, 'wrong rejection: '.$expected); return; }
    throw new RuntimeException('expected rejection: '.$expected);
}

$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->exec("CREATE TABLE patients_patients (patient_id TEXT PRIMARY KEY, display_name TEXT NOT NULL, status TEXT NOT NULL);
CREATE TABLE patients_doctor_links (link_id TEXT PRIMARY KEY, doctor_id TEXT NOT NULL, patient_id TEXT NOT NULL, status TEXT NOT NULL);
CREATE TABLE billing_patient_profiles (
 billing_profile_id TEXT PRIMARY KEY, doctor_id TEXT NOT NULL, patient_id TEXT NOT NULL,
 alias TEXT NOT NULL, receiver_legal_name TEXT NOT NULL, rfc TEXT NOT NULL,
 fiscal_zip_code TEXT NOT NULL, fiscal_regime_code TEXT NOT NULL,
 default_cfdi_use_code TEXT NOT NULL, billing_email TEXT, is_default INTEGER NOT NULL DEFAULT 0,
 archived_at TEXT, created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
 active_default_slot INTEGER GENERATED ALWAYS AS (CASE WHEN archived_at IS NULL AND is_default=1 THEN 1 ELSE NULL END) STORED,
 UNIQUE(doctor_id,patient_id,active_default_slot)
);
INSERT INTO patients_patients VALUES ('patient-a','Persona sintética','active'),('patient-b','Otra persona sintética','active');
INSERT INTO patients_doctor_links VALUES ('link-a','doctor-a','patient-a','active'),('link-b','doctor-b','patient-a','active'),('link-c','doctor-a','patient-b','active');");
$catalog = new SatCfdiCatalog();
$service = new PatientBillingProfilesService(new PatientBillingProfilesRepository($pdo), $catalog);
$cat=$catalog->publicData();
check(count($cat['regimes'])===19 && count($cat['uses'])===24, 'official catalog complete');
check($service->list('doctor-a','patient-a')===[], 'A zero profiles');
$draft=['alias'=>'Personal','receiver_legal_name'=>'Ana López','rfc'=>' maga800101abc ','fiscal_zip_code'=>'01000','fiscal_regime_code'=>'605','default_cfdi_use_code'=>'D01','billing_email'=>''];
$first=$service->create('doctor-a','patient-a',$draft);
check($first['rfc']==='MAGA800101ABC' && $first['receiver_legal_name']==='ANA LÓPEZ' && $first['billing_email']===null, 'RFC/name normalization and optional email');
check((int)$first['is_default']===1 && count($service->list('doctor-a','patient-a'))===1, 'B first profile default');
$second=$service->create('doctor-a','patient-a',[...$draft,'alias'=>'Familiar','receiver_legal_name'=>'Otro Receptor','rfc'=>'LOPA800101ABC']);
$third=$service->create('doctor-a','patient-a',[...$draft,'alias'=>'Empresa','receiver_legal_name'=>'Empresa S.A.','rfc'=>'ABC800101ABC','fiscal_regime_code'=>'601','default_cfdi_use_code'=>'G03']);
check(count($service->list('doctor-a','patient-a'))===3, 'C three profiles');
check(count(array_filter($service->list('doctor-a','patient-a'),static fn($p)=>(int)$p['is_default']===1))===1, 'at most one default');
$service->makeDefault('doctor-a','patient-a',$second['billing_profile_id']);
check((int)$service->list('doctor-a','patient-a')[0]['is_default']===1 && $service->list('doctor-a','patient-a')[0]['billing_profile_id']===$second['billing_profile_id'], 'D/E switch default');
$edited=$service->update('doctor-a','patient-a',$third['billing_profile_id'],[...$draft,'alias'=>'Sociedad','receiver_legal_name'=>'Empresa, S.A. de C.V.','rfc'=>'ABC800101ABC','fiscal_regime_code'=>'601','default_cfdi_use_code'=>'G03','billing_email'=>'facturas@example.test']);
check($edited['receiver_legal_name']==='EMPRESA, S.A. DE C.V.' && $edited['billing_email']==='facturas@example.test', 'F edit preserves punctuation');
$service->archive('doctor-a','patient-a',$third['billing_profile_id']);
check(count($service->list('doctor-a','patient-a'))===2, 'G archive non-default');
check((int)$pdo->query("SELECT COUNT(*) FROM billing_patient_profiles WHERE archived_at IS NOT NULL")->fetchColumn()===1, 'G archival not hard delete');
$service->archive('doctor-a','patient-a',$second['billing_profile_id']);
check(count(array_filter($service->list('doctor-a','patient-a'),static fn($p)=>(int)$p['is_default']===1))===0, 'H archive default permits zero default');
check($service->list('doctor-b','patient-a')===[], 'I doctor B cannot read A profiles');
rejects(fn()=> $service->makeDefault('doctor-b','patient-a',$first['billing_profile_id']),'billing_profile_not_found');
rejects(fn()=> $service->list('doctor-b','patient-b'),'patient_scope_denied');
rejects(fn()=> $service->update('doctor-b','patient-a',$first['billing_profile_id'],$draft),'billing_profile_not_found');
rejects(fn()=> $service->archive('doctor-b','patient-a',$first['billing_profile_id']),'billing_profile_not_found');
rejects(fn()=> $service->create('doctor-a','patient-a',[...$draft,'rfc'=>'BAD']),'invalid_rfc');
rejects(fn()=> $service->create('doctor-a','patient-a',[...$draft,'fiscal_zip_code'=>'1000']),'invalid_fiscal_zip_code');
rejects(fn()=> $service->create('doctor-a','patient-a',[...$draft,'fiscal_regime_code'=>'999']),'invalid_fiscal_regime_code');
rejects(fn()=> $service->create('doctor-a','patient-a',[...$draft,'default_cfdi_use_code'=>'ZZZ']),'invalid_cfdi_use_code');
rejects(fn()=> $service->create('doctor-a','patient-a',[...$draft,'default_cfdi_use_code'=>'G03']),'invalid_cfdi_use_code');
rejects(fn()=> $service->create('doctor-a','patient-a',[...$draft,'billing_email'=>'not-an-email']),'invalid_billing_email');
check((int)$pdo->query("SELECT COUNT(*) FROM patients_patients")->fetchColumn()===2, 'patient data unchanged');
check((int)$pdo->query("SELECT COUNT(*) FROM sqlite_master WHERE type='table' AND name='invoices'")->fetchColumn()===0, 'no invoice table');
echo "PASS FISC01 A-N: zero/one/three profiles, transactional default, edit, archive, cross-doctor, RFC/ZIP/catalog/email, no patient or invoice mutation\n";
