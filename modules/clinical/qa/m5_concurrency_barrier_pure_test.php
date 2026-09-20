<?php
declare(strict_types=1);

$passed = 0;
function m5_check(bool $condition, string $name): void
{
    global $passed;
    if (!$condition) throw new RuntimeException('FAIL: ' . $name);
    $passed++;
    echo "PASS: {$name}\n";
}

function m5_isolated_php(string $code): array
{
    $pipes = [];
    $process = proc_open(
        [PHP_BINARY, '-d', 'display_errors=1', '-r', $code],
        [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
        $pipes
    );
    if (!is_resource($process)) {
        throw new RuntimeException('FAIL: unable to start isolated PHP probe');
    }
    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    return [proc_close($process), $stdout, $stderr];
}

$integrityFile = realpath(__DIR__ . '/../../../api/_lib/clinical_encounter_integrity.php');
if ($integrityFile === false) throw new RuntimeException('FAIL: integrity runtime not found');
$integrityLiteral = var_export($integrityFile, true);

[$status, $stdout, $stderr] = m5_isolated_php(<<<PHP
putenv('MXMED_CLINICAL_M5_QA_MODE');
require {$integrityLiteral};
if (function_exists('clinical_m5_qa_barrier_reach')) exit(10);
if (clinical_m5_qa_barrier_reach_if_enabled(['T04_CONCURRENT_START_BEFORE_INSERT'], '/missing/m5-barrier.php') !== false) exit(11);
if (function_exists('clinical_m5_qa_barrier_reach')) exit(12);
PHP);
m5_check($status === 0, 'QA mode off does not load or require QA implementation: ' . trim($stdout . $stderr));

$offDirectory = sys_get_temp_dir() . '/mxmed-m5-r1-off-' . bin2hex(random_bytes(8));
$offDirectoryLiteral = var_export($offDirectory, true);
[$status, $stdout, $stderr] = m5_isolated_php(<<<PHP
putenv('MXMED_CLINICAL_M5_QA_MODE=0');
putenv('MXMED_CLINICAL_M5_BARRIER_DIR=' . {$offDirectoryLiteral});
require {$integrityLiteral};
clinical_m5_qa_barrier_reach_if_enabled(['T04_CONCURRENT_START_BEFORE_INSERT'], '/missing/m5-barrier.php');
if (file_exists({$offDirectoryLiteral})) exit(20);
PHP);
m5_check($status === 0 && !file_exists($offDirectory), 'QA mode off creates no barrier filesystem state: ' . trim($stdout . $stderr));

[$status, $stdout, $stderr] = m5_isolated_php(<<<PHP
putenv('MXMED_CLINICAL_M5_QA_MODE=1');
putenv('MXMED_CLINICAL_M5_QA_ENVIRONMENT_ID=m5-disposable-lazy-load-test');
putenv('MXMED_BUILD=test');
\$_SERVER['SERVER_ADDR'] = '127.0.0.1';
require {$integrityLiteral};
if (function_exists('clinical_m5_qa_barrier_reach')) exit(25);
if (clinical_m5_qa_barrier_reach_if_enabled(['T04_CONCURRENT_START_BEFORE_INSERT']) !== false) exit(26);
if (!function_exists('clinical_m5_qa_barrier_reach')) exit(27);
PHP);
m5_check($status === 0, 'QA mode on lazily loads the barrier implementation: ' . trim($stdout . $stderr));

[$status, $stdout, $stderr] = m5_isolated_php(<<<PHP
putenv('MXMED_CLINICAL_M5_QA_MODE=1');
require {$integrityLiteral};
try {
    clinical_m5_qa_barrier_reach_if_enabled(['T04_CONCURRENT_START_BEFORE_INSERT'], '/missing/m5-barrier.php');
} catch (RuntimeException \$error) {
    if (\$error->getMessage() === 'M5_QA_BARRIER_IMPLEMENTATION_NOT_AVAILABLE') exit(0);
}
exit(30);
PHP);
m5_check($status === 0, 'QA mode on with missing implementation fails closed: ' . trim($stdout . $stderr));

require_once __DIR__ . '/m5_concurrency_barrier.php';

$validEnvironment = [
    'MXMED_CLINICAL_M5_QA_MODE' => '1',
    'MXMED_CLINICAL_M5_QA_ENVIRONMENT_ID' => 'm5-disposable-pure-test',
    'MXMED_BUILD' => 'dev',
];
m5_check(!clinical_m5_qa_mode_requested_from([]), 'M5 QA barrier defaults off');
m5_check(!clinical_m5_qa_barrier_enabled_from([
    'MXMED_CLINICAL_M5_QA_ENVIRONMENT_ID' => 'm5-disposable-pure-test',
    'MXMED_BUILD' => 'dev',
], ['HTTP_HOST' => '127.0.0.1']), 'explicit QA enable is required');
m5_check(clinical_m5_qa_barrier_enabled_from($validEnvironment, ['SERVER_ADDR' => '127.0.0.1']), 'local disposable QA environment is allowed');
m5_check(!clinical_m5_qa_environment_allowed_from(array_replace($validEnvironment, ['MXMED_BUILD' => 'prod']), ['SERVER_ADDR' => '127.0.0.1']), 'production build is denied even on local host');
m5_check(!clinical_m5_qa_environment_allowed_from([
    'MXMED_CLINICAL_M5_QA_MODE' => '1',
    'MXMED_CLINICAL_M5_QA_ENVIRONMENT_ID' => 'working-mxmed',
    'MXMED_BUILD' => 'dev',
], ['SERVER_ADDR' => '127.0.0.1']), 'non-disposable environment identity is denied');
m5_check(clinical_m5_qa_barrier_points() === [
    'T04_CONCURRENT_START_BEFORE_INSERT',
    'T11_CONCURRENT_FINALIZE_BEFORE_TERMINAL_LOCK_OR_COMMIT',
    'T26_FINALIZE_VOID_RACE_BEFORE_TERMINAL_LOCK',
], 'only accepted M5 barrier points are exposed');

$directoryRoot = sys_get_temp_dir() . '/mxmed-m5-r2-directory-' . bin2hex(random_bytes(8));
$directory = $directoryRoot . '/nested/barrier';
clinical_m5_qa_ensure_directory($directory);
m5_check(is_dir($directory), 'barrier directory is created');
$directoryMode = fileperms($directory);
m5_check($directoryMode !== false && (($directoryMode & 0777) === 0700), 'barrier directory mode is 0700');
clinical_m5_qa_ensure_directory($directory);
m5_check(is_dir($directory), 'existing barrier directory is accepted idempotently');

$failurePath = $directoryRoot . '/not-a-directory';
file_put_contents($failurePath, "file\n");
$directoryFailure = null;
set_error_handler(static function (int $severity, string $message, string $file, int $line): void {
    throw new ErrorException($message, 0, $severity, $file, $line);
});
try {
    clinical_m5_qa_ensure_directory($failurePath);
} catch (Throwable $error) {
    $directoryFailure = $error;
} finally {
    restore_error_handler();
}
m5_check(
    $directoryFailure instanceof RuntimeException
        && $directoryFailure->getMessage() === 'M5_QA_BARRIER_DIRECTORY_CREATE_FAILED',
    'actual directory creation failure fails closed under strict HTTP warning handling'
);
unlink($failurePath);
rmdir($directory);
rmdir(dirname($directory));
rmdir($directoryRoot);

$previousMode = getenv('MXMED_CLINICAL_M5_QA_MODE');
putenv('MXMED_CLINICAL_M5_QA_MODE');
$before = glob(sys_get_temp_dir() . '/mxmed-m5-mode-off-*') ?: [];
m5_check(clinical_m5_qa_barrier_reach(clinical_m5_qa_barrier_points()) === false, 'mode off has zero barrier effect');
$after = glob(sys_get_temp_dir() . '/mxmed-m5-mode-off-*') ?: [];
m5_check($before === $after, 'mode off creates no barrier filesystem state');
if ($previousMode === false) putenv('MXMED_CLINICAL_M5_QA_MODE');
else putenv('MXMED_CLINICAL_M5_QA_MODE=' . $previousMode);

echo "M5_PREP01_PURE_TESTS_PASSED={$passed}\n";
