<?php
declare(strict_types=1);
require __DIR__.'/../services/MediaReviewInterventionService.php';
use Media\Services\MediaReviewInterventionService as Service;
use Platform\Services\{CanonicalAuditPolicyRegistry,CanonicalAuditMetadataSanitizer,CorrelatableOperationCatalog,SourceModuleCatalog};
$rows=CanonicalAuditPolicyRegistry::canonicalRows();
if(count($rows)!==37 || hash('sha256',json_encode(array_slice($rows,0,29),JSON_UNESCAPED_SLASHES))!=='72fbcb7bcc953ea039043224d37c626f2d41b46cb4721e08e33db2d7f46c684e')throw new RuntimeException('pre_MR6_policies_changed');
foreach([Service::DOWNLOAD=>'MEDIA_REVIEW_SOURCE_DOWNLOADED',Service::CORRECT=>'MEDIA_REVIEW_CORRECTED_UPLOADED'] as $cap=>$event){
 $requirement=Service::requirement($cap);$policy=CanonicalAuditPolicyRegistry::canonical()->assertAllowed($event,'SUCCESS','ADMIN_DECISION');
 if($requirement->riskLevel()!=='R1'||!$requirement->auditTrailRequired()||$requirement->capabilitiesRequired()->values()!==[$cap]||$policy['severity']!=='WARN'||!$policy['session_required']||!$policy['actor_required'])throw new RuntimeException('explicit_audited_requirement');
 if((new SourceModuleCatalog())->moduleForEvent($event)!=='MEDIA'||!(new CorrelatableOperationCatalog())->operationForEvent($event))throw new RuntimeException('catalog_mapping');
 try{(new CanonicalAuditMetadataSanitizer())->sanitize(['storage_key'=>'private/forbidden'],$policy['allowed_producer_metadata']);throw new LogicException('private_key_allowed');}catch(InvalidArgumentException){}
}
echo "PASS: 29 prior policy rows unchanged; two explicit R1 audited capabilities; canonical catalogs; private metadata rejected\n";
