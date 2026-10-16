<?php
declare(strict_types=1);
// Disposable-only joined SQL adapter. Never calls the production routines fixed to mxmed.
use Platform\Repositories\CanonicalAuditTransactionPort;
final class Op01AuditTransaction implements CanonicalAuditTransactionPort
{
    public bool $failInsert=false;
    public function __construct(private PDO $pdo){}
    public function begin():void{if(!$this->pdo->inTransaction())throw new RuntimeException('outer_transaction_required');}
    public function commit():void{if(!$this->pdo->inTransaction())throw new RuntimeException('outer_transaction_required');}
    public function rollBack():void{if($this->pdo->inTransaction())$this->pdo->rollBack();}
    public function ensureHead(string $streamKey,string $genesisHash,string $hashVersion):void
    {$s=$this->pdo->prepare('INSERT IGNORE INTO platform_audit_stream_heads VALUES(?,0,?,?,NULL)');$s->execute([$streamKey,$genesisHash,$hashVersion]);}
    public function lockHead(string $streamKey):array
    {$s=$this->pdo->prepare('SELECT last_sequence_number,last_event_hash,hash_version,updated_at FROM platform_audit_stream_heads WHERE stream_key=? FOR UPDATE');$s->execute([$streamKey]);$r=$s->fetch(PDO::FETCH_ASSOC);return ['last_sequence_number'=>(int)$r['last_sequence_number'],'last_event_hash'=>$r['last_event_hash'],'hash_version'=>$r['hash_version'],'updated_at'=>$r['updated_at']===null?null:str_replace(' ','T',$r['updated_at']).'Z'];}
    public function assertLegacyHeadMatchesLatest(string $streamKey,int $sequenceNumber,string $eventHash):void{throw new RuntimeException('legacy_not_used');}
    public function insertEvent(array $row):int
    {if($this->failInsert)throw new RuntimeException('synthetic_audit_write_failure');$c=array_keys($row);$s=$this->pdo->prepare('INSERT INTO platform_audit_events(`'.implode('`,`',$c).'`) VALUES(:'.implode(',:',$c).')');$s->execute($row);return $s->rowCount();}
    public function updateHead(string $streamKey,int $expectedSequence,string $expectedHash,?string $expectedHashVersion,?string $expectedUpdatedAt,int $newSequence,string $newHash,string $newHashVersion,string $newUpdatedAt):int
    {$s=$this->pdo->prepare('UPDATE platform_audit_stream_heads SET last_sequence_number=?,last_event_hash=?,hash_version=?,updated_at=? WHERE stream_key=? AND last_sequence_number=? AND last_event_hash=?');$s->execute([$newSequence,$newHash,$newHashVersion,str_replace(['T','Z'],[' ', ''],$newUpdatedAt),$streamKey,$expectedSequence,$expectedHash]);return $s->rowCount();}
}

final class Op01Clock implements \Identity\Contracts\Clock {
    public function __construct(public DateTimeImmutable $value) {}
    public function now(): DateTimeImmutable { return $this->value; }
}
final class Op01MfaSecret implements \Identity\Contracts\MfaSecretGeneratorPort {
    public function generate(): string { return str_repeat("s",20); }
}
function op01pdo(?string $db=null): PDO {
    if ($db!==null && !preg_match('/^mxmed_op01_[a-f0-9]{10}$/D',$db)) throw new RuntimeException('unsafe_op01_database');
    return new PDO('mysql:host=127.0.0.1;port=3306;'.($db===null?'':'dbname='.$db.';').'charset=utf8mb4','root','',[
        PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false]);
}
function op01writer(PDO $p,Op01AuditTransaction $tx): \Platform\Services\CanonicalAuditWriter {
    return new \Platform\Services\CanonicalAuditWriter(
        \Platform\Services\CanonicalAuditPolicyRegistry::canonical(),new \Platform\Services\CanonicalAuditMetadataSanitizer(),
        new \Platform\Services\TrustedAuditContextValidator(),new \Platform\Services\RandomAuditUuidProvider(),new \Platform\Services\SystemAuditUtcClock(),
        new \Platform\Services\HmacSha256AuditIpHasher(new \Platform\Services\EnvironmentAuditSecretProvider()),
        new \Platform\Services\CoarseAuditUserAgentSummarizer(),$tx,
        new \Platform\Services\CanonicalAuditSealer(new \Platform\Services\CanonicalAuditSerializer()),new \Platform\Services\AuditV1PhysicalMapper());
}
function op01stack(PDO $p,?Op01Clock $clock=null,?\Identity\Adapters\InMemorySessionStoreAdapter $store=null): array {
    $clock??=new Op01Clock(new DateTimeImmutable('now',new DateTimeZone('UTC')));
    $accounts=new \Identity\Repositories\IdentityAccountRepository($p);$credentials=new \Identity\Repositories\AccountCredentialRepository($p);
    $store??=new \Identity\Adapters\InMemorySessionStoreAdapter();
    $sessions=new \Identity\Services\SessionService($store,new \Identity\Services\SessionTokenCodec(str_repeat('s',32)),$clock,
        new \Identity\Contracts\SessionPolicy(),new \Identity\Adapters\PdoSessionAccountStateAdapter($accounts,$credentials));
    $csrf=new \Identity\Http\CsrfTokenService(str_repeat('c',32),900,$clock,'https://op01.example.invalid');
    $auth=new \Identity\Services\CredentialAuthenticationService($accounts,$credentials,
        new \Identity\Services\RateLimitService($p,new \Identity\Contracts\RateLimitKeyHasher(str_repeat('r',32)),$clock),$clock);
    $tx=new Op01AuditTransaction($p);$notifications=new \Identity\Adapters\InMemoryIdentityNotificationAdapter();
    $service=\Identity\Operators\DisposableOperatorFoundation::compose($p,$sessions,$csrf,op01writer($p,$tx),$notifications,$clock);
    return compact('clock','sessions','csrf','auth','tx','notifications','service','store');
}
function op01login(array $stack,string $id): array {
    $d=$stack['auth']->authenticate($id.'@example.invalid','Only-Synthetic-Op01!', ['ip'=>'127.0.0.1']);
    if (!$d->isAllowed()) throw new RuntimeException('synthetic_login_failed');
    $s=$stack['sessions']->create($d->candidate());
    if (!$s->allowed()) throw new RuntimeException('synthetic_session_failed');
    return ['token'=>$s->token()->value(),'csrf'=>$stack['csrf']->issueAuthenticated($s->record()->tokenDigest()),'context'=>$stack['sessions']->validate($s->token()->value())->context()];
}
function op01command(array $stack,array $actor,string $profile,string $action,array $input=[],?string $command=null): array {
    return $stack['service']->command($actor['token'],$actor['csrf'],'https://op01.example.invalid',$profile,
        $command??('command_'.bin2hex(random_bytes(10))),$action,$input+['reason'=>in_array($action,['accept','reject'],true)?'INVITEE_DECISION':'OWNER_REQUEST']);
}
function op01invite(array $s,array $owner,string $profile,string $account,array $caps=['agenda.appointments.read'],string $role='collaborator'): array {
    $r=op01command($s,$owner,$profile,'invite',['email'=>$account.'@example.invalid','capabilities'=>$caps,'role'=>$role]);
    $message=$s['notifications']->lastMessage();
    if ($message===null||$message->purpose()!=='operator_invitation') throw new RuntimeException('synthetic_notification_missing');
    [$id,$token]=explode('.',$message->token(),2);
    if ($id!==$r['invitation_id']) throw new RuntimeException('synthetic_notification_binding');
    return $r+['token'=>$token];
}
function op01accept(array $s,array $actor,string $profile,array $invite): array {
    $view=$s['service']->inspectInvitation($actor['token'],$profile,$invite['invitation_id'],$invite['token']);
    if ($view['capabilities']!==$invite['capabilities']||$view['profile_id']!==$profile) throw new RuntimeException('offer_view_mismatch');
    return op01command($s,$actor,$profile,'accept',['invitation_id'=>$invite['invitation_id'],'token'=>$invite['token'],'offer_revision'=>1]);
}
function op01migrate(PDO $p,string $file): void { $p->exec(file_get_contents($file)); }
function op01scalar(PDO $p,string $sql,array $args=[]): mixed { $s=$p->prepare($sql);$s->execute($args);return $s->fetchColumn(); }
function op01denied(callable $fn): bool {try {$fn();return false;}catch(RuntimeException|InvalidArgumentException) {return true;}}
function op01check(bool $ok,string $label): void { if(!$ok) throw new RuntimeException('FAIL '.$label);echo "PASS $label\n"; }
function op01fresh(PDO $p,string $suffix): string {
    $id='op01_'.$suffix;
    $p->prepare('INSERT INTO profiles_doctors(doctor_id,display_name) VALUES(?,?)')->execute([$id,'Synthetic '.$suffix]);
    $p->prepare("INSERT INTO auth_account_memberships(membership_id,account_id,profile_doctor_id,role_code,scope_code,status) VALUES(?,'owner_a',?,'owner','profile','active')")->execute(['owner_'.$suffix,$id]);
    $p->prepare("INSERT INTO profile_subscriptions(subscription_id,entity_type,entity_id,plan_code,contracted_plan_code,effective_plan_code,plan_label,status,starts_at,expires_at) VALUES(UUID(),'doctor',?,'optimum','optimum','optimum','Synthetic','active',DATE_SUB(UTC_TIMESTAMP(),INTERVAL 1 DAY),DATE_ADD(UTC_TIMESTAMP(),INTERVAL 30 DAY))")->execute([$id]);
    return $id;
}
function op01GovernanceBindings(PDO $p,array $s): array {
    require_once __DIR__.'/SyntheticOwnershipEligibility.php';
    $security=new \Identity\Services\GovernanceSecurityContext($s['sessions'],$s['auth'],$s['clock']);
    $mfa=new \Identity\Services\StaffMfaService($p,base64_encode(str_repeat('m',32)),'op01-key',new Op01MfaSecret(),$s['clock'],$security);
    $pair=sodium_crypto_sign_seed_keypair(str_repeat('t',32));
    $trust=new \Identity\Adapters\Ed25519ExternalTrustAnchorAdapter($p,base64_encode(sodium_crypto_sign_publickey($pair)),'op01-synthetic','local',$s['clock']);
    $audit=new \Identity\Adapters\CanonicalGovernanceAuditAdapter($p,op01writer($p,$s['tx']));
    $bindings=new \Identity\Services\GovernanceCaseSecurity($p,$security,new SyntheticOwnershipEligibility(),$trust,$audit);
    return compact('security','mfa','pair','trust','audit','bindings');
}
function op01governance(PDO $p,array $s,bool $hook=true): \Identity\Services\R3ProfileGovernanceService {
    $b=op01GovernanceBindings($p,$s);
    return new \Identity\Services\R3ProfileGovernanceService($p,$b['mfa'],new \Identity\Adapters\PdoProfileOwnershipApproverAuthorityAdapter($p),$s['clock'],$hook?$s['service']:null,$b['bindings']);
}
function op01approvedRecovery(PDO $p,array $s,array $requester,string $profile,string $replacement): string {
    $b=op01GovernanceBindings($p,$s);$cases=op01governance($p,$s);$owner=(string)op01scalar($p,"SELECT membership_id FROM auth_account_memberships WHERE profile_doctor_id=? AND role_code='owner' AND status='active'",[$profile]);
    $c=$cases->openOwnerRecoveryCase($requester['context'],'OWNER_REPLACEMENT',$owner,$replacement,'Synthetic owner transition review');
    $q=$p->prepare('SELECT * FROM auth_governance_cases WHERE case_id=?');$q->execute([$c['case_id']]);$case=$q->fetch();$context=json_decode($case['security_context'],true);
    $claims=['contract_version'=>2,'operation'=>'OWNER_REPLACEMENT','operation_id'=>$c['operation_id'],'target_account_id'=>$case['beneficiary_account_id'],
        'organization_id'=>'op01-synthetic','environment'=>'local','case_reference'=>$c['case_id'],'profile_doctor_id'=>$profile,'membership_id'=>$owner,
        'membership_version'=>\Identity\Services\GovernanceCaseSecurity::version($context['membership']),'requester_account_id'=>$requester['context']->accountId(),
        'trust_anchor_reference'=>'trust_'.bin2hex(random_bytes(16)),'ratifier_session_reference'=>'external-op01-session','authorized_at'=>$s['clock']->now()->format(DATE_ATOM),'expires_at'=>$s['clock']->now()->modify('+10 minutes')->format(DATE_ATOM),
        'ratifiers'=>[['account_id'=>'external_ratifier_a','approval_reference'=>'external-approval-a','mfa_verified'=>true,'reauthenticated'=>true],['account_id'=>'external_ratifier_b','approval_reference'=>'external-approval-b','mfa_verified'=>true,'reauthenticated'=>true]]];
    $json=json_encode($claims,JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);$b64=static fn($v)=>rtrim(strtr(base64_encode($v),'+/','-_'),'=');
    $cases->bindExternalEvidence($requester['context'],$c['case_id'],$b64($json).'.'.$b64(sodium_crypto_sign_detached($json,sodium_crypto_sign_secretkey($b['pair']))));
    $enrollment=new \Identity\Services\GovernanceStaffMfaCeremony($p,$b['mfa'],$b['security'],$b['audit'],$s['clock'],['requester']);
    foreach (['approver_a','approver_b'] as $id) {
        $actor=op01login($s,$id);
        if (!(int)op01scalar($p,"SELECT COUNT(*) FROM auth_staff_mfa_factors WHERE account_id=? AND status='ACTIVE'",[$id])) {
            $e=$enrollment->begin($actor['context'],$requester['context'],$id.'@example.invalid','Only-Synthetic-Op01!','requester@example.invalid','Only-Synthetic-Op01!');
            $enrollment->activate($actor['context'],$e['factor_id'],\Identity\Services\StaffMfaService::totp(str_repeat('s',20),intdiv($s['clock']->now()->getTimestamp(),30)));
        }
        $s['clock']->value=$s['clock']->value->modify('+31 seconds');
        $challenge=$cases->issueEndorsementChallenge($actor['context'],$c['case_id'],$id.'@example.invalid','Only-Synthetic-Op01!',['ip'=>'synthetic']);
        $proof=$b['mfa']->verifyChallenge($id,(string)$actor['context']->session()->sessionId(),1,$challenge['challenge_id'],$challenge['challenge'],
            \Identity\Services\StaffMfaService::totp(str_repeat('s',20),intdiv($s['clock']->now()->getTimestamp(),30)));
        $cases->endorse($actor['context'],$c['case_id'],$proof['proof_id'],$proof['proof']);
    }
    return $c['case_id'];
}

function op01credentialFingerprint(PDO $p): string {
    return hash('sha256',json_encode($p->query('SELECT account_id,password_hash,credential_version FROM auth_account_credentials ORDER BY account_id')->fetchAll(),JSON_THROW_ON_ERROR));
}
