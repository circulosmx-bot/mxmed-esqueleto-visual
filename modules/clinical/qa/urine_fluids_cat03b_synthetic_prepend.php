<?php
declare(strict_types=1);
// Used only by the second disposable QA server, never the review runtime.
$database=getenv('MXMED_DB_NAME');
if (!is_string($database) || preg_match('/^ordcomp01_qa_[a-f0-9]{10}$/D',$database)!==1) throw new RuntimeException('Disposable DB required');
require_once __DIR__.'/../../../api/_lib/clinical_specimen_requirements.php';
$config=clinical_specimen_authority();
$config['studies']['urine_osmolality']=[
    'specimen_mode'=>'FIXED','fixed_specimen_type_key'=>'URINE',
    'collection_mode'=>'REQUIRED_SELECTION','allowed_collection_modes'=>['SPOT','TIMED'],
    'allowed_duration_minutes'=>[1440],
];
clinical_specimen_authority($config);
