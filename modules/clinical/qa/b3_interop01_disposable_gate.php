<?php
declare(strict_types=1);

require_once dirname(__DIR__,3).'/api/_lib/healthcare_study_interop.php';

final class DisposableReleasedAuthority implements ProviderReleasedResultAuthority
{
    public function assertReleased(array $release): void
    {
        if (($release['qa_released']??false)!==true) throw new RuntimeException('NOT_RELEASED');
    }
}

$db=getenv('INTEROP_QA_DB');$private=getenv('INTEROP_QA_PRIVATE');
if (!is_string($db)||!preg_match('/^b3_interop01_[a-f0-9]{10}$/',$db)||!is_string($private)) exit(2);
$pdo=new PDO('mysql:host=localhost;dbname='.$db.';charset=utf8mb4','root','',[
    PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
$service=new HealthcareStudyInteropService($pdo);
$storage=new ClinicalPrivateBinaryStorage($private,dirname(__DIR__,3));
$authority=new DisposableReleasedAuthority();
$check=static function(bool $ok,string $name): void {if(!$ok)throw new RuntimeException($name.'=FAIL');echo $name."=PASS\n";};
$deny=static function(callable $task,string $code,string $name)use($check):void{
    try{$task();}catch(Throwable $e){$check($e->getMessage()===$code,$name);return;}
    $check(false,$name);
};
$query=static function(string $sql,array $args=[])use($pdo):array{$s=$pdo->prepare($sql);$s->execute($args);return $s->fetchAll(PDO::FETCH_ASSOC);};
$execute=static function(string $sql,array $args=[])use($pdo):int{$s=$pdo->prepare($sql);$s->execute($args);return (int)$pdo->lastInsertId();};
$execute("INSERT INTO patients_patients(patient_id,display_name) VALUES('p_interop','Interop Patient'),('p_foreign','Foreign Patient')");
$execute("INSERT INTO patients_doctor_links(link_id,doctor_id,patient_id,status) VALUES
    ('link_interop','d_interop','p_interop','active'),('link_foreign','d_foreign','p_foreign','active')");
foreach (['org_a','org_b'] as $group) {
    $execute('INSERT INTO medical_groups(group_id,organization_type_key,canonical_name,display_name,status)
        VALUES (?,\'LABORATORY\',?,?,\'verified\')',[$group,$group,$group]);
    $execute("INSERT INTO healthcare_organization_provider_status
        (group_id,operational_state,verification_state,verification_actor_user_id,verification_at)
        VALUES (?,'ACTIVE','VERIFIED','qa',UTC_TIMESTAMP())",[$group]);
}
$locations=[];
foreach (['org_a','org_b'] as $group) {
    $locations[$group]=$execute("INSERT INTO healthcare_organization_locations
        (location_uuid,group_id,branch_name,operational_state,verification_state,verification_actor_user_id,verification_at)
        VALUES (?,?,'QA Branch','ACTIVE','VERIFIED','qa',UTC_TIMESTAMP())",[mxmed_uuidv4(),$group]);
}
foreach (['org_a'=>[1,2],'org_b'=>[3]] as $group=>$studies) {
    foreach ($studies as $study) {
        $master=$execute('INSERT INTO healthcare_organization_master_services(master_service_uuid,group_id,study_type_id)
            VALUES (?,?,?)',[mxmed_uuidv4(),$group,$study]);
        $execute("INSERT INTO healthcare_organization_location_study_offerings
            (location_id,group_id,study_type_id,master_service_id,verification_state,verification_actor_user_id,verification_at)
            VALUES (?,?,?,?, 'VERIFIED','qa',UTC_TIMESTAMP())",[$locations[$group],$group,$study,$master]);
    }
}
$createOrder=static function(string $patient,array $studyIds,string $title)use($pdo):array {
    $items=array_map(static fn(int $id):array=>['study_type_id'=>$id],$studyIds);
    $payload=$items===[]?['legacy_test'=>true]:clinical_study_normalize_order_payload($pdo,'orders',['order_items'=>$items]);
    $doc=mxmed_build_clinical_document(['type'=>'orders','title'=>$title,'context'=>['patient_id'=>$patient,
        'care_setting'=>'consulta'],'payload'=>$payload,'actor'=>['user_id'=>'u_interop']]);
    $pdo->beginTransaction();
    try{$id=mxmed_persist_clinical_document_in_transaction($pdo,$doc);$pdo->commit();}
    catch(Throwable $e){$pdo->rollBack();throw $e;}
    return ['id'=>$id,'uuid'=>$doc['document_id'],'payload'=>$payload];
};
$order=$createOrder('p_interop',[1,2,3],'Interop V1');
$other=$createOrder('p_foreign',[1],'Foreign order');
$ids=array_column($order['payload']['order_items'],'order_item_id');
$foreignId=$other['payload']['order_items'][0]['order_item_id'];
$check(count($ids)===3,'QA_ZERO_PROVIDER_ORDER');
$refA=$service->sendReferral('d_interop','p_interop',$order['uuid'],1,'org_a',$locations['org_a'],
    [$ids[0],$ids[1]],'u_interop','interop-key-a');
$check(!$refA['replayed']&&$refA['referral_id']>0,'QA_REFERRAL_CREATE');
$same=$service->sendReferral('d_interop','p_interop',$order['uuid'],1,'org_a',$locations['org_a'],
    [$ids[1],$ids[0]],'u_interop','interop-key-a');
$check($same['referral_id']===$refA['referral_id']&&$same['replayed'],'QA_REFERRAL_IDEMPOTENCY');
$deny(fn()=>$service->sendReferral('d_interop','p_interop',$order['uuid'],1,'org_a',$locations['org_a'],
    [$ids[0]],'u_interop','interop-key-a'),'REFERRAL_IDEMPOTENCY_CONFLICT','QA_REFERRAL_IDEMPOTENCY_CONFLICT');
$deny(fn()=>$service->sendReferral('d_interop','p_interop',$order['uuid'],1,'org_a',$locations['org_a'],
    [$foreignId],'u_interop','interop-key-foreign'),'REFERRAL_ITEM_NOT_CANONICAL_SOURCE','QA_CROSS_PATIENT_DENIAL');
$deny(fn()=>$service->sendReferral('d_interop','p_interop',$order['uuid'],1,'org_a',$locations['org_b'],
    [$ids[0]],'u_interop','interop-key-wrongloc'),'REFERRAL_TARGET_INELIGIBLE','QA_CROSS_PROVIDER_DENIAL');
$deny(fn()=>$service->sendReferral('d_interop','p_interop',$other['uuid'],1,'org_a',$locations['org_a'],
    [$foreignId],'u_interop','interop-key-crossorder'),'REFERRAL_ORDER_NOT_FOUND','QA_CROSS_ORDER_DENIAL');
$deny(fn()=>$service->sendReferral('d_interop','p_interop',$order['uuid'],1,'org_b',$locations['org_b'],
    [$ids[0]],'u_interop','interop-key-nooffer'),'REFERRAL_STUDY_NOT_OFFERED','QA_TARGET_COVERAGE_DENIAL');
$deny(fn()=>$service->sendReferral('d_foreign','p_interop',$order['uuid'],1,'org_a',$locations['org_a'],
    [$ids[0]],'u_foreign','interop-key-doctor'),'REFERRAL_PATIENT_SCOPE_DENIED','QA_UNRELATED_DOCTOR_DENIAL');
$legacy=$createOrder('p_interop',[],'Legacy order');
$deny(fn()=>$service->sendReferral('d_interop','p_interop',$legacy['uuid'],1,'org_a',$locations['org_a'],
    [$ids[0]],'u_interop','interop-key-legacy'),'REFERRAL_V2_ITEMS_REQUIRED','QA_LEGACY_V1_REFERRAL_DENIED');
$void=$createOrder('p_interop',[1],'Void order');
$execute("UPDATE clinical_documents SET status='voided' WHERE id=?",[$void['id']]);
$deny(fn()=>$service->sendReferral('d_interop','p_interop',$void['uuid'],1,'org_a',$locations['org_a'],
    [$void['payload']['order_items'][0]['order_item_id']],'u_interop','interop-key-void'),
    'REFERRAL_ORDER_NOT_ISSUED','QA_VOIDED_ORDER_REFERRAL_DENIED');
$read=$service->referralRead($refA['referral_uuid']);
$expectedA=[$ids[0],$ids[1]];sort($expectedA,SORT_STRING);
$check((int)$read['source_order_document_id']===$order['id'] && (int)$read['source_order_version']===1
    && array_column($read['items'],'source_order_item_id')===$expectedA,'QA_REFERRAL_EXACT_VERSION');
$check(count($read['items'])===2,'QA_REFERRAL_ITEM_SCOPE');
$before=$query('SELECT payload_json,status FROM clinical_documents WHERE id=?',[$order['id']])[0];
$accepted=$service->acceptReferral($refA['referral_uuid'],'qa_provider_release_adapter');
$check(!$accepted['replayed'],'QA_REFERRAL_ACCEPT');
$replayAccept=$service->acceptReferral($refA['referral_uuid'],'qa_provider_release_adapter');
$check($replayAccept['service_order_id']===$accepted['service_order_id']&&$replayAccept['replayed']
    && count($query('SELECT service_order_id FROM healthcare_provider_service_orders WHERE referral_id=?',[$refA['referral_id']]))===1,
    'QA_DUPLICATE_ACCEPT');
$serviceItems=$query('SELECT source_order_item_id FROM healthcare_provider_service_order_items WHERE service_order_id=? ORDER BY source_order_item_id',
    [$accepted['service_order_id']]);
$check(array_column($serviceItems,'source_order_item_id')===$expectedA,'QA_SERVICE_ORDER_ITEMS');
$check($before===$query('SELECT payload_json,status FROM clinical_documents WHERE id=?',[$order['id']])[0],
    'QA_SERVICE_ORDER_PHYSICIAN_IMMUTABILITY');
$artifact=static function()use($storage):array{
    $file=tempnam(sys_get_temp_dir(),'interop-pdf-');
    file_put_contents($file,"%PDF-1.4\n1 0 obj<</Type/Catalog>>endobj\n%%EOF\n");
    try{$stage=$storage->stageFile($file,'provider-result.pdf');}
    finally{unlink($file);}
    $final=$storage->buildFinalKey(mxmed_uuidv4(),mxmed_uuidv4(),'ORIGINAL');
    $storage->finalizeCreateOnly($stage['staging_key'],$final,$stage['sha256'],$stage['byte_length']);
    $storage->deleteUncommitted($stage['staging_key']);
    return ['storage_key'=>$final,'sha256'=>$stage['sha256'],'byte_length'=>$stage['byte_length'],
        'mime_type'=>$stage['mime_type'],'source_filename'=>'provider-result.pdf'];
};
$race=static function(string $operation,array $args)use($db,$private):array{
    $processes=[];$paths=[];$start=microtime(true)+0.35;
    for($i=0;$i<2;$i++){
        $input=tempnam(sys_get_temp_dir(),'interop-race-in-');$output=tempnam(sys_get_temp_dir(),'interop-race-out-');
        $error=tempnam(sys_get_temp_dir(),'interop-race-err-');
        file_put_contents($input,json_encode(['db'=>$db,'private'=>$private,'start'=>$start,
            'operation'=>$operation,'args'=>$args],JSON_THROW_ON_ERROR));chmod($input,0600);
        $process=proc_open(['php',__DIR__.'/b3_interop01_race_worker.php',$input],
            [['pipe','r'],['file',$output,'w'],['file',$error,'w']],$pipes);
        if(!is_resource($process))throw new RuntimeException('QA_PROCESS_FAILED');
        fclose($pipes[0]);$processes[]=$process;$paths[]=[$input,$output,$error];
    }
    $out=[];
    foreach($processes as $i=>$process){
        $exit=proc_close($process);
        [$input,$output,$error]=$paths[$i];
        $decoded=json_decode((string)file_get_contents($output),true);
        if($exit!==0 || !is_array($decoded))throw new RuntimeException('QA_RACE_WORKER_FAILED: '.file_get_contents($error));
        $out[]=$decoded;unlink($input);unlink($output);unlink($error);
    }
    return $out;
};
$concurrentReferral=$race('send',['d_interop','p_interop',$order['uuid'],1,'org_a',$locations['org_a'],
    [$ids[0]],'u_interop','interop-key-race']);
$check($concurrentReferral[0]['ok']&&$concurrentReferral[1]['ok']
    && $concurrentReferral[0]['value']['referral_id']===$concurrentReferral[1]['value']['referral_id'],
    'QA_REFERRAL_CONCURRENT_IDEMPOTENCY');
$raceUuid=$concurrentReferral[0]['value']['referral_uuid'];
$concurrentAccept=$race('accept',[$raceUuid,'qa_provider_release_adapter']);
$check($concurrentAccept[0]['ok']&&$concurrentAccept[1]['ok']
    && $concurrentAccept[0]['value']['service_order_id']===$concurrentAccept[1]['value']['service_order_id'],
    'QA_DUPLICATE_ACCEPT_CONCURRENT');
$release=['provider_release_uuid'=>mxmed_uuidv4(),'service_order_uuid'=>$accepted['service_order_uuid'],
    'referral_uuid'=>$refA['referral_uuid'],'source_order_document_uuid'=>$order['uuid'],
    'patient_id'=>'p_interop','group_id'=>'org_a','location_id'=>$locations['org_a'],
    'document_type'=>'lab_result','title'=>'Provider result 1','released_at'=>'2026-10-01 18:00:00',
    'released_by_professional_id'=>'qa_professional','related_order_item_ids'=>[$ids[0]],
    'artifact'=>$artifact(),'qa_released'=>true];
$deny(fn()=>$service->publishReleasedResult([...$release,'qa_released'=>false],$authority,$storage),
    'NOT_RELEASED','QA_RELEASE_AUTHORITY_DENIAL');
$deny(fn()=>$service->publishReleasedResult([...$release,'patient_id'=>'p_foreign'],$authority,$storage),
    'PROVIDER_RELEASE_SCOPE_DENIED','QA_RELEASE_CROSS_PATIENT_DENIAL');
$deny(fn()=>$service->publishReleasedResult([...$release,'group_id'=>'org_b'],$authority,$storage),
    'PROVIDER_RELEASE_SCOPE_DENIED','QA_RELEASE_CROSS_PROVIDER_DENIAL');
$deny(fn()=>$service->publishReleasedResult([...$release,'source_order_document_uuid'=>$other['uuid']],$authority,$storage),
    'PROVIDER_RELEASE_SCOPE_DENIED','QA_RELEASE_CROSS_ORDER_DENIAL');
$deny(fn()=>$service->publishReleasedResult([...$release,'related_order_item_ids'=>[$ids[2]]],$authority,$storage),
    'PROVIDER_RESULT_ITEM_SCOPE_DENIED','QA_PROVIDER_RELEASE_COVERAGE_DENIAL');
$published=$service->publishReleasedResult($release,$authority,$storage);
$check($published['document_id']>0&&!$published['replayed'],'QA_PROVIDER_RELEASE_PUBLISH');
$again=$service->publishReleasedResult($release,$authority,$storage);
$check($again['document_id']===$published['document_id']&&$again['replayed'],'QA_PROVIDER_RELEASE_REPLAY');
$concurrentRelease=[...$release,'provider_release_uuid'=>mxmed_uuidv4(),
    'title'=>'Concurrent provider release','artifact'=>$artifact()];
$concurrentPublished=$race('publish',[$concurrentRelease]);
$check($concurrentPublished[0]['ok']&&$concurrentPublished[1]['ok']
    && $concurrentPublished[0]['value']['document_id']===$concurrentPublished[1]['value']['document_id'],
    'QA_PROVIDER_RELEASE_CONCURRENT_IDEMPOTENCY');
$deny(fn()=>$service->publishReleasedResult([...$release,'title'=>'Different title'],$authority,$storage),
    'PROVIDER_RELEASE_CONFLICT','QA_PROVIDER_RELEASE_CONFLICT');
$rows=$query('SELECT d.payload_json,d.patient_id,s.provider_release_uuid,s.referral_id,b.storage_key
    FROM clinical_documents d JOIN healthcare_provider_result_sources s ON s.clinical_result_document_id=d.id
    JOIN clinical_document_binaries b ON b.document_id=d.id WHERE d.id=?',[$published['document_id']]);
$payload=json_decode($rows[0]['payload_json'],true);
$check(count($rows)===1 && $rows[0]['patient_id']==='p_interop'
    && (int)$rows[0]['referral_id']===$refA['referral_id']
    && $payload['related_order_document_uuid']===$order['uuid']
    && $payload['related_order_item_ids']===[$ids[0]],'QA_PROVIDER_SOURCE_PROVENANCE');
$projection=clinical_or_list_fetch($pdo,'p_interop',25,'all','',null);
$item=array_values(array_filter($projection['items'],static fn(array $row):bool=>$row['kind']==='ORDER'
    && $row['order']['id']===$order['id']))[0]??null;
$check(is_array($item)&&$item['results'][0]['result_source_order_document_id']===$order['id']
    && $item['order']['order_items'][0]['coverage_state']==='RESULT_AVAILABLE'
    && $item['order']['order_items'][1]['coverage_state']==='NO_RESULT'
    && $item['order']['order_items'][2]['coverage_state']==='NO_RESULT','QA_PROVIDER_RESULT_PHYSICIAN_READ_MODEL');
$refB=$service->sendReferral('d_interop','p_interop',$order['uuid'],1,'org_b',$locations['org_b'],
    [$ids[2]],'u_interop','interop-key-b');
$acceptedB=$service->acceptReferral($refB['referral_uuid'],'qa_provider_release_adapter');
$check($refB['referral_id']!==$refA['referral_id']&&$acceptedB['service_order_id']!==$accepted['service_order_id']
    && array_column($query('SELECT source_order_item_id FROM healthcare_provider_service_order_items WHERE service_order_id=?',
        [$acceptedB['service_order_id']]),'source_order_item_id')===[$ids[2]],'QA_MULTI_PROVIDER_ORDER');
$deny(fn()=>$service->publishReleasedResult([...$release,'provider_release_uuid'=>mxmed_uuidv4(),
    'service_order_uuid'=>$acceptedB['service_order_uuid'],'referral_uuid'=>$refB['referral_uuid'],
    'group_id'=>'org_b','location_id'=>$locations['org_b']],$authority,$storage),
    'PROVIDER_RESULT_ITEM_SCOPE_DENIED','QA_CROSS_PROVIDER_ITEM_DENIAL');
$v2=$createOrder('p_interop',[1,2,3],'Interop V2');
$pdo->prepare('INSERT INTO clinical_document_revisions
    (original_document_id,supersedes_document_id,new_document_id,reason,author_user_id,created_at)
    VALUES (?,?,?,\'QA successor\',\'u_interop\',UTC_TIMESTAMP())')->execute([$order['id'],$order['id'],$v2['id']]);
$v2Ids=array_column($v2['payload']['order_items'],'order_item_id');
$check($v2Ids[0]!==$ids[0]&&$service->referralRead($refA['referral_uuid'])['source_order_document_id']==$order['id'],
    'QA_ORDER_AMENDMENT_AFTER_REFERRAL');
$deny(fn()=>$service->sendReferral('d_interop','p_interop',$order['uuid'],1,'org_a',$locations['org_a'],
    [$ids[0]],'u_interop','interop-key-post-successor'),'REFERRAL_ORDER_REPLACED',
    'QA_NEW_REFERRAL_REPLACED_VERSION_DENIED');
$late=[...$release,'provider_release_uuid'=>mxmed_uuidv4(),'title'=>'Late V1 provider result',
    'released_at'=>'2026-10-01 19:00:00','related_order_item_ids'=>[$ids[1]],'artifact'=>$artifact()];
$latePublished=$service->publishReleasedResult($late,$authority,$storage);
$projection=clinical_or_list_fetch($pdo,'p_interop',25,'all','',null);
$head=array_values(array_filter($projection['items'],static fn(array $row):bool=>$row['kind']==='ORDER'
    && $row['order']['id']===$v2['id']))[0]??null;
$children=array_column($head['results'],null,'id');$versions=array_column($head['order']['versions'],null,'id');
$check(isset($children[$latePublished['document_id']])
    && $children[$latePublished['document_id']]['result_source_order_document_id']===$order['id']
    && $children[$latePublished['document_id']]['related_order_item_ids']===[$ids[1]],
    'QA_REL01_EXACT_SOURCE_PRESERVED_DURING_INTEROP');
$check($children[$latePublished['document_id']]['order_lineage_head_document_id']===$v2['id']
    && $children[$latePublished['document_id']]['related_order_document_id']===$v2['id']
    && $children[$latePublished['document_id']]['result_order_relationship']==='PREDECESSOR_VERSION',
    'QA_REL01_LINEAGE_HEAD_SEPARATE_DURING_INTEROP');
$check($versions[$order['id']]['order_items'][0]['coverage_state']==='RESULT_AVAILABLE'
    && $versions[$order['id']]['order_items'][1]['coverage_state']==='RESULT_AVAILABLE'
    && $head['order']['coverage_state']==='NO_RESULTS'
    && $head['order']['order_items'][0]['coverage_state']==='NO_RESULT',
    'QA_REL01_NO_SUCCESSOR_ITEM_INFERENCE');
$check(count($query('SELECT event_id FROM healthcare_study_referral_events WHERE referral_id=?',[$refA['referral_id']]))===2,
    'QA_REFERRAL_APPEND_ONLY_EVENTS');
$closeOrder=$createOrder('p_interop',[1],'Closing order');
$closeItem=$closeOrder['payload']['order_items'][0]['order_item_id'];
$declined=$service->sendReferral('d_interop','p_interop',$closeOrder['uuid'],1,'org_a',$locations['org_a'],
    [$closeItem],'u_interop','interop-key-decline');
$service->closeReferral($declined['referral_uuid'],'DECLINED','PROVIDER_SYSTEM','qa_provider','Cannot perform');
$deny(fn()=>$service->acceptReferral($declined['referral_uuid'],'qa_provider'),
    'REFERRAL_NOT_ACCEPTABLE','QA_DECLINED_TERMINAL');
$canceled=$service->sendReferral('d_interop','p_interop',$closeOrder['uuid'],1,'org_a',$locations['org_a'],
    [$closeItem],'u_interop','interop-key-cancel');
$service->closeReferral($canceled['referral_uuid'],'CANCELED','PHYSICIAN','u_interop','Patient choice');
$deny(fn()=>$service->acceptReferral($canceled['referral_uuid'],'qa_provider'),
    'REFERRAL_NOT_ACCEPTABLE','QA_CANCELED_TERMINAL');
$changed=$service->sendReferral('d_interop','p_interop',$closeOrder['uuid'],1,'org_a',$locations['org_a'],
    [$closeItem],'u_interop','interop-key-target-changed');
$execute("UPDATE healthcare_organization_location_study_offerings SET operational_state='INACTIVE'
    WHERE location_id=? AND study_type_id=1",[$locations['org_a']]);
$deny(fn()=>$service->acceptReferral($changed['referral_uuid'],'qa_provider'),
    'REFERRAL_STUDY_NOT_OFFERED','QA_ACCEPT_TARGET_REVALIDATED');
$immutable=static function(callable $action,string $name)use($check):void{
    try{$action();}catch(PDOException $e){$check($e->getCode()==='45000',$name);return;}
    $check(false,$name);
};
$immutable(fn()=>$execute('UPDATE healthcare_study_referrals SET source_order_version=2 WHERE referral_id=?',
    [$refA['referral_id']]),'QA_REFERRAL_SOURCE_IMMUTABLE');
$immutable(fn()=>$execute('UPDATE healthcare_study_referral_events SET actor_id=\'other\' WHERE referral_id=?',
    [$refA['referral_id']]),'QA_REFERRAL_EVENTS_APPEND_ONLY');
$immutable(fn()=>$execute('UPDATE healthcare_provider_result_sources SET patient_id=\'p_foreign\'
    WHERE clinical_result_document_id=?',[$published['document_id']]),'QA_PROVIDER_SOURCE_IMMUTABLE');
echo "DISPOSABLE_END_TO_END_INTEROP=PASS\n";
