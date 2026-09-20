<?php
declare(strict_types=1);

/**
 * QA-only deterministic rendezvous for PHASE 2 M5 concurrency scenarios.
 *
 * Each HTTP client supplies:
 *   X-MXMed-M5-Barrier-Point
 *   X-MXMed-M5-Barrier-Participant: A|B
 *   X-MXMed-M5-Barrier-Run-Id
 *
 * The external QA controller waits for arrived_A and arrived_B, then creates
 * release. No state is stored in clinical tables.
 */

function clinical_m5_qa_barrier_points(): array
{
    return [
        'T04_CONCURRENT_START_BEFORE_INSERT',
        'T11_CONCURRENT_FINALIZE_BEFORE_TERMINAL_LOCK_OR_COMMIT',
        'T26_FINALIZE_VOID_RACE_BEFORE_TERMINAL_LOCK',
    ];
}

function clinical_m5_qa_truthy(string $value): bool
{
    return in_array(strtolower(trim($value)), ['1', 'true', 'yes', 'on'], true);
}

function clinical_m5_qa_mode_requested_from(array $environment): bool
{
    return clinical_m5_qa_truthy((string)($environment['MXMED_CLINICAL_M5_QA_MODE'] ?? ''));
}

function clinical_m5_qa_environment_allowed_from(array $environment, array $server): bool
{
    $identity = trim((string)($environment['MXMED_CLINICAL_M5_QA_ENVIRONMENT_ID'] ?? ''));
    if (preg_match('/^m5-disposable-[A-Za-z0-9._-]+$/', $identity) !== 1) {
        return false;
    }

    $build = strtolower(trim((string)($environment['MXMED_BUILD'] ?? '')));
    if (in_array($build, ['prod', 'production', 'staging'], true)) {
        return false;
    }

    $serverAddress = strtolower(trim((string)($server['SERVER_ADDR'] ?? '')));
    $localHost = in_array($serverAddress, ['127.0.0.1', '::1'], true);
    $developmentBuild = in_array($build, ['dev', 'development', 'local', 'test'], true);
    return $localHost || $developmentBuild;
}

function clinical_m5_qa_barrier_enabled_from(array $environment, array $server): bool
{
    return clinical_m5_qa_mode_requested_from($environment)
        && clinical_m5_qa_environment_allowed_from($environment, $server);
}

function clinical_m5_qa_environment_snapshot(): array
{
    $keys = [
        'MXMED_CLINICAL_M5_QA_MODE',
        'MXMED_CLINICAL_M5_QA_ENVIRONMENT_ID',
        'MXMED_CLINICAL_M5_BARRIER_DIR',
        'MXMED_CLINICAL_M5_BARRIER_TIMEOUT_MS',
        'MXMED_BUILD',
    ];
    $environment = [];
    foreach ($keys as $key) {
        $value = getenv($key);
        $environment[$key] = $value === false ? '' : (string)$value;
    }
    return $environment;
}

function clinical_m5_qa_safe_identifier(string $value, string $errorCode): string
{
    $value = trim($value);
    if ($value === '' || strlen($value) > 128 || preg_match('/^[A-Za-z0-9._-]+$/', $value) !== 1) {
        throw new RuntimeException($errorCode);
    }
    return $value;
}

function clinical_m5_qa_ensure_directory(string $directory): void
{
    if (is_dir($directory)) {
        return;
    }

    $created = false;
    try {
        $created = @mkdir($directory, 0700, true);
    } catch (Throwable) {
        // The HTTP runtime converts warnings to exceptions; verify the path below.
    }
    if ($created) {
        return;
    }

    clearstatcache(true, $directory);
    if (is_dir($directory)) {
        return;
    }

    throw new RuntimeException('M5_QA_BARRIER_DIRECTORY_CREATE_FAILED');
}

function clinical_m5_qa_barrier_reach(array $allowedPoints): bool
{
    $environment = clinical_m5_qa_environment_snapshot();
    if (!clinical_m5_qa_mode_requested_from($environment)) {
        return false;
    }
    if (!clinical_m5_qa_environment_allowed_from($environment, $_SERVER)) {
        throw new RuntimeException('M5_QA_BARRIER_ENVIRONMENT_DENIED');
    }

    $requestedPoint = trim((string)($_SERVER['HTTP_X_MXMED_M5_BARRIER_POINT'] ?? ''));
    if ($requestedPoint === '') {
        return false;
    }
    $knownPoints = clinical_m5_qa_barrier_points();
    if (!in_array($requestedPoint, $knownPoints, true) || !in_array($requestedPoint, $allowedPoints, true)) {
        throw new RuntimeException('M5_QA_BARRIER_POINT_DENIED');
    }

    $participant = strtoupper(trim((string)($_SERVER['HTTP_X_MXMED_M5_BARRIER_PARTICIPANT'] ?? '')));
    if (!in_array($participant, ['A', 'B'], true)) {
        throw new RuntimeException('M5_QA_BARRIER_PARTICIPANT_INVALID');
    }
    $runId = clinical_m5_qa_safe_identifier(
        (string)($_SERVER['HTTP_X_MXMED_M5_BARRIER_RUN_ID'] ?? ''),
        'M5_QA_BARRIER_RUN_ID_INVALID'
    );
    $environmentId = clinical_m5_qa_safe_identifier(
        (string)$environment['MXMED_CLINICAL_M5_QA_ENVIRONMENT_ID'],
        'M5_QA_BARRIER_ENVIRONMENT_DENIED'
    );
    $root = trim((string)$environment['MXMED_CLINICAL_M5_BARRIER_DIR']);
    if ($root === '' || $root === '/' || !str_starts_with($root, DIRECTORY_SEPARATOR)) {
        throw new RuntimeException('M5_QA_BARRIER_DIRECTORY_INVALID');
    }

    $directory = rtrim($root, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $environmentId
        . DIRECTORY_SEPARATOR . $runId . DIRECTORY_SEPARATOR . $requestedPoint;
    clinical_m5_qa_ensure_directory($directory);

    $arrival = $directory . DIRECTORY_SEPARATOR . 'arrived_' . $participant;
    $handle = @fopen($arrival, 'x');
    if ($handle === false) {
        throw new RuntimeException('M5_QA_BARRIER_PARTICIPANT_DUPLICATE');
    }
    fwrite($handle, (string)getmypid() . "\n");
    fclose($handle);

    $timeoutMs = (int)($environment['MXMED_CLINICAL_M5_BARRIER_TIMEOUT_MS'] ?: 20000);
    $timeoutMs = max(100, min($timeoutMs, 120000));
    $deadline = microtime(true) + ($timeoutMs / 1000);
    $arrivedA = $directory . DIRECTORY_SEPARATOR . 'arrived_A';
    $arrivedB = $directory . DIRECTORY_SEPARATOR . 'arrived_B';
    $release = $directory . DIRECTORY_SEPARATOR . 'release';
    while (microtime(true) < $deadline) {
        clearstatcache();
        if (is_file($arrivedA) && is_file($arrivedB) && is_file($release)) {
            return true;
        }
        usleep(10000);
    }
    throw new RuntimeException('M5_QA_BARRIER_TIMEOUT');
}
