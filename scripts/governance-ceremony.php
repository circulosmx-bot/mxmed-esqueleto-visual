<?php
declare(strict_types=1);
/** Credentials are read only from an owner-only file; never command arguments or logs. */
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require_once dirname(__DIR__).'/modules/identity/http/IdentityHttpComposition.php';
Identity\Http\IdentityHttpComposition::registerAutoloader();
use Identity\Http\{IdentityHttpComposition,CanonicalHttpSessionResolver,GovernanceHttpComposition};
try{
    if(getenv('MXMED_GOVERNANCE_CEREMONY_ENABLED')!=='1')throw new RuntimeException('ceremony_not_authorized');
    $args=getopt('',['action:','input:','secret-output:']);$action=$args['action']??'';
    if(!in_array($action,['bootstrap','mfa_begin','mfa_activate','mfa_cancel'],true))throw new RuntimeException('ceremony_action_denied');
    $read=static function(string $path):array{
        $parent=realpath(dirname($path));$root=realpath(dirname(__DIR__));
        if(!is_file($path)||is_link($path)||is_link(dirname($path))||$parent===false||str_starts_with($parent,$root)
            ||(fileperms($path)&0077)!==0||(fileperms($parent)&0077)!==0)throw new RuntimeException('private_ceremony_input_required');
        return json_decode(file_get_contents($path),true,32,JSON_THROW_ON_ERROR);
    };
    $input=$read((string)($args['input']??''));$target=(string)($input['target_account_id']??'');
    $identity=IdentityHttpComposition::fromProcessEnvironment();
    $composition=GovernanceHttpComposition::fromIdentity($identity,$action,$target);
    $resolver=new CanonicalHttpSessionResolver($identity->sessions());
    $subject=$resolver->resolve(['__Host-mxmed_session'=>$input['session_token']??null]);
    if($subject===null||$subject->accountId()!==$target)throw new RuntimeException('ceremony_subject_denied');
    $reauthentication=$composition->security->reauthenticate($subject,(string)($input['email']??''),(string)($input['password']??''),['ip'=>'governance-ceremony']);
    if($action==='bootstrap'){
        $approval=\Identity\Http\GovernanceDeploymentAuthorization::require($identity->pdo(),$action,$target,$identity->environment());
        if(($approval['operation_id']??null)!==($input['operation_id']??null)||($approval['case_reference']??null)!==($input['case_reference']??null)||!is_string($input['case_reference']??null))throw new RuntimeException('bootstrap_case_not_deployment_approved');
        $result=$composition->bootstrap->execute((string)$input['operation_id'],$target,(string)($input['signed_evidence']??''),$reauthentication,$input['case_reference']);
    }
    elseif($action==='mfa_begin'){
        $output=(string)($args['secret-output']??'');$parent=realpath(dirname($output));$root=realpath(dirname(__DIR__));
        if($output===''||file_exists($output)||is_link($output)||$parent===false||is_link(dirname($output))
            ||str_starts_with($parent,$root)||(fileperms($parent)&0077)!==0)throw new RuntimeException('private_secret_output_required');
        // Reserve before mutation. A failure leaves an empty private artifact, never a lost logged secret.
        $old=umask(0077);$handle=fopen($output,'x');umask($old);if($handle===false)throw new RuntimeException('private_secret_output_unavailable');
        try{
            $registrar=$resolver->resolve(['__Host-mxmed_session'=>$input['registrar_session_token']??null]);
            if($registrar===null)throw new RuntimeException('ceremony_registrar_denied');
            $secret=$composition->enrollment->begin($subject,$registrar,(string)$input['email'],(string)$input['password'],
                (string)($input['registrar_email']??''),(string)($input['registrar_password']??''));
            $bytes=json_encode($secret,JSON_THROW_ON_ERROR);if(fwrite($handle,$bytes)!==strlen($bytes)||!fflush($handle))throw new RuntimeException('private_secret_output_failed');
            $result=['factor_id'=>$secret['factor_id'],'state'=>'PENDING'];
        }finally{fclose($handle);}
    }elseif($action==='mfa_activate'){$composition->enrollment->activate($subject,(string)($input['factor_id']??''),(string)($input['code']??''));$result=['state'=>'ACTIVE'];}
    else{$composition->enrollment->cancel($subject,(string)($input['factor_id']??''));$result=['state'=>'REVOKED'];}
    echo json_encode(['ok'=>true,'data'=>$result],JSON_THROW_ON_ERROR).PHP_EOL;
}catch(Throwable){fwrite(STDERR,"GOVERNANCE_CEREMONY_DENIED: no privileged success confirmed; inspect approved audit evidence.\n");exit(78);}
