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
