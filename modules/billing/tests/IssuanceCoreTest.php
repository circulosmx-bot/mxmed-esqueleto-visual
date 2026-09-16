<?php
declare(strict_types=1);
require_once __DIR__.'/../services/IssuerProfileService.php';
require_once __DIR__.'/../services/SatIssuanceCatalog.php';
require_once __DIR__.'/../services/ExactInvoiceMath.php';
require_once __DIR__.'/../services/CsdCredentialService.php';
require_once __DIR__.'/../services/UnconfiguredPacAdapter.php';
require_once __DIR__.'/../services/InvoiceIssuanceState.php';

use Billing\Services\IssuerProfileService;
use Billing\Services\SatCfdiCatalog;
use Billing\Services\SatIssuanceCatalog;
use Billing\Services\ExactInvoiceMath;
use Billing\Services\CsdCredentialService;
use Billing\Services\EncryptedLocalCsdStore;
use Billing\Services\UnconfiguredPacAdapter;
use Billing\Services\InvoiceIssuanceState;

function ensure(bool $ok,string $label):void {if(!$ok)throw new RuntimeException($label);}
function rejected(callable $call,string $code):void {try{$call();}catch(Throwable $e){ensure($e->getMessage()===$code,'wrong error: '.$e->getMessage().' expected '.$code);return;}throw new RuntimeException('expected '.$code);}

$catalog=new SatIssuanceCatalog();
$data=$catalog->publicData();
ensure(count($data['payment_forms'])===22 && count($data['payment_methods'])===2,'official XSD enumerations');
$catalog->requireCode('c_ClaveProdServ','85121501');
$catalog->requireCode('c_ClaveUnidad','E48');
rejected(fn()=>$catalog->requireCode('c_ClaveProdServ','00000000'),'invalid_c_ClaveProdServ');
rejected(fn()=>$catalog->requireCode('c_ObjetoImp','99'),'invalid_c_ObjetoImp');
$math=ExactInvoiceMath::compute([
    ['quantity'=>'2.500000','unit_value'=>'100.333333','discount'=>'5','taxes'=>[['direction'=>'TRANSFER','tax_code'=>'002','factor_code'=>'Tasa','rate'=>'0.160000']]],
    ['quantity'=>'1','unit_value'=>'200','discount'=>'0','taxes'=>[['direction'=>'TRANSFER','tax_code'=>'002','factor_code'=>'Exento']]],
]);
ensure($math['subtotal']==='450.833333' && $math['discount']==='5.000000' && $math['tax_total']==='39.333333' && $math['total']==='485.166666','exact multiple items/tax/discount');
ensure($math['items'][0]['taxes'][0]['amount']==='39.333333' && $math['items'][1]['taxes'][0]['amount']===null,'tax precision and exempt');
$quota=ExactInvoiceMath::compute([['quantity'=>'2','unit_value'=>'10','taxes'=>[['direction'=>'TRANSFER','tax_code'=>'003','factor_code'=>'Cuota','rate'=>'1.250000']]]]);
ensure($quota['tax_total']==='2.500000' && $quota['items'][0]['taxes'][0]['tax_base']==='2.000000','quantity-based quota');
rejected(fn()=>ExactInvoiceMath::compute([['quantity'=>'1','unit_value'=>'1','discount'=>'2']]),'discount_exceeds_subtotal');
rejected(fn()=>ExactInvoiceMath::compute([['quantity'=>'1','unit_value'=>'1.0000001']]),'invalid_decimal');
rejected(fn()=>(new UnconfiguredPacAdapter())->certify(['unsigned_xml'=>'<xml/>'],'key'),'pac_provider_selection_required');
InvoiceIssuanceState::requireTransition('CERTIFICATION_PENDING','RECONCILIATION_REQUIRED');
InvoiceIssuanceState::requireTransition('RECONCILIATION_REQUIRED','CERTIFIED');
rejected(fn()=>InvoiceIssuanceState::requireTransition('RECONCILIATION_REQUIRED','CERTIFICATION_PENDING'),'invalid_issuance_transition');
rejected(fn()=>InvoiceIssuanceState::requireTransition('CERTIFIED','DRAFT'),'invalid_issuance_transition');

$pdo=new PDO('sqlite::memory:');$pdo->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
$pdo->exec("CREATE TABLE profiles_doctors (doctor_id TEXT PRIMARY KEY);
CREATE TABLE billing_issuer_profiles (issuer_profile_id TEXT PRIMARY KEY,doctor_id TEXT NOT NULL,alias TEXT NOT NULL,issuer_legal_name TEXT NOT NULL,rfc TEXT NOT NULL,fiscal_regime_code TEXT NOT NULL,expedition_postal_code TEXT NOT NULL,is_default INTEGER NOT NULL DEFAULT 0,archived_at TEXT,created_at TEXT DEFAULT CURRENT_TIMESTAMP,updated_at TEXT DEFAULT CURRENT_TIMESTAMP,active_default_slot INTEGER GENERATED ALWAYS AS (CASE WHEN archived_at IS NULL AND is_default=1 THEN 1 ELSE NULL END) STORED,UNIQUE(doctor_id,active_default_slot));
CREATE TABLE billing_csd_credentials (credential_id TEXT PRIMARY KEY,issuer_profile_id TEXT,doctor_id TEXT,certificate_serial TEXT,certificate_rfc TEXT,certificate_sha256 TEXT,valid_from TEXT,valid_to TEXT,certificate_type TEXT,certificate_storage_key TEXT,private_key_storage_key TEXT,password_storage_key TEXT,archived_at TEXT,created_at TEXT DEFAULT CURRENT_TIMESTAMP);
CREATE TABLE billing_invoice_issuance_events (event_id TEXT PRIMARY KEY,doctor_id TEXT,patient_id TEXT,draft_id TEXT,invoice_id TEXT,issuer_profile_id TEXT,billing_profile_id TEXT,action TEXT,result TEXT,occurred_at TEXT DEFAULT CURRENT_TIMESTAMP);
INSERT INTO profiles_doctors VALUES ('doctor-a'),('doctor-b');");
$issuers=new IssuerProfileService($pdo,new SatCfdiCatalog());
ensure($issuers->list('doctor-a')===[],'zero issuers');
$input=['alias'=>'Personal','issuer_legal_name'=>'Emisor Uno','rfc'=>'AAA010101AAA','fiscal_regime_code'=>'601','expedition_postal_code'=>'01000'];
$one=$issuers->create('doctor-a',$input);$two=$issuers->create('doctor-a',[...$input,'alias'=>'Empresa','rfc'=>'BBB010101BBB']);
ensure(count($issuers->list('doctor-a'))===2 && (int)$one['is_default']===1,'multiple issuers one default');
$issuers->makeDefault('doctor-a',$two['issuer_profile_id']);
ensure((int)$issuers->get('doctor-a',$two['issuer_profile_id'])['is_default']===1 && (int)$issuers->get('doctor-a',$one['issuer_profile_id'])['is_default']===0,'default switch');
rejected(fn()=>$issuers->get('doctor-b',$one['issuer_profile_id']),'issuer_profile_not_found');
rejected(fn()=>$issuers->create('doctor-a',[...$input,'rfc'=>'BAD']),'invalid_rfc');
rejected(fn()=>$issuers->create('doctor-a',[...$input,'expedition_postal_code'=>'1234']),'invalid_expedition_postal_code');
rejected(fn()=>$issuers->create('doctor-a',[...$input,'fiscal_regime_code'=>'999']),'invalid_fiscal_regime_code');
$issuers->archive('doctor-a',$two['issuer_profile_id']);
ensure(count($issuers->list('doctor-a'))===1 && (int)$pdo->query('SELECT COUNT(*) FROM billing_issuer_profiles WHERE archived_at IS NOT NULL')->fetchColumn()===1,'archive not delete');

$root=sys_get_temp_dir().'/mxmed-csd-test-'.bin2hex(random_bytes(6));
$store=new EncryptedLocalCsdStore($root,base64_encode(random_bytes(32)));
$key=openssl_pkey_new(['private_key_bits'=>2048,'private_key_type'=>OPENSSL_KEYTYPE_RSA]);
$csr=openssl_csr_new(['commonName'=>'QA EMISOR','serialNumber'=>'AAA010101AAA'],$key,['digest_alg'=>'sha256']);
$certificate=openssl_csr_sign($csr,null,$key,1,['digest_alg'=>'sha256'],123);
openssl_x509_export($certificate,$cer);openssl_pkey_export($key,$private,'correct-password');
$csd=new CsdCredentialService($pdo,$issuers,$store);
try{
    rejected(fn()=>$csd->register('doctor-a',$one['issuer_profile_id'],$cer,$private,'wrong-password'),'invalid_csd_key_or_password');
    $other=openssl_pkey_new(['private_key_bits'=>2048]);openssl_pkey_export($other,$wrongKey,'correct-password');
    rejected(fn()=>$csd->register('doctor-a',$one['issuer_profile_id'],$cer,$wrongKey,'correct-password'),'csd_key_mismatch');
    rejected(fn()=>$csd->register('doctor-a',$one['issuer_profile_id'],'not a cert',$private,'correct-password'),'invalid_csd_certificate');
    rejected(fn()=>$csd->register('doctor-a',$one['issuer_profile_id'],$cer,'not a key','correct-password'),'invalid_csd_key_or_password');
    $expired=openssl_csr_sign($csr,null,$key,0,['digest_alg'=>'sha256'],124);openssl_x509_export($expired,$expiredCer);
    rejected(fn()=>$csd->register('doctor-a',$one['issuer_profile_id'],$expiredCer,$private,'correct-password'),'csd_certificate_not_current');
    $otherCsr=openssl_csr_new(['commonName'=>'QA EMISOR','serialNumber'=>'CCC010101CCC'],$key,['digest_alg'=>'sha256']);
    $wrongRfcCert=openssl_csr_sign($otherCsr,null,$key,1,['digest_alg'=>'sha256'],125);openssl_x509_export($wrongRfcCert,$wrongRfcCer);
    rejected(fn()=>$csd->register('doctor-a',$one['issuer_profile_id'],$wrongRfcCer,$private,'correct-password'),'csd_issuer_rfc_mismatch');
    $registered=$csd->register('doctor-a',$one['issuer_profile_id'],$cer,$private,'correct-password');
    ensure($registered['certificate_type']==='UNVERIFIED' && count($csd->list('doctor-a',$one['issuer_profile_id']))===1,'CSD registration stays fail closed');
    $row=$pdo->query('SELECT * FROM billing_csd_credentials')->fetch(PDO::FETCH_ASSOC);
    ensure(!str_contains(json_encode($row),$private)&&!str_contains(json_encode($row),'correct-password'),'no plaintext secrets in DB');
    ensure($store->read($row['private_key_storage_key'])===$private && $store->read($row['password_storage_key'])==='correct-password','encrypted private read');
    $path=$root.'/'.$row['private_key_storage_key'];ensure((fileperms($path)&0077)===0,'private permissions');
    ensure((int)$pdo->query("SELECT COUNT(*) FROM billing_invoice_issuance_events WHERE action='CSD_REGISTERED'")->fetchColumn()===1,'metadata audit');
} finally {
    if(is_dir($root)){$iterator=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::CHILD_FIRST);foreach($iterator as $file)$file->isDir()?rmdir($file->getPathname()):unlink($file->getPathname());rmdir($root);}
}
echo "PASS FISC02B: SAT enumerations, exact decimals/taxes, issuer authority/scope/default/archive, encrypted CSD validation, PAC closed\n";
