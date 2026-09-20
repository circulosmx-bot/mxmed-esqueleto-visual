<?php
declare(strict_types=1);
require_once dirname(__DIR__, 3) . '/api/_lib/clinical_multipart_document_service.php';
$count = 0;
function m3b_check(bool $ok, string $name): void {
    global $count;
    if (!$ok) throw new RuntimeException($name);
    $count++;
}
$context = ['operation'=>'CREATE_ENCOUNTER_DOCUMENT','doctor_id'=>'doctor-qa','patient_id'=>'patient-qa',
    'context_type'=>'ENCOUNTER','context_id'=>'42','document_type'=>'result','metadata'=>['title'=>'Synthetic','tags'=>['a','b']]];
$binary = ['sha256'=>str_repeat('a',64),'byte_length'=>64,'mime_type'=>'application/pdf'];
$hash = static fn(array $c, array $b): string => clinical_idempotency_request_hash(clinical_multipart_semantic_request($c,$b));
$base = $hash($context,$binary);
foreach (['sha256'=>str_repeat('b',64),'byte_length'=>65,'mime_type'=>'image/png'] as $field=>$value) {
    m3b_check($hash($context,array_replace($binary,[$field=>$value])) !== $base, "$field must affect identity");
}
foreach (['doctor_id'=>'other-doctor','patient_id'=>'other-patient','context_id'=>'43','document_type'=>'order',
    'operation'=>'CREATE_POST_ENCOUNTER_RESULT','metadata'=>['title'=>'Changed']] as $field=>$value) {
    m3b_check($hash(array_replace($context,[$field=>$value]),$binary) !== $base, "$field must affect identity");
}
foreach (['upload_id','document_uuid','binary_uuid','staging_key','final_key','storage_key'] as $field) {
    m3b_check($hash($context+[$field=>'attempt-A'],$binary+[$field=>'attempt-A']) ===
        $hash($context+[$field=>'attempt-B'],$binary+[$field=>'attempt-B']), "$field cannot affect identity");
}
m3b_check($hash(array_replace($context,['metadata'=>['tags'=>['a','b'],'title'=>'Synthetic']]),$binary)===$base,'canonical metadata order');
foreach (['CREATE_ENCOUNTER_DOCUMENT','CREATE_POST_ENCOUNTER_RESULT','CREATE_DOCUMENT_AMENDMENT_OR_REPLACEMENT'] as $operation) {
    m3b_check(clinical_multipart_semantic_request(array_replace($context,['operation'=>$operation]),$binary)['operation']===$operation,'supported operation');
}
foreach ([['patient_id'=>''],['operation'=>'CREATE_OBSERVATION'],['context_type'=>'NEW_CONTEXT'],
    ['context_type'=>'PATIENT'],['metadata'=>['document_uuid'=>'attempt']]] as $invalid) {
    try { $hash(array_replace($context,$invalid),$binary); throw new RuntimeException('invalid accepted'); }
    catch (InvalidArgumentException) { $count++; }
}
$patient = array_replace($context,['operation'=>'CREATE_DOCUMENT_AMENDMENT_OR_REPLACEMENT','context_type'=>'PATIENT','context_id'=>'patient-qa']);
m3b_check(clinical_multipart_semantic_request($patient,$binary)['context_type']==='PATIENT','patient replacement context');
m3b_check($hash($patient,$binary)!==$base,'context type affects identity');
echo "MULTI03B_SEMANTIC_QA=PASS\nASSERTIONS=$count\nANY_DATABASE_CONNECTED=false\n";
