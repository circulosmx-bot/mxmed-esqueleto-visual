<?php
declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/api/_lib/clinical_m6_write_window.php';

$passed = 0;
function ww_check(bool $ok, string $name): void { global $passed; if (!$ok) throw new RuntimeException('FAIL ' . $name); $passed++; echo "PASS: {$name}\n"; }
function ww_env(?string $mode, ?string $path = null): void {
    putenv('MXMED_CLINICAL_WRITE_WINDOW_CONTROL' . ($mode === null ? '' : '=' . $mode));
    putenv('MXMED_CLINICAL_WRITE_WINDOW_STATE_PATH' . ($path === null ? '' : '=' . $path));
}

ww_env(null, null);
$status = clinical_m6_write_window_status();
ww_check($status['state'] === 'OPEN' && $status['active_writers'] === 0, 'repository default remains open');

foreach (['garbage', 'block-ish', '0'] as $bad) {
    ww_env($bad, null);
    try { clinical_m6_write_window_status(); ww_check(false, 'bad mode rejected'); }
    catch (ClinicalM6WriteWindowConfigException) { ww_check(true, 'bad mode fails closed ' . $bad); }
}

$root = sys_get_temp_dir() . '/mxmed-ww-pure-' . bin2hex(random_bytes(6));
mkdir($root, 0700, true); $path = $root . '/state.json';
clinical_m6_write_window_initialize_file($path); ww_env('FILE', $path);
ww_check(clinical_m6_write_window_status()['state'] === 'OPEN', 'initialized state open');
clinical_m6_write_window_set_state('BLOCK_WRITES');
ww_check(clinical_m6_write_window_blocks_writes(), 'blocked state observable');
try { clinical_m6_write_window_admit(); ww_check(false, 'blocked admission rejected'); }
catch (ClinicalM6WriteWindowBlockedException $e) { ww_check($e->httpStatus() === 503, 'stable blocked response'); }
clinical_m6_write_window_set_state('OPEN');
clinical_m6_write_window_admit();
ww_check(clinical_m6_write_window_status()['active_writers'] === 1, 'writer counted');
clinical_m6_write_window_release();
ww_check(clinical_m6_write_window_status()['active_writers'] === 0, 'writer released');

$cases = [
    ['GET',['encounters','e1'],false], ['POST',['patients','p1','encounters'],true],
    ['POST',['note-capture-tokens'],false], ['GET',['note-capture-tokens','t1'],false],
    ['DELETE',['note-capture-tokens','t1'],false], ['POST',['note-capture-tokens','t1','upload'],true],
    ['PATCH',['encounters','e1','observations','o1'],true], ['POST',['documents','d1','amendments'],true],
];
foreach ($cases as [$method,$segments,$expected]) ww_check(clinical_m6_write_window_route_is_clinical_writer($method,$segments)===$expected, $method.' '.implode('/',$segments));

file_put_contents($path, '{bad json');
ww_check(clinical_m6_write_window_blocks_writes(), 'malformed state fails closed');
unlink($path); rmdir($root); ww_env(null, null);
echo "M6_WRITE_WINDOW_PURE_TESTS_PASSED={$passed}\n";
