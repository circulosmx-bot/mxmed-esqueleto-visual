<?php
declare(strict_types=1);
require __DIR__.'/../services/PhysicianLogoApprovalService.php';
use Platform\Services\{CanonicalAuditPolicyRegistry,CorrelatableOperationCatalog,SourceModuleCatalog,CanonicalAuditMetadataSanitizer};
$rows=CanonicalAuditPolicyRegistry::canonicalRows();
if(count($rows)!==35 || hash('sha256',json_encode(array_slice($rows,0,31),JSON_UNESCAPED_SLASHES))!=='8fa58315fcfcf038d3e20a664d2904a60a2e9fc9c9bf9a1dd137180d358025ce')throw new RuntimeException('prior_31_policies_changed');
$event='MEDIA_PHYSICIAN_LOGO_APPROVED';$policy=CanonicalAuditPolicyRegistry::canonical()->assertAllowed($event,'SUCCESS','ADMIN_DECISION');$req=Media\Services\PhysicianLogoApprovalService::requirement();
if($req->riskLevel()!=='R1'||!$req->auditTrailRequired()||$req->capabilitiesRequired()->values()!==['media_review_approve','media_review_read']||$policy['severity']!=='WARN'||!$policy['session_required']||!$policy['actor_required'])throw new RuntimeException('approval_contract');
if((new SourceModuleCatalog())->moduleForEvent($event)!=='MEDIA'||(new CorrelatableOperationCatalog())->operationForEvent($event)!=='MEDIA_PHYSICIAN_LOGO_APPROVAL')throw new RuntimeException('mapping');
foreach(['storage_key','path','source_exif','binary'] as $key){try{(new CanonicalAuditMetadataSanitizer())->sanitize([$key=>'private'], $policy['allowed_producer_metadata']);throw new LogicException('metadata_leak');}catch(InvalidArgumentException){}}
echo "PASS MR7 R1 canonical audit, exact existing capabilities, safe metadata; prior 31 policies unchanged\n";
