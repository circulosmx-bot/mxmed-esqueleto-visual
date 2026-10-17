<?php
declare(strict_types=1);
require_once __DIR__.'/../../Corrections/bootstrap.php';
use Identity\Contracts\{Clock,SystemClock,MfaSecretGeneratorPort};
use Identity\Services\{SessionService,SessionTokenCodec,RateLimitService,CredentialAuthenticationService,GovernanceSecurityContext,StaffMfaService};
use Identity\Repositories\{IdentityAccountRepository,AccountCredentialRepository};
use Profiles\Corrections\{ReportCipher,ReportService,ReportRatePolicy,ReportReviewService};
const COR_PASSWORD='Only-Synthetic-Correction-01!';
final class CorClock implements Clock {
    public function __construct(public DateTimeImmutable $time){}
    public function now():DateTimeImmutable{return $this->time;}
    public function advance(string $s):void{$this->time=$this->time->modify($s);}
}
final class CorSecret implements MfaSecretGeneratorPort{public function generate():string{return str_repeat('e',20);}}
final class CorPdo extends PDO {
    public ?string $fault=null;
    public function prepare(string $query,array $options=[]):PDOStatement|false {
        if($this->fault!==null&&str_contains($query,$this->fault))throw new PDOException('synthetic_dependency_failure');
        return parent::prepare($query,$options);
    }
}
function corConnect():CorPdo {
    if(getenv('PUB_COR01_QA')!=='1'||getenv('MXMED_NORMAL_LOCAL_IDENTITY_EXPLICIT')!=='0')throw new RuntimeException('correction_disposable_required');
    $c=json_decode(file_get_contents((string)getenv('GOV03B_QA_CONNECTION')),true,16,JSON_THROW_ON_ERROR);
    if(!preg_match('/^mxmed_gate4d_preview_cor01_([a-f0-9]{10})$/D',$c['db'],$m)||$c['user']!=='gov03b_'.$m[1])throw new RuntimeException('correction_owned_target_required');
    $p=new CorPdo('mysql:host=127.0.0.1;port=3306;dbname='.$c['db'].';charset=utf8mb4',$c['user'],$c['password'],
        [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false]);
    if($p->query('SELECT DATABASE()')->fetchColumn()!==$c['db']||$p->query('SELECT CURRENT_USER()')->fetchColumn()!==$c['user'].'@127.0.0.1')throw new RuntimeException('correction_target_mismatch');
    return $p;
}
function corStack(PDO $p,Clock $clock=new SystemClock()):array {
    $accounts=new IdentityAccountRepository($p);$credentials=new AccountCredentialRepository($p);
    $sessions=new SessionService(new Identity\Adapters\ValkeySessionStoreAdapter(new Identity\Adapters\PreviewValkeyClient('127.0.0.1',(int)getenv('MXMED_SESSION_STORE_PORT')),
        'mxmed:gate4d:preview:session:',$clock),new SessionTokenCodec((string)getenv('MXMED_PREVIEW_PEPPER')),$clock,
        new Identity\Contracts\SessionPolicy(),new Identity\Adapters\PdoSessionAccountStateAdapter($accounts,$credentials));
    $limits=new RateLimitService($p,new Identity\Contracts\RateLimitKeyHasher(str_repeat('l',32)),$clock);
    $auth=new CredentialAuthenticationService($accounts,$credentials,$limits,$clock);$security=new GovernanceSecurityContext($sessions,$auth,$clock);
    $mfa=new StaffMfaService($p,base64_encode(str_repeat('m',32)),'cor-qa-mfa',new CorSecret(),$clock,$security);
    $cipher=new ReportCipher('cor-qa-key',['cor-qa-key'=>base64_decode((string)getenv('PUB_COR01_KEY'),true)],'synthetic-only-no-real-retention-approval');
    $csrf=new Identity\Http\CsrfTokenService((string)getenv('MXMED_PREVIEW_PEPPER'),900,$clock,(string)getenv('MXMED_PREVIEW_ORIGIN'));
    $service=new ReportService($p,$cipher,$csrf,new ReportRatePolicy($p,$cipher,$clock,3,100,100),$clock);
    $review=new ReportReviewService($service,$security,$mfa);
    return compact('accounts','credentials','sessions','limits','auth','security','mfa','cipher','csrf','service','review');
}
function corLogin(array $s,string $id):array {
    $a=$s['auth']->authenticate($id.'@example.invalid',COR_PASSWORD,['ip'=>null]);if(!$a->isAllowed())throw new RuntimeException('synthetic_login_failed');
    $created=$s['sessions']->create($a->candidate());if(!$created->allowed())throw new RuntimeException('synthetic_session_failed');
    $token=(string)$created->token();return ['context'=>$s['sessions']->validate($token)->context(),'token'=>$token,'id'=>$id];
}
function corCheck(bool $ok,string $label):void {if(!$ok)throw new RuntimeException('FAIL '.$label);echo 'PASS '.$label.PHP_EOL;}
function corDeny(callable $fn,int $status,string $label):void {
    try{$fn();}catch(Profiles\Corrections\ReportDenied $e){corCheck($e->status===$status,$label);return;}
    throw new RuntimeException('FAIL '.$label.' allowed');
}
function corSubmit(array $s,string $profile,string $category='CHANGED_PHONE',?string $browser=null):array {
    $browser??=bin2hex(random_bytes(32));$route=Profiles\Corrections\ReportEligibility::route($profile);
    $intent=$s['service']->intent($profile,$route,$browser);
    $payload=['category'=>$category,'explanation'=>'Información sintética de prueba, sin personas ni datos reales. <script>notExecuted()</script>',
        'relation'=>'','contact'=>'private-synthetic@example.invalid','privacy_ack'=>true];
    return ['receipt'=>$s['service']->submit($intent['intent'],$intent['csrf_token'],$browser,'127.0.0.1',$route,$payload),
        'intent'=>$intent,'browser'=>$browser,'route'=>$route,'payload'=>$payload];
}
function corProof(array $s,CorClock $clock,array $actor,string $report,string $status='ACCEPTED_FOR_APPLICATION',int $version=1):array {
    $clock->advance('+31 seconds');
    $cmd=['status'=>$status,'reason'=>match($status){'ACCEPTED_FOR_APPLICATION'=>'FACTS_SUPPORTED','NEEDS_CLARIFICATION'=>'NEEDS_DETAILS',default=>'NOT_SUPPORTED'},
        'evidence_refs'=>['synthetic-evidence-reference-01'],'decision_note'=>'Comprobación humana sintética de hechos.','facts_confirmed'=>true];
    $c=$s['review']->begin($actor['context'],$report,$version,$cmd,$actor['id'].'@example.invalid',COR_PASSWORD);
    return $c+$s['mfa']->verifyChallenge($actor['id'],(string)$actor['context']->session()->sessionId(),1,$c['challenge_id'],$c['challenge'],
        StaffMfaService::totp(str_repeat('e',20),intdiv($clock->now()->getTimestamp(),30)));
}
