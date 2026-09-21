<?php
declare(strict_types=1);
require_once dirname(__DIR__,3).'/api/_lib/clinical_m6_observability.php';
$count=(int)($argv[1]??0);
clinical_m6_observability_route('CONCURRENCY_TEST','EMIT','READ_ONLY');
for($i=0;$i<$count;$i++){
    if(!clinical_m6_observability_emit('concurrency_event',['outcome'=>'success','sequence'=>$i])) exit(2);
}
