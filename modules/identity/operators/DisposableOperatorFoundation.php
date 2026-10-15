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
 * OP-IMP01 foundation. Deliberately CLI/disposable-only: no HTTP route, env flag,
 * production constructor or legacy Agenda writer. OP-IMP02 must prove denial
 * across every consumer before providing a real composition.
 */
final class DisposableOperatorFoundation implements ProfileOperatorOwnershipBoundary
{
    private AccountMembershipRepository $memberships;
    private const SOURCE = 'owner_operator_invitation';
    private const ACTIONS = ['invite','reissue','cancel','accept','reject','grants','suspend','reactivate','revoke','review'];

    private function __construct(private PDO $pdo, private SessionService $sessions,
        private CsrfTokenService $csrf, private CanonicalAuditWriter $audit,
        private InMemoryIdentityNotificationAdapter $notifications, private Clock $clock)
    { $this->memberships = new AccountMembershipRepository($pdo); }

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

    /** All mutation inputs pass canonical Identity session + authenticated CSRF.
     * No actor, role, owner, membership authority or commercial plan comes from a body.
     * A successful idempotent replay returns only the saved receipt, never new access.
     */
    public function command(string $rawSession, string $csrf, string $origin, string $profile,
        string $commandId, string $action, array $input): array
    {
        if ($this->pdo->inTransaction()) throw new \RuntimeException('operator_outer_transaction_forbidden');
        if ($origin!=='https://op01.example.invalid' || !in_array($action,self::ACTIONS,true)
            || !preg_match('/^[A-Za-z0-9_-]{8,64}$/D',$commandId)) throw new \RuntimeException('operator_request_denied');
        $actor=$this->authenticate($rawSession,$csrf);
        $isResponse=in_array($action,['accept','reject'],true);
        $reason=$input['reason']??null;
        if ($reason!==($isResponse?'INVITEE_DECISION':'OWNER_REQUEST')) throw new \InvalidArgumentException('operator_reason_required');
        // Hash the canonical payload, including token digest, without persisting its contents.
        ksort($input,SORT_STRING);
        $requestHash=hash('sha256',json_encode([$action,$input],JSON_THROW_ON_ERROR));
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
                if ($action==='reissue') {
                    $old=$this->invitation($profile,(string)($input['invitation_id']??''));
                    $this->pending($old,$owner);
                    $this->closeInvitation($old,'cancelled',$actor,$correlation,'OFFER_REPLACED');
                    if (($input['email']??'')!==$old['email_normalized']) throw new \RuntimeException('operator_reissue_contact_changed');
                }
                [$result,$notification]=$this->invite($actor,$profile,$owner,$input,$correlation);
            } elseif ($action==='cancel') {
                $invite=$this->invitation($profile,(string)($input['invitation_id']??''));
                $this->pending($invite,$owner);
                $this->closeInvitation($invite,'cancelled',$actor,$correlation,'OWNER_REQUEST');
                $result=['invitation_id'=>$invite['invitation_id'],'status'=>'cancelled'];
            } else {
                $result=$this->manage($actor,$profile,$owner,$action,$input,$correlation);
            }
            $result['correlation_id']=$correlation;
            if ($this->seats($profile)>2) throw new \RuntimeException('operator_quota_state_conflict');
            $this->exec('INSERT INTO auth_profile_operator_commands(profile_doctor_id,actor_account_id,command_id,request_hash,result_json,created_at) VALUES(?,?,?,?,?,?)',
                [$profile,$actor->accountId(),$commandId,$requestHash,json_encode($result,JSON_THROW_ON_ERROR),$this->now()]);
            $this->authenticate($rawSession,$csrf); // Revoked/expired session denies before commit.
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
            if ($this->pdo->inTransaction()) return false;
            $this->pdo->beginTransaction();
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
            $commercial=(new CurrentSubscriptionReadModelService(new CurrentSubscriptionRepository($this->pdo),$this->clock->now()))->resolveForEntity('doctor',$profile);
            $entitled=($commercial['feature_access']['agenda_appointments']['available']??false)===true;
            $valid=$this->sessions->validate($session)->allowed();
            $this->pdo->commit();
            return $granted&&$entitled&&$valid;
        } catch (\Throwable) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
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
            $this->pdo->commit();
            return ['invitation_id'=>$id,'profile_id'=>$profile,'physician'=>$p['display_name'],'role'=>$i['role_code'],
                'capabilities'=>json_decode($i['offered_capabilities'],true,512,JSON_THROW_ON_ERROR),'offer_revision'=>(int)$i['offer_revision'],'expires_at'=>$i['expires_at']];
        } catch (\Throwable $e) {if($this->pdo->inTransaction())$this->pdo->rollBack();throw $e;}
    }

    /** Governed callback only, same PDO and profile lock as the enclosing owner transaction. */
    public function ownershipChanged(PDO $transaction, string $profileId, AuthenticatedAccessContext $actor, string $correlation): void
    {
        if ($transaction!==$this->pdo||!$transaction->inTransaction()) throw new \RuntimeException('operator_owner_boundary_transaction_required');
        $this->lockProfile($profileId);
        $this->owner($profileId); // Ambiguous/ownerless transitions cannot keep delegation alive.
        if ($this->seats($profileId)>2) throw new \RuntimeException('operator_owner_transition_quota_conflict');
        foreach ($this->all("SELECT * FROM auth_profile_operator_invitations WHERE profile_doctor_id=? AND status='pending' FOR UPDATE",[$profileId]) as $i)
            $this->closeInvitation($i,'cancelled',$actor,$correlation,'OWNER_CHANGED');
        $revisions=$this->all("SELECT r.*,m.profile_doctor_id FROM auth_profile_operator_grant_revisions r JOIN auth_account_memberships m ON m.membership_id=r.membership_id WHERE m.profile_doctor_id=? AND r.status='active' FOR UPDATE",[$profileId]);
        foreach ($revisions as $r) {
            $this->exec("UPDATE auth_profile_operator_grant_revisions SET status='review_required' WHERE revision_id=? AND status='active'",[$r['revision_id']]);
            $this->event('owner_review_required',$actor,$profileId,$correlation,$r['membership_id'],$r['invitation_id'],(int)$r['revision'],$r['owner_membership_id'],[],'OWNER_CHANGED');
        }
    }

    private function invite(AuthenticatedAccessContext $actor,string $profile,array $owner,array $input,string $correlation): array
    {
        $email=IdentityAccount::normalizeEmail((string)($input['email']??''));
        // Synthetic-only composition. Never emit a real address, even if a caller supplies one.
        if (!filter_var($email,FILTER_VALIDATE_EMAIL)||!str_ends_with($email,'@example.invalid')||strlen($email)>190)
            throw new \InvalidArgumentException('operator_contact_invalid');
        $role=$input['role']??'collaborator';
        if (!in_array($role,['collaborator','administrator'],true)) throw new \InvalidArgumentException('operator_role_invalid');
        $caps=ProfileOperatorCapabilityCatalog::normalize($input['capabilities']??[]);
        // Same generic refusal for an occupied relationship; no account existence disclosure.
        if ($this->one("SELECT m.membership_id FROM auth_account_memberships m JOIN auth_accounts a ON a.account_id=m.account_id WHERE m.profile_doctor_id=? AND a.email_normalized=? AND m.status IN ('pending','active','suspended') LIMIT 1",[$profile,$email])!==null
            ||$this->one("SELECT invitation_id FROM auth_profile_operator_invitations WHERE profile_doctor_id=? AND email_normalized=? AND status='pending'",[$profile,$email])!==null)
            throw new \RuntimeException('operator_invitation_unavailable');
        if ($this->seats($profile)>=2) throw new \RuntimeException('operator_quota_exceeded');
        $id=OneTimeTokenCodec::id('opinvite_');$raw=OneTimeTokenCodec::issue();
        $offer=hash('sha256',json_encode([$profile,$owner['membership_id'],$role,$caps,1],JSON_THROW_ON_ERROR));
        $expires=$this->clock->now()->modify('+7 days')->format('Y-m-d H:i:s.u');
        $this->exec('INSERT INTO auth_profile_operator_invitations(invitation_id,profile_doctor_id,owner_membership_id,email_normalized,role_code,offered_capabilities,offer_hash,token_hash,created_at,expires_at) VALUES(?,?,?,?,?,?,?,?,?,?)',
            [$id,$profile,$owner['membership_id'],$email,$role,json_encode($caps,JSON_THROW_ON_ERROR),$offer,$this->tokenHash($id,$profile,$offer,$raw),$this->now(),$expires]);
        $this->event('invite',$actor,$profile,$correlation,null,$id,1,$owner['membership_id'],$caps,'OWNER_REQUEST');
        return [['invitation_id'=>$id,'status'=>'pending','offer_revision'=>1,'role'=>$role,'capabilities'=>$caps,'expires_at'=>$expires],
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
        $caps=ProfileOperatorCapabilityCatalog::normalize(json_decode($i['offered_capabilities'],true,512,JSON_THROW_ON_ERROR));
        $this->revision($id,$i['invitation_id'],$owner['membership_id'],1,$caps,null);
        $this->event('accept',$actor,$profile,$correlation,$id,$i['invitation_id'],1,$owner['membership_id'],$caps,'INVITEE_DECISION');
        $this->event('grants',$actor,$profile,$correlation,$id,$i['invitation_id'],1,$owner['membership_id'],$caps,'OFFER_ACCEPTED');
        return ['invitation_id'=>$i['invitation_id'],'membership_id'=>$id,'status'=>'accepted','grant_revision'=>1];
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
        $this->event($status,$actor,$i['profile_doctor_id'],$correlation,null,$i['invitation_id'],1,$i['owner_membership_id'],[],$reason);
    }
    private function event(string $action,AuthenticatedAccessContext $actor,string $profile,string $correlation,?string $membership,?string $invitation,int $revision,string $owner,array $caps,string $reason): void
    {
        $types=['invite'=>'INVITATION_CREATED','accept'=>'INVITATION_ACCEPTED','cancelled'=>'INVITATION_REVOKED','rejected'=>'INVITATION_REVOKED','expired'=>'INVITATION_REVOKED',
            'grants'=>'PROFILE_OPERATOR_DELEGATION_CHANGED','suspend'=>'PROFILE_OPERATOR_DELEGATION_CHANGED','reactivate'=>'PROFILE_OPERATOR_DELEGATION_CHANGED','revoke'=>'PROFILE_OPERATOR_DELEGATION_CHANGED','review'=>'PROFILE_OPERATOR_DELEGATION_CHANGED','owner_review_required'=>'PROFILE_OPERATOR_DELEGATION_CHANGED'];
        $type=$types[$action]??throw new \InvalidArgumentException('operator_audit_action_unknown');
        $actorMembership=$this->one("SELECT membership_id,role_code FROM auth_account_memberships WHERE account_id=? AND profile_doctor_id=? AND status='active' ORDER BY membership_id LIMIT 1",[$actor->accountId(),$profile]);
        $staff=$actorMembership===null?$this->one("SELECT governance_class FROM internal_staff WHERE account_id=? AND status='ACTIVE'",[$actor->accountId()]):null;
        $actorRole=$actorMembership['role_code']??$staff['governance_class']??'authenticated_account';
        $context=TrustedAuditContext::fromServer($actor->accountId(),'account',$actorRole,'profile',$correlation,$correlation,
            (string)$actor->session()->sessionId(),null,null,(new \Platform\Services\SourceModuleCatalog())->moduleForEvent($type),'disposable/profile-operators');
        $this->audit->append(new CanonicalAuditEventInput($type,'SUCCESS',$action==='accept'?'USER_REQUEST':($reason==='OWNER_CHANGED'?'SECURITY_RESPONSE':'ADMIN_DECISION'),
            'profile_doctor',$profile,'profile_doctor',$profile,
            ['operator_action'=>$action,'actor_membership_id'=>$actorMembership['membership_id']??null,'profile_doctor_id'=>$profile,'membership_id'=>$membership,'invitation_id'=>$invitation,'grant_revision'=>$revision,
                'owner_membership_id'=>$owner,'capabilities'=>$caps,'operation_reason'=>$reason]),$context);
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
