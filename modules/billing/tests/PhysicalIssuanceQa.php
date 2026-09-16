<?php
declare(strict_types=1);
// Local, synthetic, self-cleaning physical QA. Never point this at production.
if (getenv('MXMED_ALLOW_LOCAL_BILLING_QA')!=='1' || !in_array(getenv('MXMED_DB_HOST'),['127.0.0.1','localhost','::1'],true)) {
    fwrite(STDERR,"Local billing QA gate required\n");exit(2);
}
require_once __DIR__.'/../../../api/_lib/db.php';
require_once __DIR__.'/../services/InvoiceDraftService.php';
require_once __DIR__.'/../services/CfdiPreStampValidator.php';
require_once __DIR__.'/../services/UnconfiguredPacAdapter.php';

use Billing\Services\IssuerProfileService;
use Billing\Services\SatCfdiCatalog;
use Billing\Services\SatIssuanceCatalog;
use Billing\Services\InvoiceDraftService;
use Billing\Services\CfdiPreStampValidator;
use Billing\Services\UnconfiguredPacAdapter;

$pdo=mxmed_pdo();$id=bin2hex(random_bytes(7));$doctor='qa-fisc02b-'.$id;$other=$doctor.'-other';$patient='qa-patient-'.$id;$noProfilePatient='qa-empty-'.$id;$link='qa-link-'.$id;$emptyLink='qa-empty-link-'.$id;$receiver=IssuerProfileService::uuid();$secondReceiver=IssuerProfileService::uuid();$draftId=null;$issuerId=null;
$before=[];foreach(['profiles_doctors','patients_patients','patients_doctor_links','billing_patient_profiles','billing_issuer_profiles','billing_invoice_drafts','billing_invoices','billing_invoice_issuance_events'] as $table)$before[$table]=(int)$pdo->query('SELECT COUNT(*) FROM '.$table)->fetchColumn();
$assert=static function(bool $condition,string $label):void {if(!$condition)throw new RuntimeException($label);};
try{
    $q=$pdo->prepare('INSERT INTO profiles_doctors (doctor_id,display_name) VALUES (?,?)');$q->execute([$doctor,'QA FISC02B']);$q->execute([$other,'QA FISC02B OTHER']);
    $q=$pdo->prepare('INSERT INTO patients_patients (patient_id,display_name,status) VALUES (?,?,\'active\')');$q->execute([$patient,'Paciente sintético FISC02B']);$q->execute([$noProfilePatient,'Paciente sin datos fiscales']);
    $q=$pdo->prepare('INSERT INTO patients_doctor_links (link_id,doctor_id,patient_id,status) VALUES (?,?,?,\'active\')');$q->execute([$link,$doctor,$patient]);$q->execute([$emptyLink,$doctor,$noProfilePatient]);
    $q=$pdo->prepare('INSERT INTO billing_patient_profiles (billing_profile_id,doctor_id,patient_id,alias,receiver_legal_name,rfc,fiscal_zip_code,fiscal_regime_code,default_cfdi_use_code,is_default) VALUES (?,?,?,?,?,?,?,?,?,?)');
    $q->execute([$receiver,$doctor,$patient,'QA','RECEPTOR DE PRUEBA','MAGA800101ABC','01000','605','D01',1]);
    $q->execute([$secondReceiver,$doctor,$patient,'QA2','OTRO RECEPTOR DE PRUEBA','LOPA800101ABC','01000','605','D01',0]);
    $fiscal=new SatCfdiCatalog();$codes=new SatIssuanceCatalog();$issuers=new IssuerProfileService($pdo,$fiscal);$drafts=new InvoiceDraftService($pdo,$fiscal,$codes);
    $issuer=$issuers->create($doctor,['alias'=>'QA emisor','issuer_legal_name'=>'EMISOR DE PRUEBA','rfc'=>'AAA010101AAA','fiscal_regime_code'=>'601','expedition_postal_code'=>'01000']);$issuerId=$issuer['issuer_profile_id'];
    $assert((int)$issuer['is_default']===1,'issuer default');
    $item=['product_service_code'=>'85121501','description'=>'Consulta sintética QA','quantity'=>'2.500000','unit_code'=>'E48','unit_value'=>'100.333333','discount'=>'5','tax_object_code'=>'02',
        'taxes'=>[['direction'=>'TRANSFER','tax_code'=>'002','factor_code'=>'Tasa','rate'=>'0.160000']]];
    $payload=['patient_id'=>$patient,'issuer_profile_id'=>$issuerId,'billing_profile_id'=>$receiver,'currency_code'=>'MXN','cfdi_use_code'=>'D01','payment_method_code'=>'PUE','payment_form_code'=>'03','items'=>[$item,['product_service_code'=>'85121501','description'=>'Segundo concepto','quantity'=>'1','unit_code'=>'E48','unit_value'=>'200','discount'=>'0','tax_object_code'=>'01','taxes'=>[]]]];
    $draft=$drafts->save($doctor,$payload);$draftId=$draft['draft_id'];
    $assert($draft['total']==='485.166666' && count($draft['items'])===2,'physical exact draft');
    try{$drafts->save($doctor,[...$payload,'patient_id'=>$noProfilePatient]);throw new RuntimeException('receiver scope accepted for other patient');}catch(DomainException $e){$assert($e->getMessage()==='billing_profile_not_found','no receiver profile');}
    try{$drafts->save($doctor,[...$payload,'items'=>[[...$item,'product_service_code'=>'00000000']]]);throw new RuntimeException('invalid SAT code accepted');}catch(InvalidArgumentException $e){$assert($e->getMessage()==='invalid_c_ClaveProdServ','invalid product code');}
    try{$drafts->save($doctor,[...$payload,'payment_method_code'=>'PPD']);throw new RuntimeException('invalid payment pairing accepted');}catch(InvalidArgumentException $e){$assert($e->getMessage()==='payment_method_form_conflict','payment pairing');}
    try{$drafts->get($other,$draftId);throw new RuntimeException('cross-doctor draft leaked');}catch(DomainException $e){$assert($e->getMessage()==='invoice_draft_not_found','cross-doctor draft error');}
    $view=(new CfdiPreStampValidator($pdo,$fiscal,$codes))->inspect($doctor,$draft);
    $codesFound=array_column($view['errors'],'code');
    $assert(!$view['valid'] && in_array('active_verified_csd_required',$codesFound,true) && in_array('tax_rate_catalog_unverified',$codesFound,true),'pre-stamp fails closed');
    $assert(strlen($view['xml_sha256'])===64,'server-generated XML preview');
    $tampered=$draft;$tampered['total']='1.000000';
    $tamperedView=(new CfdiPreStampValidator($pdo,$fiscal,$codes))->inspect($doctor,$tampered);
    $assert(in_array('invoice_amount_mismatch',array_column($tamperedView['errors'],'code'),true),'pre-stamp recalculates totals');
    try{$drafts->save($doctor,[...$payload,'revision'=>999],$draftId);throw new RuntimeException('stale revision accepted');}catch(DomainException $e){$assert($e->getMessage()==='draft_revision_conflict','optimistic revision');}
    $updated=$drafts->save($doctor,[...$payload,'revision'=>1,'items'=>[$payload['items'][1]]],$draftId);
    $assert((int)$updated['revision']===2 && $updated['total']==='200.000000' && count($updated['items'])===1,'replace items and recalculate');
    $updated=$drafts->save($doctor,[...$payload,'revision'=>2,'billing_profile_id'=>$secondReceiver,'items'=>[$payload['items'][1]]],$draftId);
    $assert($updated['billing_profile_id']===$secondReceiver && (int)$updated['revision']===3,'multiple receiver profiles');
    try{(new UnconfiguredPacAdapter())->certify(['unsigned_xml'=>'<xml/>'],'idempotency-key');throw new RuntimeException('PAC call not blocked');}catch(DomainException $e){$assert($e->getMessage()==='pac_provider_selection_required','PAC closed');}
    echo "PASS PHYSICAL FISC02B: issuer, scoped draft, 2 concepts/tax, decimals, revision, pre-stamp, PAC closed\n";
} finally {
    $q=$pdo->prepare('DELETE FROM billing_invoice_issuance_events WHERE doctor_id IN (?,?)');$q->execute([$doctor,$other]);
    if($draftId){$q=$pdo->prepare('DELETE t FROM billing_invoice_draft_item_taxes t JOIN billing_invoice_draft_items i ON i.item_id=t.item_id WHERE i.draft_id=?');$q->execute([$draftId]);$q=$pdo->prepare('DELETE FROM billing_invoice_draft_items WHERE draft_id=?');$q->execute([$draftId]);$q=$pdo->prepare('DELETE FROM billing_invoice_drafts WHERE draft_id=?');$q->execute([$draftId]);}
    if($issuerId){$q=$pdo->prepare('DELETE FROM billing_issuer_profiles WHERE issuer_profile_id=?');$q->execute([$issuerId]);}
    foreach([['billing_patient_profiles','billing_profile_id',$receiver],['billing_patient_profiles','billing_profile_id',$secondReceiver],['patients_doctor_links','link_id',$link],['patients_doctor_links','link_id',$emptyLink],['patients_patients','patient_id',$patient],['patients_patients','patient_id',$noProfilePatient],['profiles_doctors','doctor_id',$doctor],['profiles_doctors','doctor_id',$other]] as [$table,$column,$value]){$q=$pdo->prepare("DELETE FROM `$table` WHERE `$column`=?");$q->execute([$value]);}
    foreach($before as $table=>$count)$assert((int)$pdo->query('SELECT COUNT(*) FROM '.$table)->fetchColumn()===$count,'cleanup '.$table);
    echo "PASS PHYSICAL CLEANUP: patient, doctor, invoice counts restored\n";
}
