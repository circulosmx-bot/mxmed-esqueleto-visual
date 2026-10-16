<?php
declare(strict_types=1);
namespace Identity\Operators;

use Identity\Adapters\InMemoryIdentityNotificationAdapter;
use Identity\Contracts\{AccountMembership,AuthenticatedAccessContext,CanonicalProfileReference,Clock,IdentityAccount,NotificationMessage,OneTimeTokenPurpose,ProfileOperatorOwnershipBoundary};
use Identity\Http\CsrfTokenService;
use Identity\Repositories\AccountMembershipRepository;
use Identity\Services\{OneTimeTokenCodec,ProfileOperatorCapabilityCatalog,SessionService};
use Platform\Contracts\{CanonicalAuditEventInput,TrustedAuditContext};
use Platform\Services\CanonicalAuditWriter;
use Subscriptions\Services\CurrentSubscriptionReadModelService;
use Subscriptions\Repositories\CurrentSubscriptionRepository;
use PDO;

/**
 * Canonical OP-IMP01 authority shared by guarded synthetic factories and the
 * explicitly approved deployment composition. Every actor uses this same policy;
 * no legacy Agenda credential writer is supplied.
 */
final class ProfileOperatorService implements ProfileOperatorOwnershipBoundary
{
    private AccountMembershipRepository $memberships;
    private OperatorPresentationRepository $presentations;
    private bool $presentationReady;
    private bool $originReady;
    private const SOURCE = 'owner_operator_invitation';
    private const ACTIONS = ['invite','reissue','cancel','accept','reject','grants','suspend','reactivate','revoke','review','rename'];

    private function __construct(private PDO $pdo, private SessionService $sessions,
        private CsrfTokenService $csrf, private CanonicalAuditWriter $audit,
        private \Identity\Contracts\IdentityNotificationPort $notifications, private Clock $clock,
        private string $origin='https://op01.example.invalid', private ?OperatorInvitationRecipients $recipients=null, private bool $commercialOnly=false)
    {
        $this->recipients ??= OperatorInvitationRecipients::synthetic();
        $this->memberships = new AccountMembershipRepository($pdo);
        $this->presentations = new OperatorPresentationRepository($pdo);
        $this->presentationReady = $this->presentations->ready();
        $this->originReady = $this->presentationReady && (int)$pdo->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name='agenda_appointment_creator_snapshots'")->fetchColumn()===1;
    }

    public static function compose(PDO $pdo, SessionService $sessions, CsrfTokenService $csrf,
        CanonicalAuditWriter $audit, InMemoryIdentityNotificationAdapter $notifications, Clock $clock): self
    {
        $db=(string)$pdo->query('SELECT DATABASE()')->fetchColumn();
        if (PHP_SAPI!=='cli' || !preg_match('/^mxmed_op01_[a-f0-9]{10}$/D',$db))
            throw new \RuntimeException('operator_disposable_composition_required');
        // No real identities or profiles may be imported into this composition.
        if ((int)$pdo->query("SELECT COUNT(*) FROM auth_accounts WHERE email_normalized NOT LIKE '%@example.invalid'")->fetchColumn()!==0
            || (int)$pdo->query("SELECT COUNT(*) FROM profiles_doctors WHERE doctor_id NOT LIKE 'op01\\_%'")->fetchColumn()!==0)
            throw new \RuntimeException('operator_synthetic_fixture_required');
        return new self($pdo,$sessions,$csrf,$audit,$notifications,$clock);
    }

    /** Existing isolated HTTP factory retains its synthetic environment restrictions. */
    public static function isolated(\Identity\Http\IdentityHttpComposition $identity, CanonicalAuditWriter $audit, IsolatedInvitationOutbox $outbox): self
    {
        OperatorQaConfiguration::assertEnabled($identity->pdo());
        return new self($identity->pdo(),$identity->sessions(),$identity->csrf(),$audit,$outbox,new \Identity\Contracts\SystemClock(),OperatorQaConfiguration::origin());
    }

    /** Called only after the server deployment receipt/schema gate, never from request data. */
    public static function deployed(\Identity\Http\IdentityHttpComposition $identity,CanonicalAuditWriter $audit,TransactionalOperatorInvitationDelivery $delivery,OperatorDeploymentConfiguration $configuration):self
    {
        $configuration->assertConnection($identity->pdo());
        return new self($identity->pdo(),$identity->sessions(),$identity->csrf(),$audit,$delivery,new \Identity\Contracts\SystemClock(),$identity->allowedOrigin(),OperatorInvitationRecipients::canonical(),true);
    }

    /** All mutation inputs pass canonical Identity session + authenticated CSRF.
     * No actor, role, owner, membership authority or commercial plan comes from a body.
     * A successful idempotent replay returns only the saved receipt, never new access.
     */
    public function command(string $rawSession, string $csrf, string $origin, string $profile,
        string $commandId, string $action, array $input): array
    {
        if ($this->pdo->inTransaction()) throw new \RuntimeException('operator_outer_transaction_forbidden');
        if ($origin!==$this->origin || !in_array($action,self::ACTIONS,true)
            || !preg_match('/^[A-Za-z0-9_-]{8,64}$/D',$commandId)) throw new \RuntimeException('operator_request_denied');
        $actor=$this->authenticate($rawSession,$csrf);
        $isResponse=in_array($action,['accept','reject'],true);
        $reason=$input['reason']??null;
        if ($reason!==($isResponse?'INVITEE_DECISION':'OWNER_REQUEST')) throw new \InvalidArgumentException('operator_reason_required');
        // Hash the canonical payload, including token digest, without persisting its contents.
        ksort($input,SORT_STRING);
        $requestHash=hash('sha256',json_encode([$action,$input],JSON_THROW_ON_ERROR));
        foreach(['seat_position','presentation_id','personal_alias','creator_snapshot','creator_account_id','snapshot_version','origin_visual_key'] as $field)
            if(array_key_exists($field,$input))throw new \InvalidArgumentException('operator_presentation_client_authority_denied');
        if(!$this->presentationReady&&(array_key_exists('alias',$input)||$action==='rename'))throw new \RuntimeException('operator_presentation_not_ready');
        $notification=null;
        $correlation=$this->uuid();
        try {
            $this->pdo->beginTransaction();
            $this->lockProfile($profile); // Shared with governed owner transitions; first authority lock.
            $account=$this->account($actor);
            $owner=$this->owner($profile);
            if (!$isResponse && $owner['account_id']!==$actor->accountId()) throw new \RuntimeException('operator_owner_required');
            $prior=$this->one('SELECT request_hash,result_json FROM auth_profile_operator_commands WHERE profile_doctor_id=? AND actor_account_id=? AND command_id=? FOR UPDATE',[$profile,$actor->accountId(),$commandId]);
            if ($prior!==null) {
                if (!hash_equals($prior['request_hash'],$requestHash)) throw new \RuntimeException('operator_idempotency_conflict');
                $this->authenticate($rawSession,$csrf);
                $this->pdo->commit();
                return json_decode($prior['result_json'],true,512,JSON_THROW_ON_ERROR)+['replayed'=>true];
            }
            $this->expire($profile,$actor,$correlation);
            if ($isResponse) {
                $result=$this->respond($actor,$account,$profile,$owner,$action,$input,$correlation);
            } elseif (in_array($action,['invite','reissue'],true)) {
                $preferredPosition=null;
                if ($action==='reissue') {
                    $old=$this->invitation($profile,(string)($input['invitation_id']??''));
                    $this->pending($old,$owner);
                    if($this->presentationReady)$preferredPosition=$this->presentations->invitation($profile,$old['invitation_id'])['seat_position']??null;
                    $this->closeInvitation($old,'cancelled',$actor,$correlation,'OFFER_REPLACED');
                    if (($input['email']??'')!==$old['email_normalized']) throw new \RuntimeException('operator_reissue_contact_changed');
                }
                [$result,$notification]=$this->invite($actor,$profile,$owner,$input,$correlation,$preferredPosition===null?null:(int)$preferredPosition);
            } elseif ($action==='cancel') {
                $invite=$this->invitation($profile,(string)($input['invitation_id']??''));
                $this->pending($invite,$owner);
                $this->closeInvitation($invite,'cancelled',$actor,$correlation,'OWNER_REQUEST');
                $result=['invitation_id'=>$invite['invitation_id'],'status'=>'cancelled'];
            } elseif($action==='rename') {
                $m=$this->memberships->lockProfileMembership((string)($input['membership_id']??''));
                if(!$m||$m['profile_doctor_id']!==$profile||$m['account_id']===$actor->accountId()||$m['status']!=='active'||$m['assignment_source']!==self::SOURCE||$m['scope_code']!=='profile'||$m['entity_group_id']!==null||!in_array($m['role_code'],['administrator','collaborator'],true))throw new \RuntimeException('operator_membership_denied');
                if(!is_int($input['expected_presentation_revision']??null))throw new \InvalidArgumentException('operator_presentation_revision_required');
                $presentation=$this->presentations->rename($profile,$m['membership_id'],$input['expected_presentation_revision'],$input['alias']??null,$this->now());
                $this->event('rename',$actor,$profile,$correlation,$m['membership_id'],$presentation['invitation_id'],(int)$presentation['revision'],$owner['membership_id'],[],'OWNER_REQUEST',OperatorPresentationRepository::projection($presentation));
                $result=OperatorPresentationRepository::projection($presentation)+['membership_id'=>$m['membership_id']];
            } else {
                $result=$this->manage($actor,$profile,$owner,$action,$input,$correlation);
            }
            $result['correlation_id']=$correlation;
            if ($this->seats($profile)>2) throw new \RuntimeException('operator_quota_state_conflict');
            $this->exec('INSERT INTO auth_profile_operator_commands(profile_doctor_id,actor_account_id,command_id,request_hash,result_json,created_at) VALUES(?,?,?,?,?,?)',
                [$profile,$actor->accountId(),$commandId,$requestHash,json_encode($result,JSON_THROW_ON_ERROR),$this->now()]);
            $this->authenticate($rawSession,$csrf); // Revoked/expired session denies before commit.
            if ($notification!==null && $this->notifications instanceof TransactionalOperatorInvitationDelivery) {
                $this->notifications->send($notification);$notification=null;
            }
            if (!$this->pdo->commit()) throw new \RuntimeException('operator_commit_failed');
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $e;
        }
        // Only the final, in-memory adapter is accepted. No email, file or external dispatcher.
        if ($notification!==null) $this->notifications->send($notification);
        return $result;
    }

    /** Narrow decision only; does not execute Agenda actions or hydrate the private shell.
     * Resource's actual profile and commercial context must come from its trusted repository.
     * This foundation exposes neither a real consumer nor a client plan selector.
     */
    public function permits(string $session, string $profile, string $resourceProfile, string $capability): bool
    {
        if ($profile!==$resourceProfile || !in_array($capability,ProfileOperatorCapabilityCatalog::KEYS,true)) return false;
        $decision=$this->sessions->validate($session);
        if (!$decision->allowed()) return false;
        try {
            $owns=!$this->pdo->inTransaction();
            if($owns)$this->pdo->beginTransaction();
            $this->lockProfile($profile);
            $this->account($decision->context());
            $owner=$this->owner($profile);
            // Multiple technical role rows never imply extra access. Ambiguity fails closed.
            $rows=$this->all("SELECT * FROM auth_account_memberships WHERE account_id=? AND profile_doctor_id=? AND status IN ('pending','active','suspended') FOR UPDATE",[$decision->context()->accountId(),$profile]);
            if (count($rows)!==1) throw new \RuntimeException('operator_membership_ambiguous');
            $m=$rows[0];
            if ($m['status']!=='active'||$m['assignment_source']!==self::SOURCE||$m['scope_code']!=='profile'||$m['entity_group_id']!==null
                ||!in_array($m['role_code'],['administrator','collaborator'],true)) throw new \RuntimeException('operator_membership_denied');
            $r=$this->currentRevision($m['membership_id']);
            if ($r===null||$r['status']!=='active'||$r['owner_membership_id']!==$owner['membership_id']
                ||$r['valid_from']>$this->now()||($r['valid_until']!==null&&$r['valid_until']<=$this->now())) throw new \RuntimeException('operator_grant_denied');
            $i=$this->invitation($profile,$r['invitation_id']);
            if ($i['status']!=='accepted'||$i['accepted_membership_id']!==$m['membership_id']) throw new \RuntimeException('operator_provenance_denied');
            $granted=$this->one('SELECT capability FROM auth_profile_operator_grants WHERE revision_id=? AND capability=?',[$r['revision_id'],$capability])!==null;
            $commercial=(new CurrentSubscriptionReadModelService(new CurrentSubscriptionRepository($this->pdo),$this->clock->now(),commercialOnly:$this->commercialOnly))->resolveForEntity('doctor',$profile);
            $entitled=($commercial['feature_access']['agenda_appointments']['available']??false)===true;
            $valid=$this->sessions->validate($session)->allowed();
            if($owns)$this->pdo->commit();
            return $granted&&$entitled&&$valid;
        } catch (\Throwable) {
            if (($owns??false)&&$this->pdo->inTransaction()) $this->pdo->rollBack();
            return false;
        }
    }

    /** Verified invitee sees the immutable exact offer before explicit accept/reject. */
    public function inspectInvitation(string $session,string $profile,string $id,string $token): array
    {
        $d=$this->sessions->validate($session);
        if (!$d->allowed()||$this->pdo->inTransaction()) throw new \RuntimeException('operator_invitation_unavailable');
        try {
            $this->pdo->beginTransaction();$this->lockProfile($profile);
            $a=$this->account($d->context());$o=$this->owner($profile);$i=$this->invitation($profile,$id);$this->pending($i,$o);
            if ($a['email_normalized']!==$i['email_normalized']||!hash_equals($i['token_hash'],$this->tokenHash($id,$profile,$i['offer_hash'],$token)))
                throw new \RuntimeException('operator_invitation_unavailable');
            $p=$this->one('SELECT display_name FROM profiles_doctors WHERE doctor_id=?',[$profile]);
            $presentation=$this->presentationReady?$this->presentations->invitation($profile,$id):null;
            $this->pdo->commit();
            return ['invitation_id'=>$id,'profile_id'=>$profile,'physician'=>$p['display_name'],'role'=>$i['role_code'],
                'capabilities'=>json_decode($i['offered_capabilities'],true,512,JSON_THROW_ON_ERROR),'offer_revision'=>(int)$i['offer_revision'],'expires_at'=>$i['expires_at'],'presentation_ready'=>$this->presentationReady]+OperatorPresentationRepository::projection($presentation);
        } catch (\Throwable $e) {if($this->pdo->inTransaction())$this->pdo->rollBack();throw $e;}
    }

    /** Minimal context, never the physician/clinical bootstrap. No client profile selector. */
    public function snapshot(string $session): array
    {
        $d=$this->sessions->validate($session);
        if(!$d->allowed())throw new \RuntimeException('operator_authentication_required');
        $account=$this->one("SELECT account_id,email_normalized FROM auth_accounts WHERE account_id=? AND status='active' AND email_verified_at IS NOT NULL",[$d->context()->accountId()]);
        if($account===null)throw new \RuntimeException('operator_account_ineligible');
        $rows=$this->all("SELECT m.*,p.display_name FROM auth_account_memberships m JOIN profiles_doctors p ON p.doctor_id=m.profile_doctor_id WHERE m.account_id=? AND m.entity_group_id IS NULL AND m.scope_code='profile' AND m.status IN ('active','suspended')",[$account['account_id']]);
        if(count($rows)>1)throw new \RuntimeException('operator_profile_ambiguous');
        $m=$rows[0]??null;$caps=[];$revision=0;$owner=false;
        if($m!==null) {
            $profile=$m['profile_doctor_id'];
            $o=$this->all("SELECT membership_id,account_id FROM auth_account_memberships WHERE profile_doctor_id=? AND role_code='owner' AND status='active'",[$profile]);
            $owner=count($o)===1&&$o[0]['membership_id']===$m['membership_id']&&$m['status']==='active';
            $r=$this->one('SELECT revision FROM auth_profile_operator_grant_revisions WHERE membership_id=? ORDER BY revision DESC LIMIT 1',[$m['membership_id']]);$revision=(int)($r['revision']??0);
            $plan=(new CurrentSubscriptionReadModelService(new CurrentSubscriptionRepository($this->pdo),$this->clock->now(),commercialOnly:$this->commercialOnly))->resolveForEntity('doctor',$profile);
            foreach(ProfileOperatorCapabilityCatalog::KEYS as $key)$caps[$key]=$owner?($plan['feature_access']['agenda_appointments']['available']??false)===true:$this->permits($session,$profile,$profile,$key);
        }
        $inbox=$this->all("SELECT i.invitation_id,i.profile_doctor_id,p.display_name,i.expires_at,i.offered_capabilities FROM auth_profile_operator_invitations i JOIN profiles_doctors p ON p.doctor_id=i.profile_doctor_id WHERE i.email_normalized=? AND i.status='pending' AND i.expires_at>UTC_TIMESTAMP(6) ORDER BY i.created_at",[$account['email_normalized']]);
        foreach($inbox as &$i){
            $i['capabilities']=json_decode($i['offered_capabilities'],true);
            $i+= $this->notifications instanceof TransactionalOperatorInvitationDelivery ? $this->notifications->inboxState($i['invitation_id']) : ['opened_at'=>null,'requires_email_link'=>false];
            $p=$this->presentationReady?$this->one('SELECT * FROM auth_profile_operator_presentations WHERE profile_doctor_id=? AND invitation_id=?',[$i['profile_doctor_id'],$i['invitation_id']]):null;
            $i+=OperatorPresentationRepository::projection($p);
        }unset($i);
        $presentation=$this->presentationReady&&$m!==null?$this->one('SELECT * FROM auth_profile_operator_presentations WHERE profile_doctor_id=? AND membership_id=?',[$m['profile_doctor_id'],$m['membership_id']]):null;
        $roster=[];
        if($this->presentationReady&&$m!==null&&($owner||in_array(true,$caps,true)))
            $roster=$this->all("SELECT p.seat_position,p.alias_display personal_alias FROM auth_profile_operator_presentations p JOIN auth_account_memberships m ON m.membership_id=p.membership_id AND m.profile_doctor_id=p.profile_doctor_id WHERE p.profile_doctor_id=? AND p.released_at IS NULL AND m.status IN ('active','suspended') AND m.assignment_source=? ORDER BY p.seat_position",[$m['profile_doctor_id'],self::SOURCE]);
        foreach($roster as &$row)$row['seat_position']=(int)$row['seat_position'];unset($row);
        return ['presentation_ready'=>$this->presentationReady,'creator_snapshots_ready'=>$this->originReady,'operator_presentations'=>$roster,
            'account_id'=>$account['account_id'],'alias'=>$presentation['alias_display']??($owner?explode('@',$account['email_normalized'])[0]:'Operador'),
            'profile'=>$m===null?null:['id'=>$m['profile_doctor_id'],'name'=>$m['display_name']],
            'owner'=>$owner,'membership_id'=>$m['membership_id']??null,'status'=>$m['status']??'unassigned',
            'grant_revision'=>$owner?0:$revision,'capabilities'=>$caps,'inbox'=>$inbox]+OperatorPresentationRepository::projection($presentation);
    }

    public function overview(string $session,string $profile): array
    {
        $d=$this->sessions->validate($session);if(!$d->allowed())throw new \RuntimeException('operator_authentication_required');
        try {
            $this->pdo->beginTransaction();$this->lockProfile($profile);$this->account($d->context());$owner=$this->owner($profile);
            if($owner['account_id']!==$d->context()->accountId())throw new \RuntimeException('operator_owner_required');
            $this->expire($profile,$d->context(),$this->uuid());
            $pending=$this->all("SELECT invitation_id,email_normalized,role_code,offered_capabilities,created_at,expires_at,offer_revision,status FROM auth_profile_operator_invitations WHERE profile_doctor_id=? AND status='pending' ORDER BY created_at",[$profile]);
            foreach($pending as &$i){$i['alias']=explode('@',$i['email_normalized'])[0];$i['capabilities']=json_decode($i['offered_capabilities'],true);if($this->notifications instanceof TransactionalOperatorInvitationDelivery)$i+=$this->notifications->inboxState($i['invitation_id']);$i+=OperatorPresentationRepository::projection($this->presentationReady?$this->presentations->invitation($profile,$i['invitation_id']):null);}unset($i);
            $members=$this->all("SELECT m.membership_id,m.account_id,m.role_code,m.status,a.email_normalized,a.status account_status,a.email_verified_at FROM auth_account_memberships m JOIN auth_accounts a ON a.account_id=m.account_id WHERE m.profile_doctor_id=? AND m.assignment_source=? ORDER BY m.created_at",[$profile,self::SOURCE]);
            foreach($members as &$m){$r=$this->one('SELECT * FROM auth_profile_operator_grant_revisions WHERE membership_id=? ORDER BY revision DESC LIMIT 1',[$m['membership_id']]);
                $m+=OperatorPresentationRepository::projection($this->presentationReady?$this->presentations->membership($profile,$m['membership_id']):null);
                $m['alias']=$m['personal_alias']??'Operador sin alias';
                $m['contact_verified']=$m['email_verified_at']!==null;unset($m['email_normalized'],$m['email_verified_at']);
                $invitation=$r===null?null:$this->one('SELECT created_at,closed_at,status FROM auth_profile_operator_invitations WHERE invitation_id=? AND profile_doctor_id=? AND accepted_membership_id=?',[$r['invitation_id'],$profile,$m['membership_id']]);
                $m['invited_at']=$invitation['created_at']??null;
                $m['accepted_at']=($invitation['status']??'')==='accepted'?$invitation['closed_at']:null;
                $m['revision']=(int)($r['revision']??0);$m['grant_status']=$r['status']??'missing';
                $m['capabilities']=$r===null?[]:array_column($this->all('SELECT capability FROM auth_profile_operator_grants WHERE revision_id=? ORDER BY capability',[$r['revision_id']]),'capability');
            }unset($m);
            $plan=(new CurrentSubscriptionReadModelService(new CurrentSubscriptionRepository($this->pdo),$this->clock->now(),commercialOnly:$this->commercialOnly))->resolveForEntity('doctor',$profile);
            foreach($members as &$member){
                $r=$this->one('SELECT * FROM auth_profile_operator_grant_revisions WHERE membership_id=? ORDER BY revision DESC LIMIT 1',[$member['membership_id']]);
                // Display-only projection. Action consumers always revalidate through permits.
                $member['effective_capabilities']=($member['status']==='active'&&$member['account_status']==='active'&&$member['contact_verified']&&($r['status']??'')==='active'&&$r['owner_membership_id']===$owner['membership_id']
                    &&$r['valid_from']<=$this->now()&&($r['valid_until']===null||$r['valid_until']>$this->now())
                    &&($plan['feature_access']['agenda_appointments']['available']??false)===true)?$member['capabilities']:[];
            }unset($member);
            $total=$this->seats($profile);$this->pdo->commit();
            return ['presentation_ready'=>$this->presentationReady,'limit'=>2,'occupied'=>$total-count($pending),'reserved'=>count($pending),'available'=>max(0,2-$total),'invitations'=>$pending,'members'=>$members];
        }catch(\Throwable $e){if($this->pdo->inTransaction())$this->pdo->rollBack();throw $e;}
    }

    /** Shared grant decision + existing business writer + audit under one profile lock. */
    public function executeAgenda(string $session,string $csrf,string $origin,string $profile,string $capability,int $expectedRevision,callable $business): array
    {
        if($origin!==$this->origin||!in_array($capability,ProfileOperatorCapabilityCatalog::KEYS,true)||$this->pdo->inTransaction())throw new \RuntimeException('operator_request_denied');
        $actor=$this->authenticate($session,$csrf);
        try {
            $this->pdo->beginTransaction();$this->lockProfile($profile);$this->account($actor);$owner=$this->owner($profile);
            $members=$this->all("SELECT * FROM auth_account_memberships WHERE account_id=? AND profile_doctor_id=? AND status IN ('active','pending','suspended') FOR UPDATE",[$actor->accountId(),$profile]);
            if(count($members)!==1||$members[0]['status']!=='active')throw new \RuntimeException('operator_membership_denied');
            $m=$members[0];$isOwner=$owner['membership_id']===$m['membership_id'];$r=$isOwner?null:$this->currentRevision($m['membership_id']);$revision=(int)($r['revision']??0);
            if($expectedRevision!==$revision)throw new \RuntimeException('operator_revision_conflict');
            $plan=(new CurrentSubscriptionReadModelService(new CurrentSubscriptionRepository($this->pdo),$this->clock->now(),commercialOnly:$this->commercialOnly))->resolveForEntity('doctor',$profile);
            if(($plan['feature_access']['agenda_appointments']['available']??false)!==true||(!$isOwner&&!$this->permits($session,$profile,$profile,$capability)))throw new \RuntimeException('operator_capability_denied');
            $creator=null;
            if($this->originReady){
                require_once dirname(__DIR__,2).'/agenda/contracts/AppointmentCreatorSnapshot.php';
                $presentation=$isOwner?null:$this->presentations->membership($profile,$m['membership_id']);
                $creator=\Agenda\Contracts\AppointmentCreatorSnapshot::fromCanonical($actor,$m,$presentation,$this->now());
            }
            $result=$business(['creator_snapshot'=>$creator,'account_id'=>$actor->accountId(),'membership_id'=>$m['membership_id'],'role'=>$isOwner?'owner':'operator','grant_revision'=>$revision]);
            if(($result['ok']??false)!==true)throw new \DomainException((string)($result['error']??'agenda_write_failed'));
            $correlation=$this->uuid();
            $context=TrustedAuditContext::fromServer($actor->accountId(),'account',$m['role_code'],'profile',$correlation,$correlation,(string)$actor->session()->sessionId(),null,null,'AGENDA','isolated/operators/agenda');
            $this->audit->append(new CanonicalAuditEventInput('PROFILE_OPERATOR_AGENDA_ACTION','SUCCESS','USER_REQUEST','profile_doctor',$profile,'profile_doctor',$profile,
                ['capability'=>$capability,'membership_id'=>$m['membership_id'],'grant_revision'=>$revision,'profile_doctor_id'=>$profile,'resource_id'=>$result['data']['appointment_id']??$profile]),$context);
            $this->authenticate($session,$csrf);$this->pdo->commit();return $result;
        }catch(\Throwable $e){if($this->pdo->inTransaction())$this->pdo->rollBack();throw $e;}
    }

    /** Governed callback only, same PDO and profile lock as the enclosing owner transaction. */
    public function ownershipChanged(PDO $transaction, string $profileId, AuthenticatedAccessContext $actor, string $correlation): void
    {
        if ($transaction!==$this->pdo||!$transaction->inTransaction()) throw new \RuntimeException('operator_owner_boundary_transaction_required');
        $this->lockProfile($profileId);
        $newOwner=$this->owner($profileId); // Ambiguous/ownerless transitions cannot keep delegation alive.
        if($this->presentationReady){
            foreach($this->all("SELECT p.invitation_id FROM auth_profile_operator_presentations p JOIN auth_account_memberships m ON m.membership_id=p.membership_id WHERE p.profile_doctor_id=? AND p.released_at IS NULL AND (m.status='revoked' OR m.account_id=?)",[$profileId,$newOwner['account_id']]) as $row)
                $this->presentations->release($profileId,$row['invitation_id'],$this->now());
        }
        if ($this->seats($profileId)>2) throw new \RuntimeException('operator_owner_transition_quota_conflict');
        foreach ($this->all("SELECT * FROM auth_profile_operator_invitations WHERE profile_doctor_id=? AND status='pending' FOR UPDATE",[$profileId]) as $i)
            $this->closeInvitation($i,'cancelled',$actor,$correlation,'OWNER_CHANGED');
        $revisions=$this->all("SELECT r.*,m.profile_doctor_id FROM auth_profile_operator_grant_revisions r JOIN auth_account_memberships m ON m.membership_id=r.membership_id WHERE m.profile_doctor_id=? AND r.status='active' FOR UPDATE",[$profileId]);
        foreach ($revisions as $r) {
            $this->exec("UPDATE auth_profile_operator_grant_revisions SET status='review_required' WHERE revision_id=? AND status='active'",[$r['revision_id']]);
            $this->event('owner_review_required',$actor,$profileId,$correlation,$r['membership_id'],$r['invitation_id'],(int)$r['revision'],$r['owner_membership_id'],[],'OWNER_CHANGED');
        }
    }

    private function invite(AuthenticatedAccessContext $actor,string $profile,array $owner,array $input,string $correlation,?int $preferredPosition=null): array
    {
        $email=$this->recipients->normalize($input['email']??null);
        $role=$input['role']??'collaborator';
        if (!in_array($role,['collaborator','administrator'],true)) throw new \InvalidArgumentException('operator_role_invalid');
        $caps=ProfileOperatorCapabilityCatalog::normalize($input['capabilities']??[]);
        // Same generic refusal for an occupied relationship; no account existence disclosure.
        if ($this->one("SELECT m.membership_id FROM auth_account_memberships m JOIN auth_accounts a ON a.account_id=m.account_id WHERE m.profile_doctor_id=? AND a.email_normalized=? AND m.status IN ('pending','active','suspended') LIMIT 1",[$profile,$email])!==null
            ||$this->one("SELECT invitation_id FROM auth_profile_operator_invitations WHERE profile_doctor_id=? AND email_normalized=? AND status='pending'",[$profile,$email])!==null)
            throw new \RuntimeException('operator_invitation_unavailable');
        if ($this->seats($profile)>=2) throw new \RuntimeException('operator_quota_exceeded');
        $id=OneTimeTokenCodec::id('opinvite_');$raw=OneTimeTokenCodec::issue();
        $alias=$this->presentationReady?OperatorAlias::normalize($input['alias']??null):['display'=>null,'key'=>null];
        $offer=hash('sha256',json_encode([$profile,$owner['membership_id'],$role,$caps,1,$this->presentationReady?['presentation_version'=>1,'alias'=>$alias['display']]:null],JSON_THROW_ON_ERROR));
        $created=$this->clock->now();
        $expires=$created->modify('+7 days')->format('Y-m-d H:i:s.u');
        $this->exec('INSERT INTO auth_profile_operator_invitations(invitation_id,profile_doctor_id,owner_membership_id,email_normalized,role_code,offered_capabilities,offer_hash,token_hash,created_at,expires_at) VALUES(?,?,?,?,?,?,?,?,?,?)',
            [$id,$profile,$owner['membership_id'],$email,$role,json_encode($caps,JSON_THROW_ON_ERROR),$offer,$this->tokenHash($id,$profile,$offer,$raw),$created->format('Y-m-d H:i:s.u'),$expires]);
        $presentation=$this->presentationReady?$this->presentations->reserve($profile,$id,$alias,$this->now(),$preferredPosition):null;
        $this->event('invite',$actor,$profile,$correlation,null,$id,1,$owner['membership_id'],$caps,'OWNER_REQUEST',OperatorPresentationRepository::projection($presentation));
        return [['invitation_id'=>$id,'status'=>'pending','offer_revision'=>1,'role'=>$role,'capabilities'=>$caps,'expires_at'=>$expires]+OperatorPresentationRepository::projection($presentation),
            new NotificationMessage(OneTimeTokenPurpose::OPERATOR_INVITATION,$email,$id.'.'.$raw,$expires)];
    }

    private function respond(AuthenticatedAccessContext $actor,array $account,string $profile,array $owner,string $action,array $input,string $correlation): array
    {
        $i=$this->invitation($profile,(string)($input['invitation_id']??''));
        $this->pending($i,$owner);
        if ($actor->accountId()===$owner['account_id']||$account['email_normalized']!==$i['email_normalized']
            ||($input['offer_revision']??null)!==(int)$i['offer_revision']
            ||!hash_equals($i['token_hash'],$this->tokenHash($i['invitation_id'],$profile,$i['offer_hash'],(string)($input['token']??''))))
            throw new \RuntimeException('operator_invitation_unavailable');
        if ($action==='reject') {
            $this->closeInvitation($i,'rejected',$actor,$correlation,'INVITEE_DECISION');
            return ['invitation_id'=>$i['invitation_id'],'status'=>'rejected'];
        }
        if ($this->seats($profile)>2) throw new \RuntimeException('operator_quota_state_conflict');
        if ($this->one("SELECT membership_id FROM auth_account_memberships WHERE account_id=? AND profile_doctor_id=? AND status IN ('pending','active','suspended') LIMIT 1 FOR UPDATE",[$actor->accountId(),$profile])!==null)
            throw new \RuntimeException('operator_membership_conflict');
        $id=OneTimeTokenCodec::id('opmember_');
        $this->memberships->create(new AccountMembership($id,$actor->accountId(),CanonicalProfileReference::profile($profile),$i['role_code'],'profile','active',self::SOURCE));
        // Convert reservation to membership while holding the profile lock, never double count.
        $this->exec("UPDATE auth_profile_operator_invitations SET status='accepted',closed_at=?,accepted_membership_id=? WHERE invitation_id=? AND status='pending'",[$this->now(),$id,$i['invitation_id']]);
        if($this->notifications instanceof TransactionalOperatorInvitationDelivery)$this->notifications->retire($i['invitation_id']);
        $presentation=$this->presentationReady?$this->presentations->accept($profile,$i['invitation_id'],$id,$input['alias']??null,$this->now()):null;
        $caps=ProfileOperatorCapabilityCatalog::normalize(json_decode($i['offered_capabilities'],true,512,JSON_THROW_ON_ERROR));
        $this->revision($id,$i['invitation_id'],$owner['membership_id'],1,$caps,null);
        $this->event('accept',$actor,$profile,$correlation,$id,$i['invitation_id'],1,$owner['membership_id'],$caps,'INVITEE_DECISION',OperatorPresentationRepository::projection($presentation));
        $this->event('grants',$actor,$profile,$correlation,$id,$i['invitation_id'],1,$owner['membership_id'],$caps,'OFFER_ACCEPTED');
        return ['invitation_id'=>$i['invitation_id'],'membership_id'=>$id,'status'=>'accepted','grant_revision'=>1]+OperatorPresentationRepository::projection($presentation);
    }

    private function manage(AuthenticatedAccessContext $actor,string $profile,array $owner,string $action,array $input,string $correlation): array
    {
        $m=$this->memberships->lockProfileMembership((string)($input['membership_id']??''));
        if ($m===null||$m['profile_doctor_id']!==$profile||$m['entity_group_id']!==null||$m['scope_code']!=='profile'
            ||$m['account_id']===$actor->accountId()||$m['assignment_source']!==self::SOURCE
            ||!in_array($m['role_code'],['administrator','collaborator'],true)||!in_array($m['status'],['active','suspended'],true))
            throw new \RuntimeException('operator_membership_denied');
        $r=$this->currentRevision($m['membership_id']);
        if ($r===null||($input['expected_revision']??null)!==(int)$r['revision']) throw new \RuntimeException('operator_revision_conflict');
        $newRevision=(int)$r['revision'];$caps=[];
        if (in_array($action,['review','grants'],true)) {
            if ($action==='grants'&&($r['status']!=='active'||$r['owner_membership_id']!==$owner['membership_id']))
                throw new \RuntimeException('operator_owner_review_required');
            if ($action==='review'&&$r['status']!=='review_required') throw new \RuntimeException('operator_review_state_conflict');
            $caps=ProfileOperatorCapabilityCatalog::normalize($input['capabilities']??[]);
            $until=$input['valid_until']??null;
            if ($until!==null&&(!is_string($until)||!preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}\.\d{6}$/D',$until)||$until<=$this->now()))
                throw new \InvalidArgumentException('operator_validity_invalid');
            $this->exec("UPDATE auth_profile_operator_grant_revisions SET status='superseded' WHERE revision_id=?",[$r['revision_id']]);
            $newRevision++;
            $this->revision($m['membership_id'],$r['invitation_id'],$owner['membership_id'],$newRevision,$caps,$until);
        } else {
            $next=['suspend'=>'suspended','reactivate'=>'active','revoke'=>'revoked'][$action];
            if ($next==='active'&&($r['status']!=='active'||$r['owner_membership_id']!==$owner['membership_id'])) throw new \RuntimeException('operator_owner_review_required');
            if (!$this->memberships->transitionOperatorStatus($m['membership_id'],$m['account_id'],$profile,$m['status'],$next)) throw new \RuntimeException('operator_lifecycle_conflict');
            if($next==='revoked'&&$this->presentationReady)$this->presentations->release($profile,$r['invitation_id'],$this->now());
            if ($next==='revoked') $this->exec("UPDATE auth_profile_operator_grant_revisions SET status='revoked' WHERE revision_id=?",[$r['revision_id']]);
        }
        $this->event($action,$actor,$profile,$correlation,$m['membership_id'],$r['invitation_id'],$newRevision,$owner['membership_id'],$caps,'OWNER_REQUEST');
        return ['membership_id'=>$m['membership_id'],'grant_revision'=>$newRevision,'action'=>$action,'status'=>$next??$m['status']];
    }

    private function revision(string $membership,string $invitation,string $owner,int $revision,array $caps,?string $until): void
    {
        $id=OneTimeTokenCodec::id('opgrant_');
        $this->exec("INSERT INTO auth_profile_operator_grant_revisions(revision_id,membership_id,invitation_id,owner_membership_id,revision,status,valid_from,valid_until,created_at) VALUES(?,?,?,?,?,'active',?,?,?)",[$id,$membership,$invitation,$owner,$revision,$this->now(),$until,$this->now()]);
        foreach ($caps as $cap) $this->exec('INSERT INTO auth_profile_operator_grants(revision_id,capability) VALUES(?,?)',[$id,$cap]);
    }
    private function authenticate(string $session,string $csrf): AuthenticatedAccessContext
    {
        $d=$this->sessions->validate($session);
        if (!$d->allowed()||!$this->csrf->validAuthenticated($csrf,$d->record()->tokenDigest())) throw new \RuntimeException('operator_authentication_or_csrf_required');
        return $d->context();
    }
    private function account(AuthenticatedAccessContext $actor): array
    {
        $a=$this->one('SELECT account_id,email_normalized,status,email_verified_at FROM auth_accounts WHERE account_id=? FOR UPDATE',[$actor->accountId()]);
        $c=$this->one('SELECT credential_version FROM auth_account_credentials WHERE account_id=? FOR UPDATE',[$actor->accountId()]);
        if ($a===null||$c===null||$a['status']!=='active'||$a['email_verified_at']===null||(int)$c['credential_version']!==$actor->principal()->credentialVersion())
            throw new \RuntimeException('operator_account_ineligible');
        return $a;
    }
    private function lockProfile(string $profile): void
    {
        if ($this->one('SELECT doctor_id FROM profiles_doctors WHERE doctor_id=? FOR UPDATE',[$profile])===null) throw new \RuntimeException('operator_profile_not_found');
    }
    private function owner(string $profile): array
    {
        $owners=$this->all("SELECT m.*,a.status owner_account_status,a.email_verified_at owner_email_verified_at FROM auth_account_memberships m JOIN auth_accounts a ON a.account_id=m.account_id
            WHERE m.profile_doctor_id=? AND m.role_code='owner' AND m.status='active' AND m.entity_group_id IS NULL AND m.scope_code IN ('profile','profile_doctor')
            FOR UPDATE",[$profile]);
        if (count($owners)!==1||$owners[0]['owner_account_status']!=='active'||$owners[0]['owner_email_verified_at']===null)
            throw new \RuntimeException('operator_unique_owner_required');
        return $owners[0];
    }
    private function seats(string $profile): int
    {
        // Count canonical people by account_id (not mutable email or role rows).
        // Creation rejects a contact already assigned here. Reservations are separate
        // until acceptance closes one and creates a membership in this transaction.
        $r=$this->one("SELECT
            (SELECT COUNT(DISTINCT m.account_id) FROM auth_account_memberships m
             WHERE m.profile_doctor_id=? AND m.entity_group_id IS NULL AND m.status IN ('pending','active','suspended')
               AND NOT EXISTS (SELECT 1 FROM auth_account_memberships o WHERE o.account_id=m.account_id AND o.profile_doctor_id=m.profile_doctor_id AND o.role_code='owner' AND o.status='active'))
            + (SELECT COUNT(*) FROM auth_profile_operator_invitations WHERE profile_doctor_id=? AND status='pending' AND expires_at>?) n",
            [$profile,$profile,$this->now()]);
        return (int)$r['n'];
    }
    private function invitation(string $profile,string $id): array
    {
        return $this->one('SELECT * FROM auth_profile_operator_invitations WHERE invitation_id=? AND profile_doctor_id=? FOR UPDATE',[$id,$profile])??throw new \RuntimeException('operator_invitation_unavailable');
    }
    private function pending(array $i,array $owner): void
    {
        if ($i['status']!=='pending'||$i['expires_at']<=$this->now()||$i['owner_membership_id']!==$owner['membership_id']) throw new \RuntimeException('operator_invitation_unavailable');
    }
    private function currentRevision(string $membership): ?array
    {
        return $this->one("SELECT * FROM auth_profile_operator_grant_revisions WHERE membership_id=? AND status IN ('active','review_required') FOR UPDATE",[$membership]);
    }
    private function expire(string $profile,AuthenticatedAccessContext $actor,string $correlation): void
    {
        foreach ($this->all("SELECT * FROM auth_profile_operator_invitations WHERE profile_doctor_id=? AND status='pending' AND expires_at<=? FOR UPDATE",[$profile,$this->now()]) as $i)
            $this->closeInvitation($i,'expired',$actor,$correlation,'EXPIRED');
    }
    private function closeInvitation(array $i,string $status,AuthenticatedAccessContext $actor,string $correlation,string $reason): void
    {
        if ($this->exec('UPDATE auth_profile_operator_invitations SET status=?,closed_at=? WHERE invitation_id=? AND status=\'pending\'',[$status,$this->now(),$i['invitation_id']])!==1)
            throw new \RuntimeException('operator_invitation_state_conflict');
        if($this->presentationReady)$this->presentations->release($i['profile_doctor_id'],$i['invitation_id'],$this->now());
        if($this->notifications instanceof TransactionalOperatorInvitationDelivery)$this->notifications->retire($i['invitation_id']);
        $this->event($status,$actor,$i['profile_doctor_id'],$correlation,null,$i['invitation_id'],1,$i['owner_membership_id'],[],$reason);
    }
    private function event(string $action,AuthenticatedAccessContext $actor,string $profile,string $correlation,?string $membership,?string $invitation,int $revision,string $owner,array $caps,string $reason,array $presentation=[]): void
    {
        $types=['invite'=>'INVITATION_CREATED','accept'=>'INVITATION_ACCEPTED','cancelled'=>'INVITATION_REVOKED','rejected'=>'INVITATION_REVOKED','expired'=>'INVITATION_REVOKED',
            'grants'=>'PROFILE_OPERATOR_DELEGATION_CHANGED','suspend'=>'PROFILE_OPERATOR_DELEGATION_CHANGED','reactivate'=>'PROFILE_OPERATOR_DELEGATION_CHANGED','revoke'=>'PROFILE_OPERATOR_DELEGATION_CHANGED','review'=>'PROFILE_OPERATOR_DELEGATION_CHANGED','owner_review_required'=>'PROFILE_OPERATOR_DELEGATION_CHANGED','rename'=>'PROFILE_OPERATOR_DELEGATION_CHANGED'];
        $type=$types[$action]??throw new \InvalidArgumentException('operator_audit_action_unknown');
        $actorMembership=$this->one("SELECT membership_id,role_code FROM auth_account_memberships WHERE account_id=? AND profile_doctor_id=? AND status='active' ORDER BY membership_id LIMIT 1",[$actor->accountId(),$profile]);
        $staff=$actorMembership===null?$this->one("SELECT governance_class FROM internal_staff WHERE account_id=? AND status='ACTIVE'",[$actor->accountId()]):null;
        $actorRole=$actorMembership['role_code']??$staff['governance_class']??'authenticated_account';
        $context=TrustedAuditContext::fromServer($actor->accountId(),'account',$actorRole,'profile',$correlation,$correlation,
            (string)$actor->session()->sessionId(),null,null,(new \Platform\Services\SourceModuleCatalog())->moduleForEvent($type),$this->origin==='https://op01.example.invalid'?'disposable/profile-operators':'api/profile-operators/index.php/command');
        $this->audit->append(new CanonicalAuditEventInput($type,'SUCCESS',$action==='accept'?'USER_REQUEST':($reason==='OWNER_CHANGED'?'SECURITY_RESPONSE':'ADMIN_DECISION'),
            'profile_doctor',$profile,'profile_doctor',$profile,
            ['operator_action'=>$action,'actor_membership_id'=>$actorMembership['membership_id']??null,'profile_doctor_id'=>$profile,'membership_id'=>$membership,'invitation_id'=>$invitation,'grant_revision'=>$revision,
                'owner_membership_id'=>$owner,'capabilities'=>$caps,'operation_reason'=>$reason]+$presentation),$context);
    }
    private function tokenHash(string $id,string $profile,string $offer,string $token): string
    { return hash('sha256',$id.'|'.$profile.'|'.$offer.'|'.OneTimeTokenCodec::hash($token)); }
    private function uuid(): string
    { $h=bin2hex(random_bytes(16));return substr($h,0,8).'-'.substr($h,8,4).'-4'.substr($h,13,3).'-a'.substr($h,17,3).'-'.substr($h,20,12); }
    private function now(): string { return $this->clock->now()->format('Y-m-d H:i:s.u'); }
    private function one(string $sql,array $args=[]): ?array { return $this->all($sql,$args)[0]??null; }
    private function all(string $sql,array $args=[]): array { $s=$this->pdo->prepare($sql);$s->execute($args);return $s->fetchAll(PDO::FETCH_ASSOC); }
    private function exec(string $sql,array $args=[]): int { $s=$this->pdo->prepare($sql);$s->execute($args);return $s->rowCount(); }
}
