<?php
declare(strict_types=1);
require_once __DIR__.'/ase-adm02-workflow-runtime.php';
/** Owned disposable composition only. No normal/runtime selector or generated secret. */
function workspace02OwnedServices():array
{
    if(getenv('MXMED_ADVISOR_WORKSPACE02_REVIEW')!=='1')throw new RuntimeException('physician_request_owned_required');
    [$identity]=staffWorkflowOwnedServices();$owned=realpath((string)getenv('ASE_INT02A_OWNED'));$p=$identity->pdo();
    require_once __DIR__.'/../modules/media/storage/LocalPersistentPrivateMediaStorage.php';
    foreach(['workspace02-evidence-key','workspace02-proof-key'] as $file)if(is_link($owned.'/'.$file)||!is_file($owned.'/'.$file)||strlen(file_get_contents($owned.'/'.$file))!==32||(fileperms($owned.'/'.$file)&0077)!==0)throw new RuntimeException('physician_request_owned_required');
    $root=realpath($owned.'/workspace02-private');if($root!==$owned.'/workspace02-private'||!is_dir($root)||(fileperms($root)&0077)!==0)throw new RuntimeException('physician_request_owned_required');
    $security=new Identity\Services\GovernanceSecurityContext($identity->sessions(),$identity->authentication());
    $mfa=new Identity\Services\StaffMfaService($p,base64_encode(file_get_contents($owned.'/workflow-mfa-key')),'synthetic-workflow-mfa',security:$security);
    $authority=new Identity\Accreditation\EvidenceAuthority($p,$security);
    $cipher=new Identity\Accreditation\EvidenceCipher('workspace02-synthetic',['workspace02-synthetic'=>file_get_contents($owned.'/workspace02-evidence-key')],'synthetic-owned-disposal-only');
    $storage=new Media\Storage\LocalPersistentPrivateMediaStorage($root);
    $composition=new Identity\Accreditation\PhysicianAdmissionComposition($p,$security,$cipher,$storage,$mfa);
    $enrollment=new Identity\Accreditation\PublicEnrollmentService($authority,$cipher,file_get_contents($owned.'/workspace02-proof-key'));
    $requests=new Identity\PhysicianRequests\RequestService($authority,$enrollment,$cipher,$mfa,$storage);
    return compact('identity','security','mfa','authority','cipher','storage','composition','enrollment','requests');
}
