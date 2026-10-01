<?php
declare(strict_types=1);
require_once __DIR__.'/../../../api/_lib/clinical_study_contract.php';
require_once __DIR__.'/../../../api/_lib/clinical_study_catalog_read.php';
$db=getenv('TAX03B_QA_DB');
if(!is_string($db)||!preg_match('/^tax03b_qa_[a-f0-9]{12}$/D',$db))throw new RuntimeException('Disposable DB required');
$pdo=new PDO('mysql:host=localhost;dbname='.$db,'root','',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$manifest=json_decode(file_get_contents(__DIR__.'/../catalog/tax03b_source_curation.json'),true,512,JSON_THROW_ON_ERROR);
$curated=array_values(array_filter($manifest['entries'],fn($e)=>$e['classification']==='CANONICAL_CLEAR'));
$count=(int)$pdo->query('SELECT COUNT(*) FROM clinical_study_types')->fetchColumn();
if($count!==count($curated))throw new RuntimeException('QA_SEED_COUNT');
$unique=(int)$pdo->query('SELECT COUNT(DISTINCT study_type_key) FROM clinical_study_types')->fetchColumn();
if($count!==$unique)throw new RuntimeException('QA_KEY_UNIQUE');
$rows=$pdo->query('SELECT study_type_key,display_name_es,category_key,aliases_json,seed_provenance FROM clinical_study_types')->fetchAll(PDO::FETCH_ASSOC);
$keys=[];$names=[];$aliases=[];
foreach($rows as $row){
 $keys[$row['study_type_key']]=$row;
 $name=mb_strtolower(trim($row['display_name_es']));
 if(isset($names[$name]))throw new RuntimeException('QA_DUPLICATE_CANONICAL_NAME');
 $names[$name]=true;
 if(!in_array($row['category_key'],clinical_study_categories(),true))throw new RuntimeException('QA_CATEGORY');
 if($row['seed_provenance']!=='TAX03B:2026_09_30_15_initial_curated_study_catalog.sql')throw new RuntimeException('QA_PROVENANCE');
 $aliasList=json_decode($row['aliases_json'],true,512,JSON_THROW_ON_ERROR);
 if(!is_array($aliasList)||!array_is_list($aliasList))throw new RuntimeException('QA_ALIAS_JSON');
 foreach($aliasList as $alias){
  $norm=mb_strtolower(trim($alias));
  if(isset($aliases[$norm])&&$aliases[$norm]!==$row['study_type_key'])throw new RuntimeException('QA_ALIAS_CONFLICT');
  $aliases[$norm]=$row['study_type_key'];
 }
}
foreach($curated as $entry){
 $row=$keys[$entry['study_type_key']]??null;
 if($row===null||$row['display_name_es']!==$entry['source_name']||$row['category_key']!==$entry['category_key'])throw new RuntimeException('QA_MANIFEST_MATCH');
 if(json_decode($row['aliases_json'],true,512,JSON_THROW_ON_ERROR)!==$entry['aliases'])throw new RuntimeException('QA_MANIFEST_ALIASES');
}
if((int)$pdo->query('SELECT COUNT(*) FROM clinical_study_type_external_codes')->fetchColumn()!==0)throw new RuntimeException('QA_EXTERNAL_CODES');
if(count(clinical_study_categories())!==13||!in_array('GENETICA',clinical_study_categories(),true))throw new RuntimeException('QA_GENETICA');
if(clinical_study_category_labels_es()['GENETICA']!=='Genética')throw new RuntimeException('QA_GENETICA_LABEL');
$pdo->exec("UPDATE clinical_study_types SET is_active=0 WHERE study_type_key='cma_microarray'");
$hidden=clinical_study_catalog_read($pdo,['category'=>'GENETICA','search'=>'Microarray']);
if($hidden['items']!==[])throw new RuntimeException('QA_INACTIVE_FILTER');
$pdo->exec("UPDATE clinical_study_types SET is_active=1 WHERE study_type_key='cma_microarray'");
$found=clinical_study_catalog_read($pdo,['category'=>'GENETICA','search'=>'Microarray']);
if(count($found['items'])!==1||$found['items'][0]['study_type_key']!=='cma_microarray')throw new RuntimeException('QA_ALIAS_CATEGORY_SEARCH');
if($found['items'][0]['category_label_es']!=='Genética')throw new RuntimeException('QA_GENETICA_LABEL_PROJECTION');
echo "QA_CATALOG_SEED_IDEMPOTENT=PASS\nQA_KEY_CATEGORY_ALIAS_PROVENANCE=PASS\nQA_INACTIVE_FILTER=PASS\nQA_EXTERNAL_CODES_ZERO=PASS\n";
