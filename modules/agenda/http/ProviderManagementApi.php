<?php
declare(strict_types=1);
namespace Agenda\Http;

use Agenda\Services\HealthcareOrganizationDirectoryService as Directory;
use Agenda\Services\HealthcareOrganizationManagementAuthorizationService as Policy;
use Agenda\Services\HealthcareOrganizationTeamService as Team;
use Agenda\Services\HealthcareProviderCoverageService as Coverage;
use Agenda\Services\HealthcareOrganizationInviteeResolutionService as InviteeResolution;
use Identity\Contracts\AuthenticatedAccessContext;
use PDO;
use RuntimeException;
use Subscriptions\Services\ProviderCommercialEntitlementService as Entitlement;

require_once __DIR__.'/../services/HealthcareOrganizationDirectoryService.php';
require_once __DIR__.'/../services/HealthcareOrganizationTeamService.php';
require_once __DIR__.'/../services/HealthcareProviderCoverageService.php';
require_once __DIR__.'/../services/HealthcareOrganizationInviteeResolutionService.php';
require_once __DIR__.'/../../subscriptions/services/ProviderCommercialEntitlementService.php';
require_once __DIR__.'/../../../api/_lib/clinical_study_contract.php';
require_once __DIR__.'/../../../api/_lib/clinical_study_catalog_read.php';

final class ProviderManagementHttpException extends RuntimeException {
    public function __construct(public readonly int $status,public readonly string $error) { parent::__construct($error); }
}

/** The HTTP entrypoint provides only a canonical session context, never a payload actor ID. */
final class ProviderManagementApi {
    private Policy $policy; private Directory $directory; private Coverage $coverage; private Team $team; private Entitlement $entitlements; private InviteeResolution $invitees;
    private const LOCATION_FIELDS=['branch_name','street','exterior_number','interior_number','postal_code','colonia','municipality','state_name','latitude','longitude','coordinate_source','phone'];
    private const OFFERING_FIELDS=['service_mode','requires_appointment','preparation_instructions'];
    public function __construct(private PDO $pdo) {
        $this->entitlements=new Entitlement($pdo); $this->policy=new Policy($pdo,$this->entitlements);
        $this->directory=new Directory($pdo); $this->coverage=new Coverage($pdo); $this->team=new Team($pdo);
        $this->invitees=new InviteeResolution($pdo);
    }
    /** @return array{int,array} */
    public function handle(string $method,array $path,array $query,array $body,?AuthenticatedAccessContext $actor): array {
        if ($actor===null) $this->fail(401,'UNAUTHENTICATED');
        if ($path===['me','organizations'] && $method==='GET') return [200,['organizations'=>$this->myOrganizations($actor)]];
        if ($path===['me','invitations'] && $method==='GET') return [200,['invitations'=>$this->team->listOwnPendingInvitations($actor)]];
        if ($path===['study-types'] && $method==='GET') {
            if ($this->myOrganizations($actor)===[]) $this->fail(403,'FORBIDDEN');
            return [200,\clinical_study_catalog_read($this->pdo,$query)];
        }
        if (($path[0]??'')==='invitations' && count($path)===2 && $method==='GET') return [200,$this->publicInvitation($this->team->readOwnInvitation($actor,$path[1]))];
        if (($path[0]??'')==='invitations' && count($path)===3 && $path[2]==='accept' && $method==='POST') {
            $this->keys($body,[]); return [200,$this->publicInvitation($this->team->accept($actor,$path[1]))];
        }
        if (($path[0]??'')!=='organizations' || !isset($path[1]) || !preg_match('/^[A-Za-z0-9._:-]{1,64}$/D',$path[1])) $this->fail(404,'NOT_FOUND');
        $group=$path[1]; $route=array_slice($path,2);
        if ($route===[] && $method==='GET') return [200,$this->context($actor,$group)];
        if ($route===['subscription'] && $method==='GET') return [200,$this->subscription($actor,$group)];
        if ($route===['study-types'] && $method==='GET') { $this->requireProviderMember($actor,$group); return [200,\clinical_study_catalog_read($this->pdo,$query)]; }
        if ($route===['team'] && $method==='GET') { $this->permit($actor,$group,Policy::TEAM);return [200,['members'=>array_map($this->publicMember(...),$this->team->listMembers($actor,$group))]]; }
        if ($route===['invitations'] && $method==='GET') { $this->permit($actor,$group,Policy::TEAM);return [200,['invitations'=>array_map($this->publicInvitation(...),$this->team->listPendingInvitations($actor,$group))]]; }
        if ($route===['invitee-resolution'] && $method==='POST') {
            $this->permit($actor,$group,Policy::TEAM);$this->keys($body,['email']);
            return [200,$this->invitees->resolve($actor,$group,$this->string($body,'email'))];
        }
        if ($route===['invitations'] && $method==='POST') {
            $this->permit($actor,$group,Policy::TEAM);
            $this->keys($body,['invitee_account_id','invitee_email','role','submission_key']);
            if (isset($body['invitee_email'])) {
                if (isset($body['invitee_account_id'])) $this->fail(422,'AMBIGUOUS_INVITEE');
                $result=$this->invitees->inviteByEmail($actor,$group,$this->string($body,'invitee_email'),
                    $this->string($body,'role'),$this->string($body,'submission_key'));
                if ($result['state']!=='INVITATION_CREATED') $this->fail($result['state']==='NOT_FOUND_OR_NOT_INVITABLE'?422:409,$result['state']);
                return [201,$result['invitation']];
            }
            return [201,$this->publicInvitation($this->team->invite($actor,$group,$this->string($body,'invitee_account_id'),
                $this->string($body,'role'),$this->string($body,'submission_key')))];
        }
        if (count($route)===3 && $route[0]==='invitations' && $route[2]==='revoke' && $method==='POST') {
            $this->permit($actor,$group,Policy::TEAM); $this->keys($body,[]);
            if (!in_array($route[1],array_column($this->team->listPendingInvitations($actor,$group),'invitation_uuid'),true)) $this->fail(404,'NOT_FOUND');
            return [200,$this->publicInvitation($this->team->revokeInvitation($actor,$route[1]))];
        }
        if (count($route)===3 && $route[0]==='members' && in_array($route[2],['suspend','revoke'],true) && $method==='POST') {
            $this->permit($actor,$group,Policy::TEAM); $this->keys($body,[]);
            return [200,$route[2]==='suspend'?$this->team->suspendMember($actor,$group,$route[1]):$this->team->revokeMember($actor,$group,$route[1])];
        }
        if ($route===['locations']) {
            $this->permit($actor,$group,Policy::LOCATIONS);
            if ($method==='GET') return [200,['locations'=>$this->directory->readOrganization($group)['locations']]];
            if ($method==='POST') { $key=$this->submission($body);unset($body['submission_key']);$this->keys($body,array_merge(self::LOCATION_FIELDS,['operational_state']));
                return [201,$this->retry($actor,$group,'location_create',$key,$body,fn()=> $this->locationFields($this->directory->createLocation($group,$body)))]; }
        }
        if (($route[0]??'')!=='locations' || !isset($route[1])) $this->fail(404,'NOT_FOUND');
        $uuid=$route[1];$this->permit($actor,$group,Policy::LOCATIONS,'location',$uuid);$location=$this->location($group,$uuid);
        if (count($route)===2) {
            if ($method==='GET') return [200,$location];
            if ($method==='PATCH') { $this->keys($body,self::LOCATION_FIELDS);return [200,$this->locationFields($this->directory->updateLocationForProvider($group,$uuid,$body,$actor->accountId()))]; }
        }
        if (count($route)===3 && $route[2]==='state' && $method==='PATCH') {
            $this->exactState($body); return [200,$this->locationFields($this->directory->updateLocation($group,$uuid,$body))];
        }
        if (($route[2]??'')!=='offerings') $this->fail(404,'NOT_FOUND');
        $this->permit($actor,$group,Policy::OFFERINGS,'location',$uuid);
        if (count($route)===3) {
            if ($method==='GET') return [200,['offerings'=>$location['offerings']]];
            if ($method==='POST') { $key=$this->submission($body);unset($body['submission_key']);$this->keys($body,array_merge(['study_type_id','operational_state'],self::OFFERING_FIELDS));
                $study=$this->id($body['study_type_id']??null);unset($body['study_type_id']);
                return [201,$this->retry($actor,$group,'offering_create:'.$uuid,$key,['study_type_id'=>$study]+$body,
                    function() use($group,$uuid,$study,$body) { $this->directory->createOffering($group,$uuid,$study,$body);return $this->publicOffering($this->offering($group,$uuid,$study)); })]; }
        }
        if (!isset($route[3])) $this->fail(404,'NOT_FOUND');
        $study=$this->id($route[3]);$offering=$this->offering($group,$uuid,$study);
        $this->permit($actor,$group,Policy::OFFERINGS,'offering',$offering['offering_id']);
        if (count($route)===4) {
            if ($method==='GET') return [200,$this->publicOffering($offering)];
            if ($method==='PATCH') { $this->keys($body,self::OFFERING_FIELDS);$this->directory->updateOffering($group,$uuid,$study,$body);return [200,$this->publicOffering($this->offering($group,$uuid,$study))]; }
        }
        if (count($route)===5 && $route[4]==='state' && $method==='PATCH') {
            $this->exactState($body);$this->directory->updateOffering($group,$uuid,$study,$body);return [200,$this->publicOffering($this->offering($group,$uuid,$study))];
        }
        if (($route[4]??'')!=='service-areas') $this->fail(404,'NOT_FOUND');
        $this->permit($actor,$group,Policy::SERVICE_AREAS,'offering',$offering['offering_id']);
        if (count($route)===5) {
            if ($method==='GET') return [200,['service_areas'=>array_map($this->publicArea(...),$offering['service_areas'])]];
            if ($method==='POST') { $key=$this->submission($body);unset($body['submission_key']);$this->keys($body,['scope_type','postal_code']);
                return [201,$this->retry($actor,$group,'area_create:'.$uuid.':'.$study,$key,$body,
                    fn()=> $this->publicArea($this->coverage->createServiceArea($group,$uuid,$study,$this->string($body,'scope_type'),$this->string($body,'postal_code'))))]; }
        }
        if (count($route)===7 && $route[6]==='state' && $method==='PATCH') {
            $area=$this->id($route[5]);if (!in_array($area,array_map(static fn($a)=>(int)$a['service_area_id'],$offering['service_areas']),true)) $this->fail(404,'NOT_FOUND');
            $this->permit($actor,$group,Policy::SERVICE_AREAS,'service_area',$area);$this->exactState($body);
            return [200,$this->publicArea($this->coverage->setServiceAreaOperationalState($group,$uuid,$study,$area,$this->string($body,'operational_state')))];
        }
        $this->fail(404,'NOT_FOUND');
    }
    private function myOrganizations(AuthenticatedAccessContext $actor): array {
        $stmt=$this->pdo->prepare("SELECT DISTINCT m.entity_group_id,g.display_name,g.organization_type_key
            FROM auth_account_memberships m JOIN medical_groups g ON g.group_id=m.entity_group_id
            WHERE m.account_id=? AND m.profile_doctor_id IS NULL AND m.scope_code='organization'
              AND m.status='active' ORDER BY g.display_name,m.entity_group_id");
        $stmt->execute([$actor->accountId()]);$items=[];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $group=$row['entity_group_id'];$team=$this->policy->evaluate($actor,$group,Policy::TEAM);
            if ($team['role']===null) continue;
            $loc=$this->policy->evaluate($actor,$group,Policy::LOCATIONS);
            $items[]=['group_id'=>$group,'display_name'=>$row['display_name'],'organization_type'=>$row['organization_type_key'],
                'member_role'=>$team['role'],'member_state'=>'active',
                'actions'=>['provider_team_manage'=>$team['allowed'],'provider_locations_manage'=>$loc['allowed'],
                    'locations_entitlement_required'=>$loc['eligible']&&!$loc['allowed']&&$loc['entitlement_required']]];
        }
        return $items;
    }
    private function requireProviderMember(AuthenticatedAccessContext $actor,string $group): void {
        if ($this->policy->evaluate($actor,$group,Policy::TEAM)['role']===null) $this->fail(404,'NOT_FOUND');
    }
    private function publicInvitation(array $invite): array {
        if (isset($invite['invitee_account_id'])) {
            $stmt=$this->pdo->prepare('SELECT email_address FROM auth_accounts WHERE account_id=?');
            $stmt->execute([$invite['invitee_account_id']]);
            $invite['invitee_email']=$stmt->fetchColumn()?:null;
            unset($invite['invitee_account_id']);
        }
        return $invite;
    }
    private function publicMember(array $member): array {
        $stmt=$this->pdo->prepare('SELECT email_address FROM auth_accounts WHERE account_id=?');
        $stmt->execute([$member['account_id']]);
        $member['account_email']=$stmt->fetchColumn()?:null;
        unset($member['account_id']);
        return $member;
    }
    private function context(AuthenticatedAccessContext $actor,string $group): array {
        $actions=[];$role=null;
        foreach ([Policy::TEAM,Policy::SUBSCRIPTION,Policy::PROFILE,Policy::LOCATIONS,Policy::OFFERINGS,Policy::SERVICE_AREAS] as $action) {
            $d=$this->policy->evaluate($actor,$group,$action);$role??=$d['role'];$actions[$action]=['allowed'=>$d['allowed'],'entitlement_required'=>$d['eligible']&&!$d['allowed']&&$d['entitlement_required']];
        }
        if ($role===null) $this->fail(404,'NOT_FOUND');
        $org=$this->directory->readOrganization($group);
        $status=$org['provider_status'];
        if (is_array($status)) $status['display_state_es']=$status['operational_state']==='INACTIVE'?'Inactivo':match($status['verification_state']) {
            'VERIFIED'=>'Verificado','REJECTED'=>'Rechazado',default=>'En revisión',
        };
        return ['group_id'=>$group,'organization_type'=>$org['organization_type_key'],'display_name'=>$org['display_name'],'member_role'=>$role,'member_state'=>'active',
            'provider_status'=>$status,'actions'=>$actions,'commercial_entitled'=>$actions[Policy::LOCATIONS]['allowed'],
            'location_count'=>count($org['locations']),'offering_count'=>array_sum(array_map(static fn($l)=>count($l['offerings']),$org['locations']))];
    }
    private function subscription(AuthenticatedAccessContext $actor,string $group): array {
        $context=$this->context($actor,$group);
        if ($context['member_role']==='collaborator') return ['actions'=>$context['actions']];
        $caps=[];foreach ([Policy::PROFILE,Policy::LOCATIONS,Policy::OFFERINGS,Policy::SERVICE_AREAS] as $action) $caps[$action]=$this->entitlements->hasCapability($group,$action);
        if ($context['member_role']==='administrator') return ['capabilities'=>$caps];
        $this->permit($actor,$group,Policy::SUBSCRIPTION);
        $stmt=$this->pdo->prepare("SELECT subscription_id,plan_code,billing_period,status,starts_at,expires_at,grace_ends_at FROM profile_subscriptions
            WHERE entity_type='provider_organization' AND entity_id=? AND deleted_at IS NULL ORDER BY created_at DESC LIMIT 1");$stmt->execute([$group]);
        return ['subscription'=>$stmt->fetch(PDO::FETCH_ASSOC)?:null,'capabilities'=>$caps];
    }
    private function permit(AuthenticatedAccessContext $actor,string $group,string $action,string $type='organization',string|int|null $id=null): void {
        $d=$this->policy->evaluate($actor,$group,$action,$type,$id);if ($d['allowed']) return;
        if ($d['role']===null) $this->fail(404,'NOT_FOUND');
        if ($d['eligible']&&$d['entitlement_required']&&!$d['entitlement_satisfied']) $this->fail(403,'ENTITLEMENT_REQUIRED');
        $this->fail(403,'FORBIDDEN');
    }
    private function location(string $group,string $uuid): array {
        foreach ($this->directory->readOrganization($group)['locations'] as $loc) if ($loc['location_uuid']===$uuid) return $loc;
        $this->fail(404,'NOT_FOUND');
    }
    private function offering(string $group,string $uuid,int $study): array {
        foreach ($this->location($group,$uuid)['offerings'] as $o) if ((int)$o['study_type_id']===$study) {
            $s=$this->pdo->prepare('SELECT o.offering_id FROM healthcare_organization_location_study_offerings o JOIN healthcare_organization_locations l ON l.location_id=o.location_id WHERE l.group_id=? AND l.location_uuid=? AND o.study_type_id=?');$s->execute([$group,$uuid,$study]);
            $o['offering_id']=(int)$s->fetchColumn();return $o;
        }
        $this->fail(404,'NOT_FOUND');
    }
    private function publicOffering(array $o): array {unset($o['offering_id']);$o['service_areas']=array_map($this->publicArea(...),$o['service_areas']);return $o;}
    private function publicArea(array $a): array {return ['service_area_id'=>(int)$a['service_area_id'],'scope_type'=>$a['scope_type'],'region_key'=>$a['region_key'],'postal_code'=>substr($a['region_key'],6),'operational_state'=>$a['operational_state'],'verification_state'=>$a['verification_state']];}
    private function locationFields(array $a): array {return array_intersect_key($a,array_flip(array_merge(['location_uuid','operational_state','verification_state'],self::LOCATION_FIELDS)));}
    private function keys(array $data,array $allowed): void {foreach (array_keys($data) as $key) if (!in_array($key,$allowed,true)) $this->fail(422,'UNSUPPORTED_FIELD');}
    private function exactState(array $body): void {$this->keys($body,['operational_state']);if (count($body)!==1) $this->fail(422,'INVALID_REQUEST');}
    private function string(array $data,string $key): string {if (!isset($data[$key])||!is_string($data[$key])||trim($data[$key])==='') $this->fail(422,'INVALID_REQUEST');return trim($data[$key]);}
    private function id(mixed $v): int {if (!is_int($v)&&(!is_string($v)||!ctype_digit($v))) $this->fail(422,'INVALID_ID');$n=(int)$v;if ($n<1) $this->fail(422,'INVALID_ID');return $n;}
    private function submission(array $body): string {$key=$this->string($body,'submission_key');if (!preg_match('/^[A-Za-z0-9._:-]{8,128}$/D',$key)) $this->fail(422,'INVALID_SUBMISSION_KEY');return $key;}
    private function fail(int $status,string $error): never {throw new ProviderManagementHttpException($status,$error);}
    private function retry(AuthenticatedAccessContext $actor,string $group,string $operation,string $key,array $input,callable $write): array {
        $hash=hash('sha256',json_encode($input,JSON_THROW_ON_ERROR));$this->pdo->beginTransaction();
        try {
            $this->pdo->prepare('INSERT INTO healthcare_organization_management_requests (account_id,group_id,operation_code,submission_key,request_hash)
                VALUES (?,?,?,?,?) ON DUPLICATE KEY UPDATE request_id=LAST_INSERT_ID(request_id)')->execute([$actor->accountId(),$group,$operation,$key,$hash]);
            $id=(int)$this->pdo->lastInsertId();$s=$this->pdo->prepare('SELECT request_hash,response_json FROM healthcare_organization_management_requests WHERE request_id=? FOR UPDATE');$s->execute([$id]);$row=$s->fetch(PDO::FETCH_ASSOC);
            if (!is_array($row)||!hash_equals($row['request_hash'],$hash)) $this->fail(409,'IDEMPOTENCY_CONFLICT');
            if ($row['response_json']!==null) {$result=json_decode($row['response_json'],true,512,JSON_THROW_ON_ERROR);$this->pdo->commit();return $result;}
            $result=$write();$this->pdo->prepare('UPDATE healthcare_organization_management_requests SET response_json=? WHERE request_id=?')->execute([json_encode($result,JSON_THROW_ON_ERROR),$id]);
            $this->pdo->commit();return $result;
        } catch (\Throwable $e) {if ($this->pdo->inTransaction()) $this->pdo->rollBack();throw $e;}
    }
}
