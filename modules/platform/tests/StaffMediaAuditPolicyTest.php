<?php
declare(strict_types=1);
require_once __DIR__.'/../../identity/http/IdentityHttpComposition.php';
\Identity\Http\IdentityHttpComposition::registerAutoloader();
use Platform\Services\{CanonicalAuditPolicyRegistry,CanonicalAuditMetadataSanitizer,SourceModuleCatalog,CorrelatableOperationCatalog};
$r=CanonicalAuditPolicyRegistry::canonical();$rows=CanonicalAuditPolicyRegistry::canonicalRows();
if(hash('sha256',json_encode(array_slice($rows,0,59),JSON_UNESCAPED_SLASHES))!=='ddbaf575ecbb95ec0ac3671ddfd36a3d7abf870164feec1930f1496c99ae2f1e')throw new RuntimeException('historical_59_drift');
if(array_column(array_slice($rows,59),'event_type')!==['STAFF_MEDIA_CHANGED','STAFF_MEDIA_READ'])throw new RuntimeException('media_event_drift');
foreach(['STAFF_MEDIA_CHANGED','STAFF_MEDIA_READ'] as $event){
 $p=$r->policyFor($event);
 if((new SourceModuleCatalog())->moduleForEvent($event)!=='ADMIN'||(new CorrelatableOperationCatalog())->operationForEvent($event)!=='SENSITIVE_ADMIN_OPERATION')throw new RuntimeException('media_catalog_drift');
 foreach([['SUCCESS','USER_REQUEST'],['DENIED','POLICY_DENIED'],['FAILURE','INTERNAL_ERROR']] as [$result,$reason])$r->assertAllowed($event,$result,$reason);
 foreach(['bytes','email','name','document_number','storage_key','key_id','ciphertext','plaintext','request_body'] as $field){
  $blocked=false;try{(new CanonicalAuditMetadataSanitizer())->sanitize([$field=>'synthetic'],$p['allowed_producer_metadata']);}catch(InvalidArgumentException){$blocked=true;}
  if(!$blocked)throw new RuntimeException('media_sensitive_metadata_allowed');
 }
}
echo "STAFF_MEDIA_AUDIT_POLICY=PASS; HISTORICAL_59_UNCHANGED=true; SECRET_METADATA_DENIED=true\n";
