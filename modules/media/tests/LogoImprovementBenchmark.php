<?php
declare(strict_types=1);
require __DIR__.'/LogoImprovementFixture.php';
// Run `candidate white 800 800` in a separate process before measuring this worker.
$id=$argv[1]??'';$p=mr5Pdo();[$storage]=mr5Storage();$review=mr6Rows($p,$id)['IMPROVEMENT_INPUT'];
if((int)$review['width']!==800||(int)$review['height']!==800||(int)$review['byte_size']>4194304)throw new RuntimeException('maximum_envelope_lossless_input_required');
$start=hrtime(true);$result=(new Media\Services\LogoImprovementService($p,$storage))->mutate(mr8Context('generate'),'generate',$id);
if($result['status']!=='PROPOSAL_CREATED')throw new RuntimeException('benchmark_proposal_required');
echo json_encode(['input_width'=>800,'input_height'=>800,'input_bytes'=>(int)$review['byte_size'],'duration_ms'=>round((hrtime(true)-$start)/1e6,2),'status'=>$result['status']]).PHP_EOL;
