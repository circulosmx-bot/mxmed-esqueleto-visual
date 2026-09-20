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

$passed = 0;
function lease_check(bool $ok, string $name): void {
    global $passed; if (!$ok) throw new RuntimeException('FAIL ' . $name);
    $passed++; echo "PASS: {$name}\n";
}
function lease_spawn(string $state, string $ready, string $release): array {
    $proc = proc_open([PHP_BINARY,__FILE__,'worker',$state,$ready,$release],
        [0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);
    if (!is_resource($proc)) throw new RuntimeException('worker start failed');
    $deadline=microtime(true)+5; while(!is_file($ready)&&microtime(true)<$deadline) usleep(10000);
    if (!is_file($ready)) throw new RuntimeException('worker admission timeout');
    return [$proc,$pipes];
}
function lease_close_pipes(array $pipes): void { foreach($pipes as $pipe) if(is_resource($pipe)) fclose($pipe); }
function lease_kill($proc, array $pipes): void {
    $status=proc_get_status($proc); if (($status['running']??false)===true) posix_kill((int)$status['pid'], SIGKILL);
    lease_close_pipes($pipes); proc_close($proc);
}
function lease_finish($proc,array $pipes,string $release): void { touch($release); lease_close_pipes($pipes); if(proc_close($proc)!==0) throw new RuntimeException('normal worker failed'); }
function lease_probe(string $state): int {
    $proc=proc_open([PHP_BINARY,__FILE__,'probe',$state],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);
    if(!is_resource($proc)) throw new RuntimeException('probe failed'); lease_close_pipes($pipes); return proc_close($proc);
}

$root=sys_get_temp_dir().'/mxmed-ww-crash-'.bin2hex(random_bytes(6)); mkdir($root,0700,true);
$state=$root.'/state.json'; clinical_m6_write_window_initialize_file($state);
putenv('MXMED_CLINICAL_WRITE_WINDOW_CONTROL=FILE'); putenv('MXMED_CLINICAL_WRITE_WINDOW_STATE_PATH='.$state);

[$a,$ap]=lease_spawn($state,$root.'/a.ready',$root.'/a.release');
lease_check(clinical_m6_write_window_status()['active_writers']===1,'CRASH-01 live lease counted');
lease_kill($a,$ap);
$after=clinical_m6_write_window_status();
lease_check($after['active_writers']===0 && count(glob($state.'.leases/*.lease')?:[])===0,'CRASH-01 SIGKILL stale lease recovered');

[$b,$bp]=lease_spawn($state,$root.'/b.ready',$root.'/b.release');
clinical_m6_write_window_set_state('BLOCK_WRITES');
lease_check(clinical_m6_write_window_status()['active_writers']===1,'CRASH-02 live writer not falsely reaped');
lease_check(lease_probe($state)===0,'CRASH-02 new writer rejected while blocked');
lease_kill($b,$bp);
$blocked=clinical_m6_write_window_status();
lease_check($blocked['state']==='BLOCK_WRITES'&&$blocked['active_writers']===0,'CRASH-02 hard kill drains to quiescence');

clinical_m6_write_window_set_state('OPEN');
[$c,$cp]=lease_spawn($state,$root.'/c.ready',$root.'/c.release');
[$d,$dp]=lease_spawn($state,$root.'/d.ready',$root.'/d.release');
lease_check(clinical_m6_write_window_status()['active_writers']===2,'mixed drain counts two live writers');
clinical_m6_write_window_set_state('BLOCK_WRITES');
lease_check(lease_probe($state)===0,'mixed drain rejects third writer');
lease_finish($c,$cp,$root.'/c.release');
lease_check(clinical_m6_write_window_status()['active_writers']===1,'mixed drain normal completion leaves live writer');
lease_kill($d,$dp);
lease_check(clinical_m6_write_window_status()['active_writers']===0,'mixed normal and SIGKILL drain reaches zero');

clinical_m6_write_window_set_state('OPEN');
clinical_m6_write_window_admit(); clinical_m6_write_window_release();
lease_check(clinical_m6_write_window_status()['active_writers']===0,'reopen after crash supports normal completion');

foreach(glob($root.'/*')?:[] as $path){ if(is_dir($path)){foreach(glob($path.'/*')?:[] as $child)unlink($child);rmdir($path);}else unlink($path); }
rmdir($root);
echo "M6_WRITE_WINDOW_CRASH_LEASE_TESTS_PASSED={$passed}\n";
