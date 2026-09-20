<?php
declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/api/_lib/clinical_m6_write_window.php';

if (($argv[1] ?? '') === 'worker') {
    putenv('MXMED_CLINICAL_WRITE_WINDOW_CONTROL=FILE');
    putenv('MXMED_CLINICAL_WRITE_WINDOW_STATE_PATH=' . $argv[2]);
    clinical_m6_write_window_admit();
    file_put_contents($argv[3], 'admitted');
    while (!is_file($argv[4])) usleep(10000);
    clinical_m6_write_window_release();
    exit(0);
}
if (($argv[1] ?? '') === 'probe') {
    putenv('MXMED_CLINICAL_WRITE_WINDOW_CONTROL=FILE');
    putenv('MXMED_CLINICAL_WRITE_WINDOW_STATE_PATH=' . $argv[2]);
    try { clinical_m6_write_window_admit(); clinical_m6_write_window_release(); exit(2); }
    catch (ClinicalM6WriteWindowBlockedException) { exit(0); }
}

function cc_check(bool $ok, string $name): void { if (!$ok) throw new RuntimeException('FAIL ' . $name); echo "PASS: {$name}\n"; }
$root = sys_get_temp_dir() . '/mxmed-ww-concurrency-' . bin2hex(random_bytes(6)); mkdir($root,0700,true);
$state=$root.'/state.json'; $ready=$root.'/ready'; $release=$root.'/release';
clinical_m6_write_window_initialize_file($state);
putenv('MXMED_CLINICAL_WRITE_WINDOW_CONTROL=FILE'); putenv('MXMED_CLINICAL_WRITE_WINDOW_STATE_PATH='.$state);
$cmd=[PHP_BINARY,__FILE__,'worker',$state,$ready,$release];
$proc=proc_open($cmd,[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);
if (!is_resource($proc)) throw new RuntimeException('worker start failed');
$deadline=microtime(true)+5; while(!is_file($ready)&&microtime(true)<$deadline) usleep(10000);
cc_check(is_file($ready), 'worker admitted in separate process');
cc_check(clinical_m6_write_window_status()['active_writers']===1, 'cross-process active writer visible');
clinical_m6_write_window_set_state('BLOCK_WRITES');
$blocked=proc_close(proc_open([PHP_BINARY,__FILE__,'probe',$state],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$probePipes));
cc_check($blocked===0, 'new cross-process writer blocked after entry');
cc_check(clinical_m6_write_window_status()['active_writers']===1, 'admitted writer remains counted while blocked');
touch($release); foreach($pipes as $pipe) fclose($pipe); cc_check(proc_close($proc)===0, 'admitted writer completes');
$status=clinical_m6_write_window_status(); cc_check($status['state']==='BLOCK_WRITES'&&$status['active_writers']===0, 'observable quiescence');
clinical_m6_write_window_set_state('OPEN');
$GLOBALS['clinical_m6_write_window_admitted']=false; clinical_m6_write_window_admit(); clinical_m6_write_window_release();
cc_check(clinical_m6_write_window_status()['active_writers']===0, 'normal admission resumes after exit');
foreach([$ready,$release,$state] as $file) @unlink($file); rmdir($root);
echo "M6_WRITE_WINDOW_CONCURRENCY_TESTS_PASSED=7\n";
