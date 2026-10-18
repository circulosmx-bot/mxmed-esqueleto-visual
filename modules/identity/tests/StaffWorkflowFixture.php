<?php
declare(strict_types=1);
// CLI only. Configuration comes from the owning fresh-instance runner, never HTTP.
if(PHP_SAPI!=='cli')exit(1);
require_once __DIR__.'/../http/IdentityHttpComposition.php';
\Identity\Http\IdentityHttpComposition::registerAutoloader();
use Identity\Http\{IdentityHttpComposition,CanonicalHttpSessionResolver};
use Identity\Services\{CredentialAuthenticationService,RateLimitService,SessionService,SessionTokenCodec,SessionStoreFactory,InternalGovernanceService};
use Identity\Repositories\{IdentityAccountRepository,AccountCredentialRepository};
use Identity\Contracts\{RateLimitKeyHasher,SystemClock,Clock,SessionPolicy};
use Identity\Adapters\{PreviewValkeyClient,PdoSessionAccountStateAdapter};
$phase=getenv('ASE_TEAM_QA_PHASE')?:'ase-int02a';
if(!in_array($phase,['ase-adm02imp01'],true))throw new RuntimeException('isolated_phase_required');
$owned=(string)getenv('ASE_INT02A_OWNED');
if(!preg_match('~^/private/tmp/mxmed-'.preg_quote($phase,'~').'-[a-zA-Z0-9_]+$~D',realpath($owned)?:'')||!preg_match('/^mxmed_gate4d_preview_'.str_replace('-','',$phase).'_[a-f0-9]{10}$/D',(string)getenv('MXMED_DB_NAME'))||in_array((int)getenv('MXMED_DB_PORT'),[3306,3309],true))throw new RuntimeException('isolated_target_required');
$fixture=new PDO('mysql:unix_socket='.$owned.'/mysql.sock;dbname='.getenv('MXMED_DB_NAME').';charset=utf8mb4','ase_fixture',(string)getenv('ASE_INT02A_FIXTURE_PASSWORD'),[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
if(realpath((string)$fixture->query('SELECT @@datadir')->fetchColumn())!==realpath($owned.'/mysql'))throw new RuntimeException('isolated_datadir_required');
$identity=IdentityHttpComposition::fromProcessEnvironment();$clock=new SystemClock();$action=$argv[1]??'';

// Test entry only, unreachable from the allowlisted TLS router; canonical services below are real.
$tokens=json_decode(file_get_contents($owned.'/sessions.json'),true,512,JSON_THROW_ON_ERROR);
$b=json_decode(stream_get_contents(STDIN),true,16,JSON_THROW_ON_ERROR);
$fixture->setAttribute(PDO::ATTR_EMULATE_PREPARES,false);
require_once dirname(__DIR__,3).'/scripts/ase-adm02-workflow-runtime.php';
[$identity,$flow,$cases,$invites,$media,$writer]=staffWorkflowOwnedServices();
$p=$identity->pdo();$resolver=new CanonicalHttpSessionResolver($identity->sessions());
$security=new \Identity\Services\GovernanceSecurityContext($identity->sessions(),$identity->authentication());
$secret=file_get_contents($owned.'/workflow-totp-secret');
$gen=new class($secret) implements \Identity\Contracts\MfaSecretGeneratorPort {public function __construct(private string $s){}public function generate():string{return $this->s;}};
$mfa=new \Identity\Services\StaffMfaService($p,base64_encode(file_get_contents($owned.'/workflow-mfa-key')),'synthetic-workflow-mfa',secrets:$gen,security:$security);
try{
 if($action==='enroll'){
  $actor=$resolver->resolve(['__Host-mxmed_session'=>$tokens['director']]);$registrar=$resolver->resolve(['__Host-mxmed_session'=>$tokens['master']]);
  $password=trim(file_get_contents($owned.'/fixture-password'));
  $ceremony=new \Identity\Services\GovernanceStaffMfaCeremony($p,$mfa,$security,new \Identity\Adapters\CanonicalGovernanceAuditAdapter($p),$clock,['qa_master']);
  $r=$ceremony->begin($actor,$registrar,'qa_director@example.test',$password,'qa_master@example.test',$password);
  $ceremony->activate($actor,$r['factor_id'],\Identity\Services\StaffMfaService::totp($secret,intdiv(time(),30)));$result=['enrolled'=>true];
 }elseif($action==='consumer'){
  require_once dirname(__DIR__,2).'/media/services/MediaReviewAuthority.php';
  $authority=new \Identity\Services\InternalOperatorAuthority($resolver,new \Identity\Repositories\InternalOperatorGrantRepository($p));
  $context=$authority->resolve(['__Host-mxmed_session'=>$tokens[$b['actor']]],'read','media_review_submission',\Platform\Contracts\RiskLevel::R0);
  $allowed=false;try{\Media\Services\MediaReviewAuthority::requireRead($context);$allowed=true;}catch(\RuntimeException){}
  $result=['allowed'=>$allowed];
 }elseif($action==='admit'){
  $cookies=['__Host-mxmed_session'=>$tokens[$b['actor']??'director']];
  $result=$writer->completeAdmission($cookies,$b['target'],$flow,$b['input']);
 }else throw new RuntimeException('invalid_fixture');
 echo json_encode(['ok'=>true,'data'=>$result]);
}catch(Throwable $e){file_put_contents($owned.'/workflow-fixture-error.log',get_class($e).': '.$e->getMessage());echo json_encode(['ok'=>false,'error'=>'fixture_failed']);}
