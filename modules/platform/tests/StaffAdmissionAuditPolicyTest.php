<?php
declare(strict_types=1);
require_once __DIR__.'/../../identity/http/IdentityHttpComposition.php';
\Identity\Http\IdentityHttpComposition::registerAutoloader();
use Platform\Services\{CanonicalAuditPolicyRegistry,CanonicalAuditMetadataSanitizer,SourceModuleCatalog,CorrelatableOperationCatalog};
$registry=CanonicalAuditPolicyRegistry::canonical();$rows=CanonicalAuditPolicyRegistry::canonicalRows();
function requireCheck(bool $ok,string $name):void{if(!$ok)throw new RuntimeException($name);}
requireCheck(hash('sha256',json_encode(array_slice($rows,0,57),JSON_UNESCAPED_SLASHES))==='8233dcbacce77aee64fa40ce4ebd97e55003f21daf2f0092cd353f4e66d7c62a','all_57_historical_policies_unchanged');
requireCheck(array_column(array_slice($rows,57,2),'event_type')===['STAFF_ADMISSION_CASE_CHANGED','STAFF_ADMISSION_CASE_READ'],'two_exact_appended_events');
foreach(['STAFF_ADMISSION_CASE_CHANGED','STAFF_ADMISSION_CASE_READ'] as $event){
 $policy=$registry->policyFor($event);
 requireCheck((new SourceModuleCatalog())->moduleForEvent($event)==='ADMIN','canonical_module');
 requireCheck((new CorrelatableOperationCatalog())->operationForEvent($event)==='SENSITIVE_ADMIN_OPERATION','canonical_correlation');
 requireCheck($policy['allowed_producer_metadata']===['operation','case_id','version','provenance']&&$policy['session_required']&&$policy['actor_required']&&$policy['target_required'],'closed_policy');
 foreach(['given_names','first_surname','email','mobile','document','raw_body','tokens'] as $field){
  $denied=false;try{(new CanonicalAuditMetadataSanitizer())->sanitize([$field=>'synthetic'],$policy['allowed_producer_metadata']);}catch(InvalidArgumentException){$denied=true;}
  requireCheck($denied,'personal_or_secret_metadata_denied');
 }
 $registry->assertAllowed($event,'SUCCESS','USER_REQUEST');
}
$registry->assertAllowed('STAFF_ADMISSION_CASE_CHANGED','SUCCESS','ADMIN_DECISION');
$denied=false;try{$registry->assertAllowed('STAFF_ADMISSION_CASE_READ','SUCCESS','ADMIN_DECISION');}catch(InvalidArgumentException){$denied=true;}
requireCheck($denied,'read_not_admin_decision');
echo "STAFF_ADMISSION_AUDIT_POLICY=PASS; HISTORICAL_57_UNCHANGED=true; PERSONAL_METADATA_DENIED=true\n";
