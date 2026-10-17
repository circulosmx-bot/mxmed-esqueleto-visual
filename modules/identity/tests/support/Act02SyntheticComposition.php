<?php
declare(strict_types=1);
/** Synthetic signing fixture: impossible on mxmed, never imported by OperationalRuntime. */
final class Act02SyntheticComposition
{
    public static function build(\Identity\Http\IdentityHttpComposition $identity):array
    {
        $p=$identity->pdo();$db=$p->query('SELECT DATABASE()')->fetchColumn();
        if(getenv('ACT02_QA')!=='1'||getenv('MXMED_NORMAL_LOCAL_IDENTITY_EXPLICIT')!=='0'
            ||!preg_match('/^mxmed_gate4d_preview_(cor02|c4ui)_([a-f0-9]{10})$/D',$db,$m)
            ||$p->query('SELECT CURRENT_USER()')->fetchColumn()!=='gov03b_'.$m[2].'@127.0.0.1')throw new RuntimeException('act02_synthetic_target_required');
        require_once __DIR__.'/C4UiSyntheticSms.php';
        $out=(string)getenv('GOV03B_QA_EVIDENCE');$dir=$out.'/activation-synthetic';
        if(!is_dir($dir))mkdir($dir,0700);$secretFile=$dir.'/signing.key';
        if(!is_file($secretFile)){file_put_contents($secretFile,sodium_crypto_sign_secretkey(sodium_crypto_sign_keypair()));chmod($secretFile,0600);}
        $secret=\Identity\Operational\ActivationGuard::privateFile($secretFile);$key=sodium_crypto_sign_publickey_from_secretkey($secret);
        $report=$m[1]==='cor02';$workflows=$report?\Identity\Operational\ActivationGuard::WORKFLOWS:['public_enrollment','enrollment_review','c4_evidence','c4_issuer'];
        $secrets=['evidence_key'=>base64_decode((string)getenv('C4_EV_QA_KEY'),true),'evidence_key_id'=>'c4-qa-key',
            'report_key'=>base64_decode((string)getenv('PUB_COR01_KEY'),true),'report_key_id'=>'cor-qa-key',
            'proof_key'=>getenv('PUB_ENR02_QA_PROOF_KEY')?:str_repeat('q',64),'mfa_key'=>str_repeat('m',32),'mfa_key_id'=>$report?'cor-qa-mfa':'c4-qa-mfa',
            'transport_key'=>getenv('MXMED_GOVERNANCE_PROXY_KEY')?:str_repeat('t',64),'audit_key'=>(string)getenv('MXMED_AUDIT_IP_HMAC_SECRET'),'storage_root'=>(string)getenv('C4_EV_QA_STORAGE_ROOT')];
        if(!is_dir($secrets['storage_root']))mkdir($secrets['storage_root'],0700,true);
        $secrets['storage_root']=realpath($secrets['storage_root']);
        $source=$dir.'/secrets-'.bin2hex(random_bytes(8)).'.json';$encoded=$secrets;
        foreach(['evidence_key','report_key','proof_key','mfa_key','transport_key','audit_key'] as $name)$encoded[$name]=base64_encode($encoded[$name]);
        file_put_contents($source,json_encode($encoded,JSON_THROW_ON_ERROR));chmod($source,0600);$secrets['_source_path']=$source;
        $staff=[];$caps=$report?['physician_accreditation_review','public_profile_correction_review','public_profile_correction_apply']:['physician_accreditation_review'];
        foreach($p->query("SELECT s.account_id,g.capability FROM internal_staff s JOIN internal_operator_grants g ON g.account_id=s.account_id JOIN auth_accounts a ON a.account_id=s.account_id WHERE s.status='ACTIVE' AND a.status='active' AND g.status='ACTIVE' AND g.revoked_at IS NULL AND EXISTS(SELECT 1 FROM auth_staff_mfa_factors f WHERE f.account_id=s.account_id AND f.status='ACTIVE') ORDER BY s.account_id,g.capability")->fetchAll(PDO::FETCH_ASSOC) as $r)
            if(in_array($r['capability'],$caps,true))$staff[$r['account_id']][]=$r['capability'];
        $payload=['contract'=>'MXMED-ACT02.1','state'=>'approved_pilot','mode'=>'synthetic','issued_at'=>time(),'expires_at'=>time()+3600,
            'identity'=>\Identity\Operators\OperatorDeploymentSchema::identity($p),'deployment_principal'=>'synthetic-owning-qa-deployer','origin'=>$identity->allowedOrigin(),
            'source_hash'=>\Identity\Operational\DeploymentPackage::sourceHash(),'schema_fingerprint'=>\Identity\Operational\DeploymentPackage::schemaFingerprint($p),
            'policies'=>array_fill_keys(['privacy','custody','recovery','retention','legal_hold','disposal','staff','pilot','abuse'],'synthetic-only-no-real-retention-approval'),
            'workflows'=>$workflows,'accounts'=>$p->query('SELECT account_id FROM auth_accounts ORDER BY account_id')->fetchAll(PDO::FETCH_COLUMN),
            'profiles'=>$p->query('SELECT doctor_id FROM profiles_doctors ORDER BY doctor_id')->fetchAll(PDO::FETCH_COLUMN),'staff'=>$staff,
            'rate_limits'=>['browser'=>3,'peer'=>100,'profile'=>20],'secret_hashes'=>[],'storage_root'=>$secrets['storage_root'],
            'secret_source'=>['path'=>$source,'sha256'=>hash_file('sha256',$source)]];
        foreach(['evidence_key','report_key','mfa_key'] as $name)$payload[$name.'_id']=$secrets[$name.'_id'];
        foreach(['evidence_key','report_key','proof_key','mfa_key','transport_key','audit_key'] as $name)$payload['secret_hashes'][$name]=hash('sha256',$secrets[$name]);
        $token=bin2hex(random_bytes(8));$authorization=$dir.'/approval-'.$token.'.json';$trust=$dir.'/trust-'.$token.'.json';
        file_put_contents($trust,json_encode(['public_key'=>base64_encode($key)],JSON_THROW_ON_ERROR));chmod($trust,0600);
        self::sign($authorization,$payload,$secret);
        return ['authorization'=>$authorization,'trust'=>$trust,'secrets'=>$secrets,'payload'=>$payload,'signing_secret'=>$secret,'sms'=>new C4UiSyntheticSms()];
    }
    public static function sign(string $path,array $payload,string $secret):void
    {$bytes=json_encode($payload,JSON_THROW_ON_ERROR);file_put_contents($path,json_encode(['payload'=>base64_encode($bytes),'signature'=>base64_encode(sodium_crypto_sign_detached($bytes,$secret))],JSON_THROW_ON_ERROR));chmod($path,0600);}
    public static function install(\Identity\Http\IdentityHttpComposition $identity):array
    {
        $x=self::build($identity);
        try{$bundle=\Identity\Operational\WorkflowFactory::create($identity,$x['authorization'],$x['trust'],$x['secrets'],$x['sms']);}
        finally{unlink($x['authorization']);unlink($x['trust']);unlink($x['secrets']['_source_path']);}
        \Identity\Accreditation\PublicEnrollmentRuntime::install($bundle['enrollment']);\Profiles\Corrections\ReportRuntime::install($bundle['reports']);
        return $bundle;
    }
}
