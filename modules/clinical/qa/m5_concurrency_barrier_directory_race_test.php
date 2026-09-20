<?php
declare(strict_types=1);

const M5_RACE_POINT = 'T26_FINALIZE_VOID_RACE_BEFORE_TERMINAL_LOCK';
const M5_RACE_ENVIRONMENT_ID = 'm5-disposable-barrier-race-test';
const M5_RACE_ROUNDS = 40;

function m5_race_remove_tree(string $path): void
{
    if (!file_exists($path) && !is_link($path)) {
        return;
    }
    if (!is_dir($path) || is_link($path)) {
        @unlink($path);
        return;
    }
    $entries = scandir($path);
    if ($entries !== false) {
        foreach ($entries as $entry) {
            if ($entry !== '.' && $entry !== '..') {
                m5_race_remove_tree($path . DIRECTORY_SEPARATOR . $entry);
            }
        }
    }
    @rmdir($path);
}

function m5_race_wait_for_files(array $files, float $timeoutSeconds): void
{
    $deadline = microtime(true) + $timeoutSeconds;
    do {
        clearstatcache();
        $ready = true;
        foreach ($files as $file) {
            if (!is_file($file)) {
                $ready = false;
                break;
            }
        }
        if ($ready) {
            return;
        }
        usleep(1000);
    } while (microtime(true) < $deadline);

    throw new RuntimeException('M5_RACE_PROCESS_READY_TIMEOUT');
}

function m5_race_start_process(array $command): array
{
    $pipes = [];
    $process = proc_open(
        $command,
        [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
        $pipes
    );
    if (!is_resource($process)) {
        throw new RuntimeException('M5_RACE_PROCESS_START_FAILED');
    }
    return [$process, $pipes];
}

function m5_race_finish_process(array $started): array
{
    [$process, $pipes] = $started;
    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    return [proc_close($process), (string)$stdout, (string)$stderr];
}

function m5_race_worker(array $arguments): void
{
    if (count($arguments) !== 6) {
        fwrite(STDERR, "M5_RACE_WORKER_ARGUMENTS_INVALID\n");
        exit(64);
    }
    [, $barrierRoot, $runId, $participant, $gate, $ready] = $arguments;

    putenv('MXMED_CLINICAL_M5_QA_MODE=1');
    putenv('MXMED_CLINICAL_M5_QA_ENVIRONMENT_ID=' . M5_RACE_ENVIRONMENT_ID);
    putenv('MXMED_CLINICAL_M5_BARRIER_DIR=' . $barrierRoot);
    putenv('MXMED_CLINICAL_M5_BARRIER_TIMEOUT_MS=5000');
    putenv('MXMED_BUILD=test');
    $_SERVER['SERVER_ADDR'] = '127.0.0.1';
    $_SERVER['HTTP_X_MXMED_M5_BARRIER_POINT'] = M5_RACE_POINT;
    $_SERVER['HTTP_X_MXMED_M5_BARRIER_PARTICIPANT'] = $participant;
    $_SERVER['HTTP_X_MXMED_M5_BARRIER_RUN_ID'] = $runId;

    require_once __DIR__ . '/m5_concurrency_barrier.php';
    set_error_handler(static function (int $severity, string $message, string $file, int $line): void {
        throw new ErrorException($message, 0, $severity, $file, $line);
    });

    if (file_put_contents($ready, "ready\n", LOCK_EX) === false) {
        fwrite(STDERR, "M5_RACE_WORKER_READY_CREATE_FAILED\n");
        exit(70);
    }
    $deadline = microtime(true) + 5.0;
    while (!is_file($gate)) {
        if (microtime(true) >= $deadline) {
            fwrite(STDERR, "M5_RACE_WORKER_GATE_TIMEOUT\n");
            exit(70);
        }
        clearstatcache(true, $gate);
        usleep(1000);
    }

    try {
        if (!clinical_m5_qa_barrier_reach([M5_RACE_POINT])) {
            throw new RuntimeException('M5_RACE_BARRIER_NOT_REACHED');
        }
        restore_error_handler();
        echo 'WORKER_' . $participant . "_OK\n";
        exit(0);
    } catch (Throwable $error) {
        restore_error_handler();
        fwrite(STDERR, get_class($error) . ': ' . $error->getMessage() . "\n");
        exit(70);
    }
}

if (($argv[1] ?? '') === '--worker') {
    m5_race_worker(array_slice($argv, 1));
}

$base = sys_get_temp_dir() . '/mxmed-m5-r2-race-' . getmypid() . '-' . bin2hex(random_bytes(6));
$controlDirectory = $base . '/control';
if (!mkdir($controlDirectory, 0700, true) && !is_dir($controlDirectory)) {
    throw new RuntimeException('M5_RACE_CONTROL_DIRECTORY_CREATE_FAILED');
}

$controllerPath = __DIR__ . '/m5_barrier_release.sh';
$workerCount = 2;
$completedRounds = 0;
$bothAbsentAtRelease = true;
$workerASetupSuccess = true;
$workerBSetupSuccess = true;
$noMkdirFileExistsFailure = true;
$arrivedACreated = true;
$arrivedBCreated = true;

try {
    for ($round = 1; $round <= M5_RACE_ROUNDS; $round++) {
        $suffix = str_pad((string)$round, 3, '0', STR_PAD_LEFT);
        $runId = 'directory-race-' . $suffix;
        $barrierRoot = $base . '/round-' . $suffix . '/barriers';
        $directory = $barrierRoot . '/' . M5_RACE_ENVIRONMENT_ID . '/' . $runId . '/' . M5_RACE_POINT;
        $gate = $controlDirectory . '/gate-' . $suffix;
        $readyA = $controlDirectory . '/ready-A-' . $suffix;
        $readyB = $controlDirectory . '/ready-B-' . $suffix;

        $controller = m5_race_start_process([
            'bash', $controllerPath, $barrierRoot, M5_RACE_ENVIRONMENT_ID, $runId, M5_RACE_POINT, '5',
        ]);
        $workerA = m5_race_start_process([
            PHP_BINARY, __FILE__, '--worker', $barrierRoot, $runId, 'A', $gate, $readyA,
        ]);
        $workerB = m5_race_start_process([
            PHP_BINARY, __FILE__, '--worker', $barrierRoot, $runId, 'B', $gate, $readyB,
        ]);

        m5_race_wait_for_files([$readyA, $readyB], 5.0);
        if (is_dir($directory)) {
            $bothAbsentAtRelease = false;
            throw new RuntimeException('M5_RACE_DIRECTORY_EXISTED_BEFORE_RELEASE');
        }
        if (file_put_contents($gate, "go\n", LOCK_EX) === false) {
            throw new RuntimeException('M5_RACE_GATE_CREATE_FAILED');
        }

        [$statusA, $stdoutA, $stderrA] = m5_race_finish_process($workerA);
        [$statusB, $stdoutB, $stderrB] = m5_race_finish_process($workerB);
        [$controllerStatus, $controllerStdout, $controllerStderr] = m5_race_finish_process($controller);
        $combinedOutput = $stdoutA . $stderrA . $stdoutB . $stderrB . $controllerStdout . $controllerStderr;

        $workerAOk = $statusA === 0 && str_contains($stdoutA, 'WORKER_A_OK');
        $workerBOk = $statusB === 0 && str_contains($stdoutB, 'WORKER_B_OK');
        $workerASetupSuccess = $workerASetupSuccess && $workerAOk;
        $workerBSetupSuccess = $workerBSetupSuccess && $workerBOk;
        $noMkdirFileExistsFailure = $noMkdirFileExistsFailure
            && stripos($combinedOutput, 'mkdir(): File exists') === false;
        $arrivedACreated = $arrivedACreated && is_file($directory . '/arrived_A');
        $arrivedBCreated = $arrivedBCreated && is_file($directory . '/arrived_B');

        if (!$workerAOk || !$workerBOk || $controllerStatus !== 0
            || !$noMkdirFileExistsFailure || !$arrivedACreated || !$arrivedBCreated
            || !is_file($directory . '/release')) {
            throw new RuntimeException(
                'M5_RACE_ROUND_FAILED round=' . $round . ' output=' . trim($combinedOutput)
            );
        }
        $completedRounds++;
    }

    echo "M5_BARRIER_DIRECTORY_CONCURRENCY_REGRESSION=PASS\n";
    echo 'CONCURRENT_TEST_WORKER_COUNT=' . $workerCount . "\n";
    echo 'CONCURRENT_TEST_ROUNDS=' . $completedRounds . "\n";
    echo 'BOTH_WORKERS_START_WITH_DIRECTORY_ABSENT=' . ($bothAbsentAtRelease ? 'true' : 'false') . "\n";
    echo 'WORKER_A_DIRECTORY_SETUP_SUCCESS=' . ($workerASetupSuccess ? 'true' : 'false') . "\n";
    echo 'WORKER_B_DIRECTORY_SETUP_SUCCESS=' . ($workerBSetupSuccess ? 'true' : 'false') . "\n";
    echo 'NO_MKDIR_FILE_EXISTS_FAILURE=' . ($noMkdirFileExistsFailure ? 'true' : 'false') . "\n";
    echo 'ARRIVED_A_CREATED=' . ($arrivedACreated ? 'true' : 'false') . "\n";
    echo 'ARRIVED_B_CREATED=' . ($arrivedBCreated ? 'true' : 'false') . "\n";
} finally {
    m5_race_remove_tree($base);
}
