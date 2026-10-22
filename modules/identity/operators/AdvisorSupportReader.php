<?php
declare(strict_types=1);
namespace Identity\Operators;

use Identity\Accreditation\EvidenceAuthority;
use Identity\Contracts\AuthenticatedAccessContext;
use Identity\PhysicianRequests\RequestAuthority;
use Identity\Repositories\InternalOperatorGrantRepository;
use Media\Services\AdvisorWorkspaceService;
use PDO;

/** Bounded diagnostic reader. Never composes an owner writer or expires invitations. */
final class AdvisorSupportReader
{
    public const CAPABILITY='physician_operator_support_read';
    public function __construct(private EvidenceAuthority $a){}
    private function rows(string $sql,array $args=[]):array{return $this->a->query($sql,$args)->fetchAll(PDO::FETCH_ASSOC);}
    public function read(AuthenticatedAccessContext $actor,string $profile):array
    {
        if(!preg_match('/^[a-zA-Z0-9_-]{1,64}$/D',$profile))throw new \InvalidArgumentException('workspace_invalid_request');
        return $this->a->transaction($actor,function()use($actor,$profile){
            (new RequestAuthority($this->a->pdo,$this->a->clock))->assertActiveStaff($actor,true);
            $caps=(new InternalOperatorGrantRepository($this->a->pdo))->activeCapabilities($actor->accountId(),true);
            if(!$caps->contains(self::CAPABILITY)||($this->a->scope!==null&&!$this->a->scope->allowsStaff($actor->accountId(),self::CAPABILITY)))throw new \RuntimeException('operator_support_denied');
            $this->a->scope?->profile($profile);
            // The new grant cannot discover additional profiles. Revalidate the existing
            // Requests / Media context within this transaction, including scoped applicants.
            $inScope=false;
            if($caps->contains('media_review_read')){
                require_once dirname(__DIR__,2).'/media/services/AdvisorWorkspaceService.php';
                $inScope=$this->rows('SELECT s.submission_id FROM media_review_submissions s JOIN profiles_doctors p ON p.doctor_id=s.owner_id WHERE s.owner_id=? AND '.AdvisorWorkspaceService::scope().' LIMIT 1',[$profile])!==[];
            }
            if(!$inScope&&$caps->contains(RequestAuthority::READ)){
                $rows=$this->rows("SELECT o.account_id FROM auth_physician_enrollment_origins o LEFT JOIN auth_physician_profile_request_profiles l ON l.origin_id=o.origin_id WHERE o.status='BOUND' AND o.intake_state='SUBMITTED' AND o.account_id<>? AND COALESCE(o.original_profile_doctor_id,l.profile_doctor_id)=?",[$actor->accountId(),$profile]);
                foreach($rows as $row)if($this->a->scope===null||$this->a->scope->accepts($profile,$row['account_id']))$inScope=true;
            }
            if(!$inScope)throw new \RuntimeException('operator_support_scope_denied');
            // Driver casing differs across MySQL versions; inspect names explicitly.
            $tables=$this->a->query('SELECT table_name FROM information_schema.tables WHERE table_schema=DATABASE()')->fetchAll(PDO::FETCH_COLUMN);
            $required=['auth_account_memberships','auth_profile_operator_invitations','auth_profile_operator_grant_revisions','auth_profile_operator_grants'];
            if(array_diff($required,$tables))throw new \RuntimeException('operator_support_schema_unavailable');
            $now=$this->a->sqlTime();
            $members=$this->rows("SELECT m.membership_id,m.account_id,m.role_code,m.status,m.assignment_source,a.status account_status,(a.email_verified_at IS NOT NULL) contact_verified FROM auth_account_memberships m JOIN auth_accounts a ON a.account_id=m.account_id WHERE m.profile_doctor_id=? AND m.entity_group_id IS NULL AND m.scope_code IN ('profile','profile_doctor') ORDER BY m.created_at,m.membership_id LIMIT 501",[$profile]);
            if(count($members)>500)throw new \RuntimeException('operator_support_inventory_unavailable');
            $owners=array_values(array_filter($members,static fn($m)=>$m['role_code']==='owner'&&$m['status']==='active'));
            $owner=count($owners)===1?$owners[0]:null;
            $ownerValid=$owner!==null&&$owner['account_status']==='active'&&(bool)$owner['contact_verified'];
            $presentations=in_array('auth_profile_operator_presentations',$tables,true)?$this->rows('SELECT membership_id,invitation_id,alias_display,seat_position,released_at FROM auth_profile_operator_presentations WHERE profile_doctor_id=?',[$profile]):[];
            foreach($members as &$m){
                $m['contact_verified']=(bool)$m['contact_verified'];$m['alias']=null;$m['seat_position']=null;
                foreach($presentations as $p)if($p['membership_id']===$m['membership_id']){$m['alias']=$p['alias_display'];$m['seat_position']=$p['seat_position']===null?null:(int)$p['seat_position'];}
                $rev=$this->rows('SELECT revision_id,revision,status,owner_membership_id,valid_from,valid_until FROM auth_profile_operator_grant_revisions WHERE membership_id=? ORDER BY revision DESC LIMIT 1',[$m['membership_id']])[0]??null;
                $m['grant_revision']=$rev===null?null:(int)$rev['revision'];$m['grant_status']=$rev['status']??'missing';
                $m['delegated_functions']=$rev===null?[]:array_column($this->rows('SELECT capability FROM auth_profile_operator_grants WHERE revision_id=? ORDER BY capability',[$rev['revision_id']]),'capability');
                $m['delegation_current']=$ownerValid&&$m['status']==='active'&&$m['account_status']==='active'&&$m['contact_verified']&&($rev['status']??'')==='active'&&$rev['owner_membership_id']===$owner['membership_id']&&$rev['valid_from']<=$now&&($rev['valid_until']===null||$rev['valid_until']>$now);
                $m['next_action']=match(true){$m['role_code']==='owner'=>'La titularidad se gestiona por su flujo gobernado independiente.',!$m['contact_verified']=>'La persona debe verificar su cuenta mediante Identity.',$m['account_status']!=='active'=>'La persona debe resolver el estado de su cuenta mediante el flujo de acceso.', $m['status']==='suspended'=>'El titular debe revisar la suspensión desde su panel.', $m['status']==='revoked'=>'La relación fue dada de baja; el titular debe evaluar una nueva invitación.',!$m['delegation_current']=>'El titular debe revisar la delegación y su vigencia desde su panel.',default=>'Consultar las funciones delegadas; el acceso final también depende del plan y del consumidor autorizado.'};
            }unset($m);
            $invitations=$this->rows('SELECT invitation_id,owner_membership_id,role_code,status,created_at,expires_at,closed_at,accepted_membership_id,offered_capabilities FROM auth_profile_operator_invitations WHERE profile_doctor_id=? ORDER BY created_at DESC,invitation_id LIMIT 101',[$profile]);
            $more=count($invitations)>100;$invitations=array_slice($invitations,0,100);
            foreach($invitations as &$i){
                $i['offered_functions']=json_decode($i['offered_capabilities'],true,16,JSON_THROW_ON_ERROR);unset($i['offered_capabilities']);
                $i['display_status']=$i['status']==='pending'&&$i['expires_at']<=$now?'expired':$i['status'];
                $i['alias']=null;foreach($presentations as $p)if($p['invitation_id']===$i['invitation_id'])$i['alias']=$p['alias_display'];
                $i['account_linked']=$i['status']==='accepted'&&$i['accepted_membership_id']!==null;
                $i['owner_current']=$ownerValid&&$i['owner_membership_id']===$owner['membership_id'];
                $i['next_action']=match($i['display_status']){'accepted'=>'La aceptación quedó registrada. Consulta el estado actual de la relación y sus permisos.','pending'=>$i['owner_current']?'La persona invitada debe aceptar con su propia cuenta verificada. El titular puede revisar o cancelar la invitación.':'La invitación requiere revisión por el titular actual.','expired','cancelled','rejected'=>'El titular debe evaluar si corresponde emitir una nueva invitación.',default=>'Consultar el flujo canónico con el titular.'};
            }unset($i);
            // Exact policy from ProfileOperatorService::seats: distinct non-owner people
            // plus unexpired pending reservations; suspended people retain their position.
            $occupied=(int)$this->a->query("SELECT COUNT(DISTINCT m.account_id) FROM auth_account_memberships m WHERE m.profile_doctor_id=? AND m.entity_group_id IS NULL AND m.status IN ('pending','active','suspended') AND NOT EXISTS(SELECT 1 FROM auth_account_memberships o WHERE o.account_id=m.account_id AND o.profile_doctor_id=m.profile_doctor_id AND o.role_code='owner' AND o.status='active')",[$profile])->fetchColumn();
            $reserved=(int)$this->a->query("SELECT COUNT(*) FROM auth_profile_operator_invitations WHERE profile_doctor_id=? AND status='pending' AND expires_at>?",[$profile,$now])->fetchColumn();
            $events=$this->rows("SELECT event_id,action,occurred_at_utc,JSON_UNQUOTE(JSON_EXTRACT(metadata_json,'$.producer_metadata.operator_action')) operation FROM platform_audit_events WHERE resource_type='profile_doctor' AND resource_reference=? AND outcome='SUCCESS' AND action IN ('INVITATION_CREATED','INVITATION_ACCEPTED','INVITATION_REVOKED','PROFILE_OPERATOR_DELEGATION_CHANGED') AND JSON_UNQUOTE(JSON_EXTRACT(metadata_json,'$.producer_metadata.profile_doctor_id'))=? ORDER BY occurred_at_utc DESC,event_id DESC LIMIT 100",[$profile,$profile]);
            return ['doctor_id'=>$profile,'owner'=>$owner,'owner_state'=>$ownerValid?'confirmed':(count($owners)>1?'ambiguous':'unavailable'),'members'=>$members,'invitations'=>$invitations,'invitations_has_more'=>$more,'events'=>$events,'seats'=>['limit'=>2,'occupied'=>$occupied,'reserved'=>$reserved,'available'=>max(0,2-$occupied-$reserved),'consistent'=>$occupied+$reserved<=2],'support_actions'=>[],'access_note'=>'Consulta de soporte. Las funciones delegadas no garantizan acceso: el consumidor valida cuenta, relación, titular, vigencia y plan. Sólo el titular puede administrar operadores.'];
        });
    }
}
