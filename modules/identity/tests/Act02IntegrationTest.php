<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'||getenv('ACT02_QA')!=='1')exit(1);
require_once __DIR__.'/../http/IdentityHttpComposition.php';\Identity\Http\IdentityHttpComposition::registerAutoloader();
require_once __DIR__.'/support/Act02SyntheticComposition.php';
use Identity\Operational\{ActivationGuard,WorkflowFactory,DeploymentPackage};
$identity=\Identity\Http\IdentityHttpComposition::fromProcessEnvironment();$p=$identity->pdo();putenv('MXMED_GOVERNANCE_PROXY_KEY='.str_repeat('t',64));$x=Act02SyntheticComposition::build($identity);
function actCheck(bool $v,string $name):void{if(!$v)throw new RuntimeException($name);echo 'PASS '.$name.PHP_EOL;}
$a=$x['payload'];$run=fn(array $secrets,?\Agenda\Contracts\OtpProviderPort $sms)=>WorkflowFactory::create($identity,$x['authorization'],$x['trust'],$secrets,$sms);
try{
    if(getenv('ACT02_QA_PARTIAL_SCHEMA')==='1'){
        // The owning runner renamed a required table in THIS disposable schema.
        // Approval uses its actual current fingerprint: denial must be structural.
        try{$run($x['secrets'],$x['sms']);throw new LogicException('partial schema accepted');}
        catch(PDOException $e){actCheck(($e->errorInfo[0]??null)==='42S02'&&(int)($e->errorInfo[1]??0)===1146,'guard_denies_actual_partial_schema_with_matching_fingerprint');}
        actCheck(!$p->inTransaction(),'partial_schema_guard_leaves_no_transaction');
        return;
    }
    $bundle=$run($x['secrets'],$x['sms']);actCheck(isset($bundle['enrollment'],$bundle['reports'],$bundle['onboarding']),'shared_factory_positive_signed_synthetic_case');
    if(getenv('ASE_ALIGN01_QA')==='1'){
        $advisors=[];$governanceReviewer=null;
        foreach($p->query("SELECT s.account_id,s.governance_class,g.capability FROM internal_staff s JOIN internal_operator_grants g ON g.account_id=s.account_id WHERE s.status='ACTIVE' AND g.status='ACTIVE' AND g.revoked_at IS NULL AND EXISTS(SELECT 1 FROM auth_staff_mfa_factors f WHERE f.account_id=s.account_id AND f.status='ACTIVE') ORDER BY s.account_id")->fetchAll(PDO::FETCH_ASSOC) as $r){
            if($r['governance_class']==='ADVISOR'&&$r['capability']==='public_profile_correction_review'&&in_array($r['account_id'],$a['accounts'],true))$advisors[$r['capability']]??=$r['account_id'];
            if(!in_array($r['capability'],$a['staff'][$r['account_id']]??[],true))continue;
            if($r['governance_class']==='ADVISOR')$advisors[$r['capability']]??=$r['account_id'];
            elseif(in_array($r['governance_class'],['DIRECTOR','MASTER_ADMIN'],true)&&$r['capability']==='physician_accreditation_review')$governanceReviewer??=$r['account_id'];
        }
        actCheck(isset($advisors['physician_accreditation_review'],$advisors['professional_source_provision'],$advisors['public_profile_correction_review'])&&$governanceReviewer!==null,'advisor_guard_fixtures_have_independent_MFA_and_grants');
        $guard=function(array $approval)use($identity,$x):array{
            Act02SyntheticComposition::sign($x['authorization'],$approval,$x['signing_secret']);
            return ActivationGuard::verify($identity,$x['authorization'],$x['trust'],$x['secrets']);
        };
        // The principal is restricted for the full ACT03 selection. Keep that
        // selection: narrowing workflows would correctly reject its source INSERT grants.
        $review=$a;$review['staff']=[$governanceReviewer=>['physician_accreditation_review']];
        foreach(['physician_accreditation_review','professional_source_provision'] as $cap)$review['staff'][$advisors[$cap]][]=$cap;
        actCheck($guard($review)['staff']===$review['staff'],'guard_accepts_individually_approved_routine_review_advisor');
        $sources=$a;
        $sources['staff']=[$governanceReviewer=>['physician_accreditation_review'],$advisors['professional_source_provision']=>['professional_source_provision']];
        actCheck($guard($sources)['staff']===$sources['staff'],'guard_accepts_individually_approved_source_advisor');
        foreach([['issuer_only',['c4_issuer'],'act02_staff_capability_or_MFA_missing'],['issuer_and_review',['enrollment_review','c4_evidence','c4_issuer'],'act02_staff_coverage_missing']] as [$label,$workflows,$denial]){
            $bad=$a;$bad['workflows']=$workflows;$bad['staff']=[$advisors['physician_accreditation_review']=>['physician_accreditation_review']];
            try{$guard($bad);throw new LogicException('advisor authorized issuer');}
            catch(RuntimeException $e){actCheck($e->getMessage()===$denial,'guard_rejects_advisor_as_reserved_issuer_staff_'.$label);}
        }
        $bad=$a;$bad['workflows']=['correction_review'];$bad['staff']=[$advisors['public_profile_correction_review']=>['public_profile_correction_review']];
        try{$guard($bad);throw new LogicException('advisor correction activation broadened');}
        catch(RuntimeException $e){actCheck($e->getMessage()==='act02_staff_capability_or_MFA_missing','guard_preserves_correction_staff_class_restriction');}
        $guard($a);
    }
    $tampered=json_decode(file_get_contents($x['authorization']),true,32,JSON_THROW_ON_ERROR);$tampered['signature']=base64_encode(str_repeat('0',64));file_put_contents($x['authorization'],json_encode($tampered,JSON_THROW_ON_ERROR));
    try{$run($x['secrets'],$x['sms']);throw new LogicException('invalid signature accepted');}
    catch(RuntimeException $e){actCheck($e->getMessage()==='act02_signature_required','guard_denies_invalid_approval_signature');}
    Act02SyntheticComposition::sign($x['authorization'],$a,$x['signing_secret']);
    $security=new \Identity\Services\GovernanceSecurityContext($identity->sessions(),$identity->authentication());
    $cipher=new \Identity\Accreditation\EvidenceCipher($x['secrets']['evidence_key_id'],[$x['secrets']['evidence_key_id']=>$x['secrets']['evidence_key']],$a['policies']['retention']);
    $enrollment=fn($scope)=>new \Identity\Accreditation\PublicEnrollmentService(new \Identity\Accreditation\EvidenceAuthority($p,$security,scope:$scope),$cipher,$x['secrets']['proof_key']);
    $service=$enrollment($bundle['scope']);$browser=\Identity\Accreditation\PublicEnrollmentService::opaque();
    $ticket=$service->ticket('CLAIM_EXISTING_PROFILE','act02_plan_0','/profiles/doctor.php?doctor_id=act02_plan_0',$browser);$origin=$service->start($ticket,$browser);
    actCheck($service->start($ticket,$browser)['application_id']===$origin['application_id'],'actual_enrollment_start_replay_preserves_allowed_origin');
    $narrow=$a;$narrow['profiles']=array_values(array_diff($narrow['profiles'],['act02_plan_0']));
    try{$enrollment(new \Identity\Operational\WorkflowScope($narrow))->start($ticket,$browser);throw new LogicException('origin replay escaped pilot cohort');}
    catch(\Identity\Operational\WorkflowScopeDenied $e){actCheck($e->status===403,'actual_enrollment_existing_origin_replay_denies_removed_pilot_profile');}
    $p->exec('SET SESSION foreign_key_checks=0');
    try{
        try{$run($x['secrets'],$x['sms']);throw new LogicException('disabled integrity accepted');}
        catch(RuntimeException $e){actCheck($e->getMessage()==='act02_native_integrity_not_enforced','guard_denies_disabled_native_FK_enforcement');}
    }finally{$p->exec('SET SESSION foreign_key_checks=1');}
    if(getenv('ACT02_QA_SUITE')==='application'){
        $login=$identity->authentication()->authenticate('app_ui_0@example.invalid','Only-Synthetic-Correction-01!',[]);
        if(!$login->isAllowed())throw new RuntimeException('act02_test_login_failed');
        $created=$identity->sessions()->create($login->candidate());$actor=$identity->sessions()->validate((string)$created->token())->context();
        actCheck(count($bundle['reports']->review->queue($actor)['reports'])>0,'actual_restricted_staff_queue_and_canonical_audit');
        $narrow=$a;unset($narrow['staff'][$actor->accountId()]);
        Act02SyntheticComposition::sign($x['authorization'],$narrow,$x['signing_secret']);$limited=$run($x['secrets'],$x['sms']);
        try{$limited['reports']->review->queue($actor);throw new LogicException('unapproved staff actor allowed');}
        catch(RuntimeException $e){actCheck($e->getMessage()==='act02_staff_outside_approved_capability','actual_review_denies_valid_staff_omitted_from_pilot_approval');}
        $authority=new \Profiles\Corrections\ApplicationAuthority($limited['reports']->reports,new \Identity\Services\GovernanceSecurityContext($identity->sessions(),$identity->authentication()));
        try{$authority->transaction($actor,fn()=>null);throw new LogicException('unapproved apply actor allowed');}
        catch(\Profiles\Corrections\ReportDenied $e){actCheck($e->status===403,'actual_application_denies_valid_grant_omitted_from_pilot_approval');}
        Act02SyntheticComposition::sign($x['authorization'],$a,$x['signing_secret']);
    }
    if(getenv('ACT02_QA_SUITE')==='resolution'){
        $login=$identity->authentication()->authenticate('enr_reviewer@example.invalid','Only-Synthetic-C4-EV01!',[]);
        if(!$login->isAllowed())throw new RuntimeException('act02_test_reviewer_login_failed');
        $created=$identity->sessions()->create($login->candidate());$cookies=['__Host-mxmed_session'=>(string)$created->token()];
        $state=$bundle['onboarding']->page($cookies,true);$outside=null;
        foreach($state['requests'] as $row){$q=$p->prepare('SELECT account_id FROM auth_physician_evidence_requests WHERE request_id=?');$q->execute([$row['request_id']]);$account=$q->fetchColumn();if(!isset($a['staff'][$account])){$outside=[$account,$row['request_id']];break;}}
        if($outside===null)throw new RuntimeException('act02_account_queue_fixture_missing');
        $narrow=$a;$narrow['accounts']=array_values(array_diff($narrow['accounts'],[$outside[0]]));
        Act02SyntheticComposition::sign($x['authorization'],$narrow,$x['signing_secret']);$limited=$run($x['secrets'],$x['sms']);
        $state=$limited['onboarding']->page($cookies,true);
        actCheck(!in_array($outside[1],array_column($state['requests'],'request_id'),true),'actual_C4_queue_excludes_outside_account_on_approved_profile');
        foreach($state['enrollment_review']['queue'] as $row){$q=$p->prepare('SELECT account_id FROM auth_physician_enrollment_origins WHERE origin_id=?');$q->execute([$row['application_id']]);if($q->fetchColumn()===$outside[0])throw new RuntimeException('enrollment_queue_outside_account');}
        actCheck(true,'actual_enrollment_queue_excludes_outside_account');
        $narrow=$a;unset($narrow['staff']['enr_reviewer']);
        // ASE's remaining reviewers are advisors. This consumer omission test
        // exercises evidence reads only; it does not request issuer activation.
        if(getenv('ASE_ALIGN01_QA')==='1')$narrow['workflows']=array_values(array_diff($narrow['workflows'],['c4_issuer']));
        Act02SyntheticComposition::sign($x['authorization'],$narrow,$x['signing_secret']);$limited=$run($x['secrets'],$x['sms']);
        try{$limited['onboarding']->page($cookies,true);throw new LogicException('unapproved C4 reviewer allowed');}
        catch(RuntimeException $e){actCheck($e->getMessage()==='act02_staff_outside_approved_capability','actual_C4_denies_valid_staff_omitted_from_pilot_approval');}
        Act02SyntheticComposition::sign($x['authorization'],$a,$x['signing_secret']);
    }
    foreach(['expired','future','wrong_server','wrong_schema','wrong_source','missing_staff','missing_staff_capability','unknown_workflow','missing_policy','pilot_on_clone'] as $case){
        $bad=$a;
        match($case){'expired'=>$bad['expires_at']=time()-1,'future'=>$bad['issued_at']=time()+60,'wrong_server'=>$bad['identity']['server_uuid']='wrong',
            'wrong_schema'=>$bad['schema_fingerprint']=str_repeat('0',64),'wrong_source'=>$bad['source_hash']=str_repeat('0',64),
            'missing_staff'=>$bad['staff']=[],
            'missing_staff_capability'=>$bad['staff']=[getenv('ACT02_QA_SUITE')==='application'?'cor_user':'enr_b'=>['physician_accreditation_review']],
            'unknown_workflow'=>$bad['workflows']=['clinical_signing'],'missing_policy'=>$bad['policies']['retention']=null,
            'pilot_on_clone'=>$bad['mode']='pilot'};
        Act02SyntheticComposition::sign($x['authorization'],$bad,$x['signing_secret']);
        try{$run($x['secrets'],$x['sms']);throw new LogicException('guard accepted '.$case);}catch(RuntimeException $e){actCheck(str_starts_with($e->getMessage(),'act02_'),'guard_denies_'.$case);}
    }
    Act02SyntheticComposition::sign($x['authorization'],$a,$x['signing_secret']);
    foreach(['evidence_key','report_key','proof_key','mfa_key','transport_key','audit_key'] as $key){$s=$x['secrets'];unset($s[$key]);try{$run($s,$x['sms']);throw new LogicException('missing key allowed');}catch(RuntimeException $e){actCheck($e->getMessage()==='act02_secret_required','guard_denies_missing_'.$key);}}
    try{$run($x['secrets'],null);throw new LogicException('missing SMS allowed');}catch(RuntimeException $e){actCheck($e->getMessage()==='act02_real_sms_adapter_pending','real_SMS_integration_remains_closed');}
    $q=$p->prepare("SELECT account_id,capability FROM internal_operator_grants WHERE status='ACTIVE' AND revoked_at IS NULL AND account_id=? AND capability=?");$staff=array_key_first($a['staff']);$cap=$a['staff'][$staff][0];
    // Restricted application principal cannot grant/revoke platform authority or edit clinical/profile/subscription rows.
    foreach(["UPDATE internal_operator_grants SET status=status","UPDATE auth_account_memberships SET status=status","UPDATE profiles_doctors SET display_name=display_name","UPDATE profile_subscriptions SET status=status","UPDATE clinical_documents SET status=status","UPDATE auth_staff_mfa_factors SET status=status"] as $sql){try{$p->exec($sql);throw new LogicException('SQL scope too broad');}catch(PDOException $e){actCheck(in_array((int)($e->errorInfo[1]??0),[1142,1143],true),'restricted_principal_denies_'.strtok(substr($sql,7),' '));}}
    foreach([['profile','outside-pilot'],['account','outside-pilot'],['workflow','clinical_signing']] as [$method,$value]){try{$bundle['scope']->$method($value);throw new LogicException('cohort bypass');}catch(RuntimeException $e){actCheck(str_starts_with($e->getMessage(),'act02_'),'exact_cohort_or_action_denies_'.$method);}}
    try{$bundle['reports']->reports->intent('outside-pilot','/profiles/doctor.php?doctor_id=outside-pilot',bin2hex(random_bytes(32)));throw new LogicException('intake cohort bypass');}catch(RuntimeException $e){actCheck($e->getMessage()==='act02_profile_outside_cohort'||$e->getMessage()==='act02_workflow_denied','actual_report_intake_denies_outside_pilot_before_writer');}
    $profile=$a['profiles'][0];
    // Explicit FREE without changing legacy/default policy, and paid source wins.
    require_once dirname(__DIR__,2).'/subscriptions/services/PublicCommercialPlanSource.php';
    $source=new \Subscriptions\Services\PublicCommercialPlanSource($p);
    foreach(['free','basic','standard','optimum','professional',null,'unknown'] as $code){
        $row=['doctor_id'=>'not-a-subscribed-fixture','preloaded_free_plan'=>$code,'preloaded_free_reference'=>'synthetic-mapping-01','preloaded_free_recorded_at'=>'2026-10-16 00:00:00'];
        $resolved=$source->resolve($row);actCheck($code==='free'?$resolved['plan_code']==='free':$resolved['plan_code']===null,'explicit_preload_never_infers_FREE_'.($code??'missing'));
    }
    actCheck(\Identity\Operational\OperationalRuntime::onboarding()===null,'normal_fixed_path_missing_approval_does_not_activate');
    actCheck(\Identity\Operational\SmsIntegration::APPROVED_ADAPTERS===[],'no_synthetic_production_SMS_adapter');
    echo "ACT02_NATIVE=PASS\n";
}finally{unlink($x['authorization']);unlink($x['trust']);unlink($x['secrets']['_source_path']);}
