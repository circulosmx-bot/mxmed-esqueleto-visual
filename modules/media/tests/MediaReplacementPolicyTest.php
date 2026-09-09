<?php
declare(strict_types=1);
require __DIR__.'/../services/MediaReplacementService.php';
use Platform\Services\{CanonicalAuditPolicyRegistry,CanonicalAuditMetadataSanitizer,CorrelatableOperationCatalog,SourceModuleCatalog};
$rows=CanonicalAuditPolicyRegistry::canonicalRows();
if(count($rows)!==36||hash('sha256',json_encode(array_slice($rows,0,35),JSON_UNESCAPED_SLASHES))!=='cc4904651b6bc93e07dd0846fbe2fdbe905473ff68e6de4ebb5ba01a2881fd89')throw new RuntimeException('prior_35_policies_changed');
$r=Media\Services\MediaReplacementService::requirement();$event='MEDIA_REVIEW_REPLACEMENT_REQUESTED';$policy=CanonicalAuditPolicyRegistry::canonical()->assertAllowed($event,'SUCCESS','ADMIN_DECISION');
if($r->riskLevel()!=='R1'||!$r->auditTrailRequired()||$r->capabilitiesRequired()->values()!==['media_review_request_replacement']||$policy['allowed_producer_metadata']!==['submission_id','physician_id','purpose','reason_code']||(new CorrelatableOperationCatalog())->operationForEvent($event)!=='MEDIA_REVIEW_REPLACEMENT'||(new SourceModuleCatalog())->moduleForEvent($event)!=='MEDIA')throw new RuntimeException('replacement_policy');
foreach(['feedback','review_feedback','storage_key','session_token','EXIF','binary'] as $key)try{(new CanonicalAuditMetadataSanitizer())->sanitize([$key=>'secret'],$policy['allowed_producer_metadata']);throw new LogicException('metadata_allowed');}catch(InvalidArgumentException){}
echo "PASS 35 prior policies unchanged; exact R1 replacement authority; feedback excluded from audit\n";
