<?php
declare(strict_types=1);
require_once __DIR__.'/../modules/identity/http/IdentityHttpComposition.php';
\Identity\Http\IdentityHttpComposition::registerAutoloader();
/** Only the owned allowlisted preview router includes this file. No production selector. */
function staffWorkflowOwnedServices():array
{
    $owned=realpath((string)getenv('ASE_INT02A_OWNED'));
    if(!$owned||!preg_match('~^/private/tmp/mxmed-ase-adm02imp01-[a-zA-Z0-9_]+$~D',$owned)||fileowner($owned)!==posix_geteuid()||(fileperms($owned)&0777)!==0700)throw new RuntimeException('workflow_owned_required');
    $path=$owned.'/workflow-approval.json';if(is_link($path)||!is_file($path)||(fileperms($path)&0777)!==0600)throw new RuntimeException('workflow_owned_required');
    $v=json_decode(file_get_contents($path),true,16,JSON_THROW_ON_ERROR);
    if(($v['expires']??0)<=time()||($v['root']??'')!==realpath(__DIR__.'/..')||($v['database']??'')!==getenv('MXMED_DB_NAME')||!preg_match('/^mxmed_gate4d_preview_aseadm02imp01_[a-f0-9]{10}$/D',$v['database']))throw new RuntimeException('workflow_owned_required');
    foreach($v['source_hashes'] as $file=>$hash)if(!hash_equals($hash,hash_file('sha256',__DIR__.'/../'.$file)))throw new RuntimeException('workflow_source_drift');
    $i=\Identity\Http\IdentityHttpComposition::fromProcessEnvironment();$p=$i->pdo();$p->setAttribute(PDO::ATTR_EMULATE_PREPARES,false);
    if(realpath((string)$p->query('SELECT @@datadir')->fetchColumn())!==$owned.'/mysql')throw new RuntimeException('workflow_owned_required');
    $sessions=new \Identity\Http\CanonicalHttpSessionResolver($i->sessions());$security=new \Identity\Services\GovernanceSecurityContext($i->sessions(),$i->authentication());
    $mfa=new \Identity\Services\StaffMfaService($p,base64_encode(file_get_contents($owned.'/workflow-mfa-key')),'synthetic-workflow-mfa',security:$security);
    $root=realpath($owned.'/staff-private');
    $policy=new \Identity\StaffMedia\Policy(65536,512,65536,800,153600,'synthetic-custody-only','synthetic-owned-disposal-only',file_get_contents($owned.'/media-operation-key'));
    $cipher=new \Identity\StaffMedia\Cipher('synthetic-media-v1',['synthetic-media-v1'=>file_get_contents($owned.'/media-key')]);$storage=new \Identity\StaffMedia\Storage($root,153640);
    $media=new \Identity\StaffMedia\Service($p,$sessions,$policy,$cipher,$storage,new \Identity\StaffMedia\Images($policy,$root),$root,new \Identity\StaffMedia\Authority($p));
    $flow=new \Identity\StaffWorkflow\Service($p,$sessions,$mfa,$security,$cipher,$storage,'synthetic-staff-acceptance-v1');
    $delivery=new class($owned) implements \Identity\StaffAdmission\InvitationDeliveryPort {
        public function __construct(private string $owned){}
        public function deliver(string $id,string $case,string $email,string $token,string $expiry):void {
            if(!str_ends_with($email,'@example.test'))throw new RuntimeException('synthetic_recipient_required');
            $f=fopen($this->owned.'/invitation-'.$id.'.json','x');if(!$f)throw new RuntimeException('delivery_conflict');
            fwrite($f,json_encode(['invitation_id'=>$id,'case_id'=>$case,'recipient'=>$email,'token'=>$token,'expires_at'=>$expiry],JSON_THROW_ON_ERROR));fclose($f);
        }
    };
    $clock=new \Identity\Contracts\SystemClock();
    $limits=new \Identity\Services\RateLimitService($p,new \Identity\Contracts\RateLimitKeyHasher((string)getenv('MXMED_PREVIEW_PEPPER')),$clock);
    $ip=new \Identity\StaffAdmission\InvitationPolicy(3600,'synthetic-preview-validity','synthetic-recipient',file_get_contents($owned.'/recipient-key'));
    $invites=new \Identity\StaffAdmission\InvitationService($p,$sessions,$ip,$clock,$limits,$delivery);
    return [$i,$flow,new \Identity\StaffAdmission\Service($p,$sessions,getenv('MXMED_STAFF_CONTROL04')==='1'?new \Identity\StaffAdmission\Geography($p):null),$invites,$media,new \Identity\Services\InternalGovernanceService($p,$sessions)];
}
