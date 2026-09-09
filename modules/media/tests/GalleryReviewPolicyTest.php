<?php
declare(strict_types=1);
require __DIR__.'/../services/GalleryApprovalService.php';
use Platform\Services\{CanonicalAuditPolicyRegistry,CorrelatableOperationCatalog,SourceModuleCatalog};
$rows=CanonicalAuditPolicyRegistry::canonicalRows();
if(count($rows)!==37||hash('sha256',json_encode(array_slice($rows,0,36),JSON_UNESCAPED_SLASHES))!=='bd49aac6e047504429b95a3d0df813e61fb3651d75ac5991e5f49790182857e1')throw new RuntimeException('prior_36_policies_changed');
$r=Media\Services\GalleryApprovalService::requirement();$e='MEDIA_DOCTOR_GALLERY_APPROVED';$policy=CanonicalAuditPolicyRegistry::canonical()->assertAllowed($e,'SUCCESS','ADMIN_DECISION');
if(!$r->auditTrailRequired()||$r->riskLevel()!=='R1'||$r->capabilitiesRequired()->values()!==['media_review_approve','media_review_read']||$policy['allowed_producer_metadata']!==['submission_id','physician_id','published_media_id']||(new SourceModuleCatalog())->moduleForEvent($e)!=='MEDIA'||(new CorrelatableOperationCatalog())->operationForEvent($e)!=='MEDIA_DOCTOR_GALLERY_APPROVAL')throw new RuntimeException('gallery_policy');
echo "PASS previous 36 policies unchanged; individual gallery approval R1/canonical audit\n";
