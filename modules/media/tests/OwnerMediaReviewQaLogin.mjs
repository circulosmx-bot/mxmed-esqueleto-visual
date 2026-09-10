import {mkdir,writeFile,realpath} from 'node:fs/promises';

const literal=value=>"'"+value.replaceAll('\\','\\\\').replaceAll("'","\\'")+"'";

/** Test generator only. The prepend and token directory stay outside the web root. */
export async function installOwnerReviewQaLogin({root,fixtureRoot,doctor,identityDb}) {
 const documentRoot=await realpath(root),directory=fixtureRoot+'/qa-auth';
 await mkdir(directory,{mode:0o700});
 const prepend=directory+'/prepend.php';
 await writeFile(prepend,`<?php
function mr12a_qa_allowed(): bool {
    foreach(['APP_ENV','MXMED_ENV','MXMED_ENVIRONMENT','ENVIRONMENT'] as $name){
        $v=strtolower((string)getenv($name));
        if($v!==''&&!in_array($v,['local','development','test'],true))return false;
    }
    return PHP_SAPI==='cli-server'
        && getenv('MXMED_MR12A_SYNTHETIC_QA')==='1'
        && getenv('APP_ENV')==='local' && getenv('MXMED_ENVIRONMENT')==='local'
        && getenv('MXMED_DB_NAME')===${literal(identityDb)}
        && realpath($_SERVER['DOCUMENT_ROOT']??'')===${literal(documentRoot)}
        && ($_SERVER['HTTP_HOST']??'')==='127.0.0.1:8128'
        && in_array($_SERVER['REMOTE_ADDR']??'', ['127.0.0.1','::1'],true);
}
function mr12a_qa_identity() {
    require_once ${literal(documentRoot+'/modules/identity/http/IdentityHttpComposition.php')};
    \\Identity\\Http\\IdentityHttpComposition::registerAutoloader();
    return \\Identity\\Http\\IdentityHttpComposition::fromProcessEnvironment();
}
function mr12a_qa_token_path(string $handle): string {
    return ${literal(directory)}.'/'.hash('sha256',$handle).'.json';
}
// Translate only on reviewer routes in this explicit generated environment.
// An explicitly supplied canonical cookie, even invalid, always takes precedence.
$qaPath=parse_url($_SERVER['REQUEST_URI']??'',PHP_URL_PATH);
if(mr12a_qa_allowed() && is_string($qaPath)
    && (str_starts_with($qaPath,'/internal/media-review/') || str_starts_with($qaPath,'/api/internal/media-review/'))
    && !array_key_exists('__Host-mxmed_session',$_COOKIE)) {
    $handle=$_COOKIE['mxmed_mr12a_qa']??null;
    if(is_string($handle)&&preg_match('/^[0-9a-f]{64}$/D',$handle)) {
        $file=mr12a_qa_token_path($handle);
        $record=is_file($file)?json_decode(file_get_contents($file),true):null;
        if(is_array($record)&&is_string($record['token']??null)) $_COOKIE['__Host-mxmed_session']=$record['token'];
    }
}
`,{mode:0o600});
 await writeFile(root+'/qa-login.php',`<?php
if(!function_exists('mr12a_qa_allowed')||!mr12a_qa_allowed()){http_response_code(404);exit;}
header('Cache-Control: private, no-store');
header('X-Content-Type-Options: nosniff');
$role=$_GET['as']??null;
if(($_SERVER['REQUEST_METHOD']??'')!=='GET'||array_keys($_GET)!==['as']||!in_array($role,['owner','reviewer'],true)){http_response_code(400);exit;}
try {
    // Remove the previous harness cookie without relaxing its __Host semantics.
    setcookie('__Host-mxmed_session','',['expires'=>1,'path'=>'/','secure'=>true,'httponly'=>true,'samesite'=>'Lax']);
    // Clear the opposite QA identity when switching roles in the same browser.
    setcookie('mxmed_mr12a_qa','',['expires'=>1,'path'=>'/','httponly'=>true,'samesite'=>'Lax']);
    if($role==='reviewer') {
        $identity=mr12a_qa_identity();
        $s=$identity->pdo()->query("SELECT a.account_id,a.status,c.credential_version FROM auth_accounts a JOIN auth_account_credentials c ON c.account_id=a.account_id WHERE a.account_id='mr3_good'");
        $a=$s->fetch(PDO::FETCH_ASSOC);
        if(!$a)throw new RuntimeException('missing_synthetic_account');
        // Fixed synthetic MR3 account only; normal session and capability checks still apply.
        $candidate=new \\Identity\\Contracts\\AuthenticationPrincipalCandidate($a['account_id'],(int)$a['credential_version'],$a['status'],gmdate('Y-m-d H:i:s'));
        $created=$identity->sessions()->create($candidate);
        if(!$created->allowed())throw new RuntimeException('synthetic_session_unavailable');
        $handle=bin2hex(random_bytes(32));$path=mr12a_qa_token_path($handle);
        $old=umask(0077);
        try {if(file_put_contents($path,json_encode(['token'=>$created->token()->value()],JSON_THROW_ON_ERROR),LOCK_EX)===false)throw new RuntimeException('synthetic_session_storage');}
        finally {umask($old);}
        setcookie('PHPSESSID','',['expires'=>1,'path'=>'/','httponly'=>true,'samesite'=>'Lax']);
        // This is NOT the productive __Host cookie: only the generated prepend recognizes it.
        setcookie('mxmed_mr12a_qa',$handle,['path'=>'/','httponly'=>true,'samesite'=>'Lax']);
        header('Location: /internal/media-review/');
    } else {
        session_start(['use_strict_mode'=>true,'cookie_httponly'=>true,'cookie_samesite'=>'Lax']);
        session_regenerate_id(true);
        $_SESSION=['user_id'=>'mr12a_owner','doctor_id'=>${literal(doctor)}];
        session_write_close();
        header('Location: /index.html');
    }
} catch(Throwable $e){http_response_code(503);echo 'No se pudo iniciar la sesión sintética. Reinicia el entorno QA.';}
`,{mode:0o600});
 return {prepend,environment:{MXMED_MR12A_SYNTHETIC_QA:'1'}};
}
