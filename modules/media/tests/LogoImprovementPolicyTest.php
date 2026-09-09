<?php
declare(strict_types=1);
require __DIR__.'/../services/LogoImprovementService.php';
use Platform\Services\{CanonicalAuditPolicyRegistry,CorrelatableOperationCatalog,SourceModuleCatalog,CanonicalAuditMetadataSanitizer};
$rows=CanonicalAuditPolicyRegistry::canonicalRows();
if(count($rows)!==37||hash('sha256',json_encode(array_slice($rows,0,32),JSON_UNESCAPED_SLASHES))!=='9680de361389894d730dbbc534adc5c1735a5af46f943005bd7bfa52ce4993c1')throw new RuntimeException('prior_policy_changed');
foreach(['generate'=>'PROPOSED','accept'=>'ACCEPTED','discard'=>'DISCARDED'] as $action=>$suffix){
 $event='MEDIA_LOGO_IMPROVEMENT_'.$suffix;$policy=CanonicalAuditPolicyRegistry::canonical()->assertAllowed($event,'SUCCESS','ADMIN_DECISION');$r=Media\Services\LogoImprovementService::requirement($action);
 if($r->riskLevel()!=='R1'||!$r->auditTrailRequired()||$r->capabilitiesRequired()->values()!==['media_review_improve']||$policy['severity']!=='WARN'||!$policy['session_required']||(new SourceModuleCatalog())->moduleForEvent($event)!=='MEDIA'||(new CorrelatableOperationCatalog())->operationForEvent($event)!=='MEDIA_LOGO_IMPROVEMENT')throw new RuntimeException('policy_contract');
 foreach(['path','storage_key','binary','EXIF','session_token'] as $key)try{(new CanonicalAuditMetadataSanitizer())->sanitize([$key=>'secret'],$policy['allowed_producer_metadata']);throw new LogicException('leak');}catch(InvalidArgumentException){}
}
echo "PASS 32 prior policies preserved; 3 R1 audited improve-only actions; safe metadata\n";
