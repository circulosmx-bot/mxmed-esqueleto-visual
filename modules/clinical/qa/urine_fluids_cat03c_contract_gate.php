<?php
declare(strict_types=1);
require_once __DIR__.'/../../../api/_lib/clinical_study_contract.php';

$database=getenv('LAB_CAT02A_QA_DB');
if (!is_string($database) || preg_match('/^ordcomp01_qa_[a-f0-9]{10}$/D',$database)!==1) throw new RuntimeException('Disposable DB required');
$pdo=new PDO('mysql:host=localhost;dbname='.$database,'root','',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$matrix=fopen(__DIR__.'/../../../docs/clinical/URINE_FLUIDS_CAT03C_ADVANCED_ORDERABLES_REAUDIT_MATRIX.csv','r');
if (!$matrix) throw new RuntimeException('Matrix unavailable');
$headers=fgetcsv($matrix,0,',','"','');$rows=0;$approved=[];
while (($values=fgetcsv($matrix,0,',','"',''))!==false) {
    ++$rows;$row=array_combine($headers,$values);
    if ($row['implementation_recommendation']!=='MINIMUM_CAT03C') continue;
    $key=$row['proposed_key'];
    if (isset($approved[$key])) {
        foreach (['display_name','specimen_requirement','routing_group','primary_family'] as $field)
            if ($approved[$key][$field]!==$row[$field]) throw new RuntimeException('Matrix identity disagreement: '.$key);
    } else $approved[$key]=$row;
}
fclose($matrix);
if ($rows!==182 || count($approved)!==17) throw new RuntimeException('Matrix count mismatch');
$actual=$pdo->query("SELECT study_type_key,display_name_es,category_key,aliases_json FROM clinical_study_types WHERE is_active=1 AND seed_provenance='URINE-FLUIDS-CAT03C-IMPL:2026_10_03_21'")->fetchAll(PDO::FETCH_ASSOC);
if (count($actual)!==17) throw new RuntimeException('New study count mismatch');
$specimens=clinical_specimen_authority();
$routing=json_decode(file_get_contents(__DIR__.'/../catalog/study_order_routing_v1.json'),true,512,JSON_THROW_ON_ERROR);
foreach ($actual as $study) {
    $key=$study['study_type_key'];$row=$approved[$key]??null;
    if (!$row || $study['display_name_es']!==$row['display_name'] || json_decode($study['aliases_json'],true)!==[] || ($routing['studies'][$key]??null)!==$row['routing_group']) throw new RuntimeException('Catalog authority mismatch: '.$key);
    $category=$row['primary_family']==='PATOLOGIA_CITOLOGIA'?'PATOLOGIA':'LABORATORIO';
    if ($study['category_key']!==$category) throw new RuntimeException('Family mismatch: '.$key);
    $rule=$specimens['studies'][$key]??null;$source=$row['specimen_requirement'];
    if (!$rule) throw new RuntimeException('Missing specimen rule: '.$key);
    if (str_starts_with($source,'SELECT_ONE:')) {
        $allowed=explode(',',substr($source,11));
        if ($rule['specimen_mode']!=='REQUIRED_SELECTION' || $rule['allowed_specimen_type_keys']!==$allowed) throw new RuntimeException('Allowlist mismatch: '.$key);
        try {clinical_specimen_validate($key,null);throw new RuntimeException('Missing selection accepted: '.$key);} catch (InvalidArgumentException $error) {if ($error->getMessage()!=='SPECIMEN_TYPE_REQUIRED') throw $error;}
        foreach ($specimens['specimen_types'] as $candidate=>$label) {
            $payload=['version'=>1,'specimen_type_key'=>$candidate];
            if (in_array($candidate,$allowed,true)) {
                $normalized=clinical_specimen_validate($key,$payload);
                if (($normalized['specimen_type_key']??null)!==$candidate || !str_contains((string)clinical_specimen_print_context($normalized,$study['display_name_es']),$label)) throw new RuntimeException('Allowed specimen failed: '.$key.'/'.$candidate);
            } else {
                try {clinical_specimen_validate($key,$payload);throw new RuntimeException('Forbidden specimen accepted: '.$key.'/'.$candidate);} catch (InvalidArgumentException $error) {if ($error->getMessage()!=='SPECIMEN_TYPE_INVALID') throw $error;}
            }
        }
    } else {
        $fixed=explode(';',substr($source,6))[0];
        $normalized=clinical_specimen_validate($key,null);
        if ($rule['specimen_mode']!=='FIXED' || $normalized['specimen_type_key']!==$fixed) throw new RuntimeException('Fixed specimen mismatch: '.$key);
        if ($key==='csf_oligoclonal_bands' && (($normalized['paired_specimen']['counterpart_specimen_type_key']??null)!=='SERUM' || ($normalized['paired_specimen']['relationship_key']??null)!=='SAME_COLLECTION_EPISODE')) throw new RuntimeException('Pair mismatch');
    }
}
echo "QA_CAT03C_MATRIX_182_AND_IDENTITIES_17=PASS\n";
echo "QA_CAT03C_NAMES_FAMILIES_ROUTING_ALIASES=PASS\n";
echo "QA_CAT03C_EVERY_CONFIGURABLE_ALLOWED_AND_DISALLOWED=PASS\n";
echo "QA_CAT03C_FIXED_CSF_AND_PAIRED_SERUM=PASS\n";
