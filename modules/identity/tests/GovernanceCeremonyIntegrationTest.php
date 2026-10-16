<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit(1);
require_once __DIR__.'/../http/IdentityHttpComposition.php';Identity\Http\IdentityHttpComposition::registerAutoloader();
use Identity\Http\{IdentityHttpComposition,GovernanceDeploymentAuthorization};
use Identity\Operators\OperatorDeploymentSchema;
use Identity\Services\StaffMfaService;
function ceremonyCheck(bool $ok,string $label):void{if(!$ok)throw new RuntimeException('FAIL '.$label);echo 'PASS '.$label.PHP_EOL;}
$root=dirname(__DIR__,3);$dir=(string)getenv('GOV03B_QA_EVIDENCE');$identity=IdentityHttpComposition::fromProcessEnvironment();$p=$identity->pdo();
$db=(string)$p->query('SELECT DATABASE()')->fetchColumn();if(!preg_match('/^mxmed_gate4d_preview_gov03b_[a-f0-9]{10}$/D',$db))throw new RuntimeException('owned_ceremony_only');
$password='Only-Synthetic-Ceremony!';$hash=password_hash($password,PASSWORD_ARGON2ID);
foreach(['ceremony_director','ceremony_staff_a','ceremony_staff_b'] as $id){$p->prepare("INSERT INTO auth_accounts(account_id,email_address,email_normalized,status,email_verified_at) VALUES(?,?,?,'active',UTC_TIMESTAMP())")->execute([$id,$id.'@example.invalid',$id.'@example.invalid']);$p->prepare('INSERT INTO auth_account_credentials(account_id,password_hash,password_changed_at,credential_version) VALUES(?,?,UTC_TIMESTAMP(),1)')->execute([$id,$hash]);}
foreach(['ceremony_staff_a','ceremony_staff_b'] as $id){$p->prepare("INSERT INTO internal_staff(account_id,governance_class,status) VALUES(?,'MASTER_ADMIN','ACTIVE')")->execute([$id]);$p->prepare("INSERT INTO internal_operator_grants(grant_id,account_id,capability,status) VALUES(UUID(),?,'profile_initial_ownership_approve','ACTIVE')")->execute([$id]);}
$tokens=[];foreach(['ceremony_director','ceremony_staff_a','ceremony_staff_b'] as $id){$d=$identity->authentication()->authenticate($id.'@example.invalid',$password,['ip'=>'synthetic-ceremony']);$s=$identity->sessions()->create($d->candidate());if(!$s->allowed())throw new RuntimeException('synthetic_session_failed');$tokens[$id]=(string)$s->token();}
$pair=sodium_crypto_sign_keypair();putenv('MXMED_GOVERNANCE_TRUST_ANCHOR_PUBLIC_KEY='.base64_encode(sodium_crypto_sign_publickey($pair)));
$operation=(new Platform\Services\RandomAuditUuidProvider())->generateCanonicalUuid();$now=new DateTimeImmutable('now',new DateTimeZone('UTC'));
$claims=['contract_version'=>2,'operation'=>'FIRST_DIRECTOR_BOOTSTRAP','operation_id'=>$operation,'target_account_id'=>'ceremony_director','organization_id'=>'mxmed-synthetic','environment'=>'local','case_reference'=>'external-approved-bootstrap-case','profile_doctor_id'=>null,'membership_id'=>null,'membership_version'=>null,'requester_account_id'=>null,'trust_anchor_reference'=>'trust_ceremony_'.bin2hex(random_bytes(12)),'ratifier_session_reference'=>'external-independent-session','authorized_at'=>$now->format(DATE_ATOM),'expires_at'=>$now->modify('+10 minutes')->format(DATE_ATOM),'ratifiers'=>[['account_id'=>'external_ratifier_one','approval_reference'=>'external-approval-one','mfa_verified'=>true,'reauthenticated'=>true],['account_id'=>'external_ratifier_two','approval_reference'=>'external-approval-two','mfa_verified'=>true,'reauthenticated'=>true]]];
$b64=static fn(string $v):string=>rtrim(strtr(base64_encode($v),'+/','-_'),'=');$json=json_encode($claims,JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);$envelope=$b64($json).'.'.$b64(sodium_crypto_sign_detached($json,sodium_crypto_sign_secretkey($pair)));
$input=['target_account_id'=>'ceremony_director','session_token'=>$tokens['ceremony_director'],'email'=>'ceremony_director@example.invalid','password'=>$password,'operation_id'=>$operation,'case_reference'=>$claims['case_reference'],'signed_evidence'=>$envelope];
$run=function(string $action,array $payload,?string $output=null)use($root,$dir):array{
 $path=$dir.'/ceremony-input.json';file_put_contents($path,json_encode($payload,JSON_THROW_ON_ERROR));chmod($path,0600);
 $args=[PHP_BINARY,$root.'/scripts/governance-ceremony.php','--action',$action,'--input',$path];if($output!==null)$args=[...$args,'--secret-output',$output];
 $process=proc_open($args,[0=>['file','/dev/null','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes,$root);$stdout=stream_get_contents($pipes[1]);$stderr=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);$exit=proc_close($process);
 // Do not preserve input, stdout or secrets in public logs.
 return ['exit'=>$exit,'data'=>json_decode($stdout,true),'error_class'=>$stderr===''?null:'CEREMONY_DENIED'];
};
putenv('MXMED_GOVERNANCE_CEREMONY_ENABLED=0');ceremonyCheck($run('bootstrap',$input)['exit']===78,'CLI_disabled_without_opt_in');putenv('MXMED_GOVERNANCE_CEREMONY_ENABLED=1');
ceremonyCheck($run('bootstrap',$input)['exit']===78,'CLI_flag_without_deployment_authorization_denied');
$receipt=OperatorDeploymentSchema::identity($p)+['contract_version'=>2,'state'=>'approved_for_governance','authorization_reference'=>'synthetic-disposable-approval','allowed_actions'=>['bootstrap','mfa_begin','mfa_activate','mfa_cancel','http'],'target_account_ids'=>array_keys($tokens),'mfa_registrar_account_ids'=>['ceremony_director'],'operation_id'=>$operation,'case_reference'=>$claims['case_reference'],'expires_at'=>$now->modify('+1 hour')->format(DATE_ATOM),'schema_fingerprint'=>OperatorDeploymentSchema::fingerprint($p),'source_hash'=>GovernanceDeploymentAuthorization::sourceHash(),'organization_id'=>'mxmed-synthetic','environment'=>'local','trust_key_sha256'=>hash('sha256',(string)getenv('MXMED_GOVERNANCE_TRUST_ANCHOR_PUBLIC_KEY')),'mfa_key_id'=>getenv('MXMED_STAFF_MFA_KEY_ID'),'mfa_key_sha256'=>hash('sha256',(string)getenv('MXMED_STAFF_MFA_ENCRYPTION_KEY'))];
$path=$dir.'/deployment-authorization.json';$bytes=json_encode($receipt,JSON_THROW_ON_ERROR);file_put_contents($path,$bytes);chmod($path,0600);putenv('MXMED_GOVERNANCE_DEPLOYMENT_AUTHORIZATION='.$path);putenv('MXMED_GOVERNANCE_DEPLOYMENT_AUTHORIZATION_SHA256='.hash('sha256',$bytes));
$wrong=$input;$wrong['case_reference']='another-external-case';ceremonyCheck($run('bootstrap',$wrong)['exit']===78,'CLI_bootstrap_case_not_approved_denied');
$r=$run('bootstrap',$input);ceremonyCheck($r['exit']===0&&($r['data']['data']['governance_class']??null)==='DIRECTOR','CLI_valid_external_bootstrap_native_audit');ceremonyCheck($run('bootstrap',$input)['exit']===78,'CLI_bootstrap_replay_denied');
foreach(['ceremony_staff_a','ceremony_staff_b'] as $id){
 $subject=['target_account_id'=>$id,'session_token'=>$tokens[$id],'email'=>$id.'@example.invalid','password'=>$password,'registrar_session_token'=>$tokens['ceremony_director'],'registrar_email'=>'ceremony_director@example.invalid','registrar_password'=>$password];
 $output=$dir.'/'.$id.'-secret.json';$r=$run('mfa_begin',$subject,$output);ceremonyCheck($r['exit']===0,'CLI_independent_enrollment_'.$id);
 $secret=json_decode(file_get_contents($output),true,32,JSON_THROW_ON_ERROR);ceremonyCheck((fileperms($output)&0077)===0,'CLI_secret_artifact_private_'.$id);
 if($id==='ceremony_staff_a'){
  $alphabet='ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';$bits='';foreach(str_split($secret['secret']) as $c)$bits.=str_pad(decbin(strpos($alphabet,$c)),5,'0',STR_PAD_LEFT);$raw='';foreach(str_split($bits,8) as $chunk){if(strlen($chunk)===8)$raw.=chr(bindec($chunk));}
  $step=intdiv(time(),30);$r=$run('mfa_activate',$subject+['factor_id'=>$secret['factor_id'],'code'=>StaffMfaService::totp($raw,$step)]);
  if($r['exit']!==0&&intdiv(time(),30)!==$step)$r=$run('mfa_activate',$subject+['factor_id'=>$secret['factor_id'],'code'=>StaffMfaService::totp($raw,intdiv(time(),30))]);
  ceremonyCheck($r['exit']===0,'CLI_possession_activation');
 }else{ceremonyCheck($run('mfa_cancel',$subject+['factor_id'=>$secret['factor_id']])['exit']===0,'CLI_explicit_factor_cancellation');}
 unlink($output);
}
$meta=json_decode((string)$p->query("SELECT metadata_json FROM platform_audit_events WHERE action='INTERNAL_GOVERNANCE_CHANGED' AND JSON_UNQUOTE(JSON_EXTRACT(metadata_json,'$.producer_metadata.operation'))='bootstrap_first_director'")->fetchColumn(),true,32,JSON_THROW_ON_ERROR);
$acceptance=$meta['producer_metadata']['after'];ceremonyCheck(($acceptance['accepting_account_id']??null)==='ceremony_director'&&($acceptance['accepting_credential_version']??null)===1&&is_string($acceptance['accepting_session_id']??null),'CLI_bootstrap_accepting_session_attributed');
ceremonyCheck(!str_contains(json_encode($meta),$tokens['ceremony_director'])&&!str_contains(json_encode($meta),$password),'CLI_audit_contains_no_credentials');
$events=(int)$p->query("SELECT COUNT(*) FROM platform_audit_events WHERE action='INTERNAL_GOVERNANCE_CHANGED'")->fetchColumn();ceremonyCheck($events===5,'CLI_all_privileged_actions_native_audit');
ceremonyCheck(!is_file((string)getenv('MXMED_PREVIEW_NOTIFICATION_FILE')),'CLI_no_notification_delivery');unlink($dir.'/ceremony-input.json');
$session=$identity->sessions()->validate($tokens['ceremony_staff_a'])->context();
$fixture=['cookie'=>$tokens['ceremony_staff_a'],'csrf'=>$identity->csrf()->issueAuthenticated($session->session()->tokenDigest()),
 'environment'=>['MXMED_GOVERNANCE_TRUST_ANCHOR_PUBLIC_KEY'=>(string)getenv('MXMED_GOVERNANCE_TRUST_ANCHOR_PUBLIC_KEY'),
 'MXMED_GOVERNANCE_DEPLOYMENT_AUTHORIZATION'=>(string)getenv('MXMED_GOVERNANCE_DEPLOYMENT_AUTHORIZATION'),
 'MXMED_GOVERNANCE_DEPLOYMENT_AUTHORIZATION_SHA256'=>(string)getenv('MXMED_GOVERNANCE_DEPLOYMENT_AUTHORIZATION_SHA256')]];
file_put_contents($dir.'/http-fixture.json',json_encode($fixture,JSON_THROW_ON_ERROR));chmod($dir.'/http-fixture.json',0600);
echo "GOV03B_CANONICAL_CLI_INTEGRATION=PASS\n";
