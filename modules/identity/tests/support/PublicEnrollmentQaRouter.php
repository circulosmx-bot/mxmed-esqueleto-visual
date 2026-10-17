<?php
declare(strict_types=1);
// Explicit injection only in this owning new disposable server; no production selector.
if(PHP_SAPI!=='cli-server'||getenv('PUB_ENR02_QA')!=='1'||getenv('MXMED_NORMAL_LOCAL_IDENTITY_EXPLICIT')!=='0'
    ||!preg_match('/^mxmed_gate4d_preview_c4ui_([a-f0-9]{10})$/D',(string)getenv('MXMED_DB_NAME'),$m)
    ||getenv('MXMED_DB_USER')!=='gov03b_'.$m[1]){http_response_code(403);exit;}
$root=dirname(__DIR__,4);
require_once $root.'/modules/identity/http/IdentityHttpComposition.php';
Identity\Http\IdentityHttpComposition::registerAutoloader();
$identity=Identity\Http\IdentityHttpComposition::fromProcessEnvironment();$pdo=$identity->pdo();
if($pdo->query('SELECT DATABASE()')->fetchColumn()!==getenv('MXMED_DB_NAME')||$pdo->query('SELECT CURRENT_USER()')->fetchColumn()!==getenv('MXMED_DB_USER').'@127.0.0.1'){http_response_code(403);exit;}
$security=new Identity\Services\GovernanceSecurityContext($identity->sessions(),$identity->authentication());
$cipher=new Identity\Accreditation\EvidenceCipher('c4-qa-key',['c4-qa-key'=>base64_decode((string)getenv('C4_EV_QA_KEY'),true)],'synthetic-retention-fixture-no-real-policy');
$service=new Identity\Accreditation\PublicEnrollmentService(new Identity\Accreditation\EvidenceAuthority($pdo,$security),$cipher,(string)getenv('PUB_ENR02_QA_PROOF_KEY'));
Identity\Accreditation\PublicEnrollmentRuntime::install(new Identity\Accreditation\PublicEnrollmentHttp($identity,$service,(string)getenv('MXMED_GOVERNANCE_PROXY_KEY')));
return false;
