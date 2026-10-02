<?php
declare(strict_types=1);
require_once __DIR__ . '/../../../api/_lib/clinical_order_result_read.php';

$db = getenv('OR02B_QA_DB');
if (!is_string($db) || !preg_match('/^or02b_qa_[a-f0-9]{12}$/', $db)) throw new RuntimeException('Disposable DB required');
$pdo = new PDO('mysql:host=localhost;dbname=' . $db, 'root', '', [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$pdo->exec("CREATE TABLE patients_patients(patient_id VARCHAR(64) PRIMARY KEY) ENGINE=InnoDB");
$pdo->exec("CREATE TABLE patients_doctor_links(doctor_id VARCHAR(64),patient_id VARCHAR(64),status VARCHAR(32)) ENGINE=InnoDB");
$pdo->exec("INSERT INTO patients_patients VALUES('p_or02b')");
$pdo->exec("INSERT INTO patients_doctor_links VALUES('d_or02b','p_or02b','active')");
$pdo->exec("CREATE TABLE clinical_documents (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, document_uuid CHAR(36) NOT NULL UNIQUE,
 document_type VARCHAR(64) NOT NULL,title VARCHAR(128) NOT NULL,summary VARCHAR(512),version INT NOT NULL DEFAULT 1,
 status VARCHAR(20) NOT NULL DEFAULT 'generated',printable TINYINT NOT NULL DEFAULT 1,patient_id VARCHAR(128) NOT NULL,
 encounter_id VARCHAR(128),encounter_ref_id BIGINT UNSIGNED,appointment_id VARCHAR(64),hospital_stay_id VARCHAR(128),
 payload_json JSON NOT NULL,event_datetime DATETIME NOT NULL,created_at DATETIME NOT NULL,generated_at DATETIME
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
$pdo->exec("CREATE TABLE clinical_document_revisions (
 revision_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,original_document_id BIGINT UNSIGNED NOT NULL,
 supersedes_document_id BIGINT UNSIGNED,new_document_id BIGINT UNSIGNED NOT NULL UNIQUE
) ENGINE=InnoDB");
$pdo->exec("CREATE TABLE clinical_document_binaries (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,document_id BIGINT UNSIGNED NOT NULL,
 variant_role VARCHAR(20) NOT NULL,variant_version INT NOT NULL
) ENGINE=InnoDB");
$insert = $pdo->prepare("INSERT INTO clinical_documents(document_uuid,document_type,title,summary,patient_id,encounter_id,encounter_ref_id,appointment_id,payload_json,event_datetime,created_at,generated_at,version)
 VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?)");
$add = static function (string $type, string $title, array $payload=[], ?string $created=null, ?string $generated=null,
    ?string $encounter=null, ?int $encounterRef=null, ?string $appointment=null, int $version=1) use ($insert,$pdo): int {
    $created ??= '2026-09-30 12:00:00';
    $insert->execute([sprintf('00000000-0000-4000-8000-%012d',random_int(1,999999999999)),$type,$title,null,'p_or02b',
        $encounter,$encounterRef,$appointment,json_encode($payload,JSON_THROW_ON_ERROR),'2026-09-01 00:00:00',$created,$generated,$version]);
    return (int)$pdo->lastInsertId();
};
$check = static function (bool $ok, string $name): void { if (!$ok) throw new RuntimeException($name); echo "{$name}=PASS\n"; };
$zero=$add('lab_order','Zero results',[],null,null,'e42',42,'apt42');
$one=$add('imaging_order','One result');
$three=$add('orden_estudio','Three results',['requested_studies'=>['Special study needle']]);
$add('lab_result','Result one',['related_order_document_id'=>$one]);
for ($i=1;$i<=3;$i++) $add('lab_result','Result three '.$i,['related_order_document_id'=>$three]);
$standalone=$add('result','Standalone generic',['result_origin'=>'sin_orden']);
$old=$add('order','Old order');
$new=$add('order','Replacement order',[],null,null,null,null,null,2);
$rev=$pdo->prepare('INSERT INTO clinical_document_revisions(original_document_id,supersedes_document_id,new_document_id) VALUES(?,?,?)');
$rev->execute([$old,$old,$new]);
$oldResult=$add('lab_result','Old result',['related_order_document_uuid'=>$pdo->query("SELECT document_uuid FROM clinical_documents WHERE id={$old}")->fetchColumn()]);
$newResult=$add('lab_result','Replacement result',['related_order_document_uuid'=>$pdo->query("SELECT document_uuid FROM clinical_documents WHERE id={$old}")->fetchColumn()],null,null,null,null,null,2);
$rev->execute([$oldResult,$oldResult,$newResult]);
for ($i=0;$i<215;$i++) $add('lab_order', $i===214 ? 'Beyond page special needle' : 'Bulk order '.$i, [], '2026-09-29 12:00:00');

function allPages(PDO $pdo, string $filter='all', string $search='', int $size=25): array {
    $items=[];$cursor=null;$seen=[];
    do {
        $page=clinical_or_list_fetch($pdo,'p_or02b',$size,$filter,$search,$cursor);
        foreach ($page['items'] as $item) {
            $id=$item['order']['id']??$item['result']['id'];
            if (isset($seen[$id])) throw new RuntimeException('Duplicate page ID '.$id);
            $seen[$id]=true;$items[]=$item;
        }
        $cursor=$page['cursor_next']===null?null:clinical_or_cursor_decode($page['cursor_next']);
    } while ($page['has_more']);
    return $items;
}
$all=allPages($pdo);
$byOrder=[];$stand=[];
foreach ($all as $item) {
    if ($item['kind']==='ORDER') $byOrder[$item['order']['id']]=$item;
    else $stand[$item['result']['id']]=$item;
}
$check($byOrder[$zero]['result_count']===0,'QA_ZERO_RESULTS');
$check($byOrder[$one]['result_count']===1,'QA_ONE_RESULT');
$check($byOrder[$three]['result_count']===3 && count($byOrder[$three]['results'])===3,'QA_MULTIPLE_RESULTS');
$check(isset($stand[$standalone]),'QA_STANDALONE_RESULT');
$check($stand[$standalone]['result']['document_type']==='result','QA_GENERIC_RESULT_TYPE');
$check(!isset($byOrder[$old]) && isset($byOrder[$new]) && $byOrder[$new]['result_count']===1 && $byOrder[$new]['results'][0]['id']===$newResult,'QA_VERSION_COLLAPSE');
$check(count($all)===220 && count($byOrder)===219,'QA_OVER_200');
$matches=allPages($pdo,'all','Beyond page special needle');
$check(count($matches)===1 && $matches[0]['kind']==='ORDER','QA_SEARCH_BEYOND_FIRST_PAGE');
$orders=allPages($pdo,'orders');
$check(count($orders)===219,'QA_FILTER_ORDERS');
$results=allPages($pdo,'results');
$check(count($results)===6,'QA_FILTER_RESULTS');
$ids=array_map(static fn(array $item): int => $item['order']['id']??$item['result']['id'],$all);
$expected=$all;
usort($expected, static function (array $a,array $b): int {
    $ad=$a['order']??$a['result'];$bd=$b['order']??$b['result'];
    return strcmp($bd['chronology_at'],$ad['chronology_at']) ?: ($bd['id']<=>$ad['id']);
});
$expectedIds=array_map(static fn(array $item): int => $item['order']['id']??$item['result']['id'],$expected);
$check(count($ids)===count(array_unique($ids)) && $ids===$expectedIds,'QA_EQUAL_TIMESTAMPS');
$check($byOrder[$zero]['order']['source_scope']==='ENCOUNTER' && $byOrder[$zero]['order']['encounter_ref_id']===42 && $byOrder[$zero]['order']['appointment_id']==='apt42','QA_SCOPE_METADATA');
$check(count(allPages($pdo,'orders','Special study needle'))===1,'QA_STUDY_SEARCH');
$childSearch=allPages($pdo,'all','Result three 3');
$check(count($childSearch)===1 && $childSearch[0]['order']['id']===$three,'QA_LINKED_RESULT_SEARCH');
$linkedOnly=allPages($pdo,'results','Result three 3');
$check(count($linkedOnly)===1 && $linkedOnly[0]['result']['related_order_document_id']===$three,'QA_RESULT_FILTER_RELATION');
$ambiguous=$add('lab_result','Conflicting relationship',['related_order_document_id'=>$zero,'related_document_id'=>$one]);
$unresolved=allPages($pdo,'all','Conflicting relationship');
$check(count($unresolved)===1 && $unresolved[0]['kind']==='UNRESOLVED_RESULT' &&
    $unresolved[0]['result']['id']===$ambiguous,'QA_AMBIGUOUS_RELATION_NOT_FABRICATED');

// REL01: a successor is the display head, never the immutable source of predecessor results.
$v1Items=[];$v2Items=[];
for ($i=1;$i<=3;$i++) {
    $v1Items[]=['order_item_id'=>sprintf('00000000-0000-4000-8000-%012d',100+$i),'study_type_id'=>$i,'study_display_name'=>'Study '.$i];
    $v2Items[]=['order_item_id'=>sprintf('00000000-0000-4000-8000-%012d',200+$i),'study_type_id'=>$i,'study_display_name'=>'Study '.$i];
}
$v1=$add('lab_order','REL01 V1',['order_payload_version'=>2,'order_items'=>$v1Items],null,null,null,null,null,1);
$v1Uuid=$pdo->query("SELECT document_uuid FROM clinical_documents WHERE id={$v1}")->fetchColumn();
$r1=$add('lab_result','REL01 R1',['related_order_document_uuid'=>$v1Uuid,
    'related_order_item_ids'=>[$v1Items[0]['order_item_id']]]);
$r2=$add('lab_result','REL01 R2',['related_order_document_id'=>(string)$v1,
    'related_order_item_ids'=>[$v1Items[1]['order_item_id'],$v1Items[2]['order_item_id']]]);
$v2=$add('lab_order','REL01 V2',['order_payload_version'=>2,'order_items'=>$v2Items],null,null,null,null,null,2);
$v2Uuid=$pdo->query("SELECT document_uuid FROM clinical_documents WHERE id={$v2}")->fetchColumn();
$rev->execute([$v1,$v1,$v2]);
$after=$add('lab_result','REL01 R after successor',['related_order_document_uuid'=>$v1Uuid,
    'related_order_item_ids'=>[$v1Items[0]['order_item_id']]]);
$direct=$add('lab_result','REL01 V2 direct',['related_order_document_uuid'=>$v2Uuid,
    'related_order_item_ids'=>[$v2Items[0]['order_item_id']]]);
$relation=allPages($pdo,'all','REL01 V2');
$check(count($relation)===1&&$relation[0]['order']['id']===$v2,'QA_REL01_LINEAGE_HEAD');
$head=$relation[0]['order'];$children=array_column($relation[0]['results'],null,'id');
$check(count($children)===4&&isset($children[$r1],$children[$r2],$children[$after],$children[$direct]),'QA_REL01_HISTORICAL_VISIBLE');
foreach ([$r1,$r2,$after] as $resultId) {
    $child=$children[$resultId];
    $check($child['result_source_order_document_id']===$v1
        && $child['result_source_order_document_uuid']===$v1Uuid
        && $child['result_source_order_version']===1
        && $child['order_lineage_head_document_id']===$v2
        && $child['order_lineage_head_document_uuid']===$v2Uuid
        && $child['result_order_relationship']==='PREDECESSOR_VERSION'
        && $child['related_order_document_id']===$v2,'QA_REL01_SOURCE_'.$resultId);
}
$check($children[$r1]['related_order_item_ids']===[$v1Items[0]['order_item_id']]
    && $children[$r2]['related_order_item_ids']===[$v1Items[1]['order_item_id'],$v1Items[2]['order_item_id']],
    'QA_REL01_EXACT_SOURCE_ITEMS');
$check($children[$direct]['result_source_order_document_id']===$v2
    && $children[$direct]['result_order_relationship']==='DIRECT_CURRENT_VERSION','QA_REL01_DIRECT_V2');
$versions=array_column($head['versions'],null,'id');
$check($versions[$v1]['order_items'][0]['coverage_state']==='RESULT_AVAILABLE'
    && $versions[$v1]['order_items'][1]['coverage_state']==='RESULT_AVAILABLE'
    && $versions[$v1]['order_items'][2]['coverage_state']==='RESULT_AVAILABLE','QA_REL01_EXACT_V1_COVERAGE');
$check($head['order_items'][0]['coverage_state']==='RESULT_AVAILABLE'
    && $head['order_items'][1]['coverage_state']==='NO_RESULT'
    && $head['order_items'][2]['coverage_state']==='NO_RESULT'
    && $head['coverage_state']==='PARTIAL_RESULTS','QA_REL01_V2_INDEPENDENT_COVERAGE');
$resultFilter=allPages($pdo,'results','REL01 R1');
$check(count($resultFilter)===1&&$resultFilter[0]['result']['result_source_order_document_id']===$v1
    && $resultFilter[0]['result']['related_order_document_id']===$v2,'QA_REL01_RESULT_FILTER_EXACT_SOURCE');
$partialV1Items=[
    ['order_item_id'=>'00000000-0000-4000-8000-000000000301','study_type_id'=>1,'study_display_name'=>'Same study'],
    ['order_item_id'=>'00000000-0000-4000-8000-000000000302','study_type_id'=>2,'study_display_name'=>'Second study'],
];
$partialV2Items=[
    ['order_item_id'=>'00000000-0000-4000-8000-000000000401','study_type_id'=>1,'study_display_name'=>'Same study'],
    ['order_item_id'=>'00000000-0000-4000-8000-000000000402','study_type_id'=>2,'study_display_name'=>'Second study'],
];
$partialV1=$add('lab_order','REL01 partial V1',['order_payload_version'=>2,'order_items'=>$partialV1Items]);
$partialUuid=$pdo->query("SELECT document_uuid FROM clinical_documents WHERE id={$partialV1}")->fetchColumn();
$partialResult=$add('lab_result','REL01 partial result',['related_order_document_uuid'=>$partialUuid,
    'related_order_item_ids'=>[$partialV1Items[0]['order_item_id']]]);
$partialV2=$add('lab_order','REL01 partial V2',['order_payload_version'=>2,'order_items'=>$partialV2Items],null,null,null,null,null,2);
$rev->execute([$partialV1,$partialV1,$partialV2]);
$partialProjection=allPages($pdo,'all','REL01 partial V2')[0];
$partialVersions=array_column($partialProjection['order']['versions'],null,'id');
$check($partialVersions[$partialV1]['order_items'][0]['coverage_state']==='RESULT_AVAILABLE'
    && $partialVersions[$partialV1]['order_items'][1]['coverage_state']==='NO_RESULT'
    && $partialVersions[$partialV1]['coverage_state']==='PARTIAL_RESULTS','QA_REL01_V1_PARTIAL_EXACT_COVERAGE');
$check($partialProjection['order']['coverage_state']==='NO_RESULTS'
    && $partialProjection['order']['order_items'][0]['coverage_state']==='NO_RESULT'
    && $partialProjection['order']['order_items'][1]['coverage_state']==='NO_RESULT'
    && $partialProjection['results'][0]['result_source_order_document_id']===$partialV1
    && $partialProjection['results'][0]['id']===$partialResult,'QA_REL01_NO_SUCCESSOR_REMAP_OR_UNKNOWN');
$partialV3Items=[
    ['order_item_id'=>'00000000-0000-4000-8000-000000000501','study_type_id'=>1,'study_display_name'=>'Same study'],
    ['order_item_id'=>'00000000-0000-4000-8000-000000000502','study_type_id'=>2,'study_display_name'=>'Second study'],
];
$partialV3=$add('lab_order','REL01 partial V3',['order_payload_version'=>2,'order_items'=>$partialV3Items],null,null,null,null,null,3);
$rev->execute([$partialV1,$partialV2,$partialV3]);
$threeVersion=allPages($pdo,'all','REL01 partial V3')[0];
$check($threeVersion['order']['id']===$partialV3
    && $threeVersion['results'][0]['result_source_order_document_id']===$partialV1
    && $threeVersion['results'][0]['order_lineage_head_document_id']===$partialV3
    && $threeVersion['results'][0]['result_order_relationship']==='PREDECESSOR_VERSION'
    && $threeVersion['order']['coverage_state']==='NO_RESULTS','QA_REL01_V1_V2_V3_EXACT_SOURCE');
