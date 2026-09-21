<?php
declare(strict_types=1);

const CLINICAL_M6_OBSERVABILITY_SCHEMA = 'mxmed.m6.telemetry.v1';
const CLINICAL_M6_OBSERVABILITY_DEFAULT_RETENTION_DAYS = 14;
const CLINICAL_M6_OBSERVABILITY_DEFAULT_MAX_BYTES = 16777216;

function clinical_m6_observability_root(): ?string
{
    $raw = getenv('MXMED_CLINICAL_M6_OBSERVABILITY_ROOT');
    if (!is_string($raw) || trim($raw) === '') return null;
    $root = rtrim(trim($raw), DIRECTORY_SEPARATOR);
    if ($root === '' || $root[0] !== DIRECTORY_SEPARATOR || str_contains($root, "\0")) {
        throw new RuntimeException('MONITORING_ROOT_INVALID');
    }
    $documentRoot = rtrim((string)($_SERVER['DOCUMENT_ROOT'] ?? ''), DIRECTORY_SEPARATOR);
    if ($documentRoot !== '' && ($root === $documentRoot || str_starts_with($root . '/', $documentRoot . '/'))) {
        throw new RuntimeException('MONITORING_ROOT_PUBLIC');
    }
    return $root;
}

function clinical_m6_observability_retention_days(): int
{
    $raw = getenv('MXMED_CLINICAL_M6_OBSERVABILITY_RETENTION_DAYS');
    if ($raw === false || trim((string)$raw) === '') return CLINICAL_M6_OBSERVABILITY_DEFAULT_RETENTION_DAYS;
    if (!preg_match('/^[0-9]+$/D', (string)$raw) || (int)$raw < 1 || (int)$raw > 365) {
        throw new RuntimeException('MONITORING_RETENTION_INVALID');
    }
    return (int)$raw;
}

function clinical_m6_observability_max_bytes(): int
{
    $raw = getenv('MXMED_CLINICAL_M6_OBSERVABILITY_MAX_BYTES');
    if ($raw === false || trim((string)$raw) === '') return CLINICAL_M6_OBSERVABILITY_DEFAULT_MAX_BYTES;
    if (!preg_match('/^[0-9]+$/D', (string)$raw) || (int)$raw < 65536 || (int)$raw > 268435456) {
        throw new RuntimeException('MONITORING_MAX_BYTES_INVALID');
    }
    return (int)$raw;
}

function clinical_m6_observability_initialize(string $root): array
{
    putenv('MXMED_CLINICAL_M6_OBSERVABILITY_ROOT=' . $root);
    $root = clinical_m6_observability_root();
    if ($root === null) throw new RuntimeException('MONITORING_ROOT_REQUIRED');
    if (!is_dir($root) && (!mkdir($root, 0770, true) || !chmod($root, 0770))) {
        throw new RuntimeException('MONITORING_ROOT_CREATE_FAILED');
    }
    if (is_link($root) || !is_readable($root) || !is_writable($root)) {
        throw new RuntimeException('MONITORING_ROOT_UNAVAILABLE');
    }
    $mode = fileperms($root);
    if (!is_int($mode) || (($mode & 0007) !== 0)) throw new RuntimeException('MONITORING_ROOT_PERMISSIONS_INVALID');
    foreach (['snapshots', 'baselines'] as $child) {
        $path = $root . DIRECTORY_SEPARATOR . $child;
        if (!is_dir($path) && (!mkdir($path, 0770) || !chmod($path, 0770))) {
            throw new RuntimeException('MONITORING_ROOT_CREATE_FAILED');
        }
    }
    return clinical_m6_observability_self_health(false);
}

function clinical_m6_observability_correlation_id(): string
{
    if (!isset($GLOBALS['clinical_m6_observability_correlation_id'])) {
        $GLOBALS['clinical_m6_observability_correlation_id'] = bin2hex(random_bytes(16));
    }
    return (string)$GLOBALS['clinical_m6_observability_correlation_id'];
}

function clinical_m6_observability_request_started_at(): float
{
    if (!isset($GLOBALS['clinical_m6_observability_started_at'])) {
        $GLOBALS['clinical_m6_observability_started_at'] = microtime(true);
    }
    return (float)$GLOBALS['clinical_m6_observability_started_at'];
}

function clinical_m6_observability_route(string $family, string $operation, string $authority): void
{
    $allowed = ['CANONICAL_V1', 'PATIENT_LEVEL_C04', 'GUARDED_LEGACY', 'BLOCKED', 'READ_ONLY', 'UNCLASSIFIED'];
    $authority = strtoupper(trim($authority));
    if (!in_array($authority, $allowed, true)) $authority = 'UNCLASSIFIED';
    $GLOBALS['clinical_m6_observability_route'] = [
        'route_family' => clinical_m6_observability_safe_label($family, 'unknown'),
        'operation' => clinical_m6_observability_safe_label($operation, 'unknown'),
        'authority_branch' => $authority,
    ];
    clinical_m6_observability_emit('route_selection', [
        ...$GLOBALS['clinical_m6_observability_route'],
        'outcome' => 'selected',
    ]);
}

function clinical_m6_observability_safe_label(mixed $value, string $fallback = 'unknown'): string
{
    $value = strtoupper(trim((string)$value));
    return preg_match('/^[A-Z0-9_.:\/-]{1,96}$/D', $value) ? $value : $fallback;
}

function clinical_m6_observability_error_identity(mixed $error): ?string
{
    if (is_array($error)) $error = $error['code'] ?? null;
    $value = strtoupper(trim((string)$error));
    if ($value === '') return null;
    $value = explode(':', $value, 2)[0];
    return preg_match('/^[A-Z0-9_]{1,96}$/D', $value) ? $value : 'UNCLASSIFIED_ERROR';
}

function clinical_m6_observability_feature_state(): array
{
    require_once __DIR__ . '/clinical_m6_cutover.php';
    require_once __DIR__ . '/clinical_encounter_integrity.php';
    $valid = true;
    $error = null;
    try {
        $mode = clinical_m6_cohort_mode();
        $emergency = clinical_m6_emergency_off();
        $pairs = clinical_m6_cohort_pairs();
    } catch (Throwable $e) {
        $valid = false;
        $error = clinical_m6_observability_error_identity($e->getMessage());
        $mode = 'invalid';
        $emergency = true;
        $pairs = [];
    }
    $canonical = [];
    foreach ($pairs as $pair) $canonical[] = ($pair['doctor_id'] ?? '') . '|' . ($pair['patient_id'] ?? '');
    sort($canonical, SORT_STRING);
    $master = clinical_encounter_integrity_v1_enabled();
    $generationRaw = getenv('MXMED_CLINICAL_M6_WORKER_GENERATION');
    $generation = is_string($generationRaw) && preg_match('/^[A-Za-z0-9_.:-]{1,64}$/D', trim($generationRaw))
        ? trim($generationRaw) : 'unconfigured';
    return [
        'master_enabled' => $master,
        'cohort_mode' => $mode,
        'emergency_off' => $emergency,
        'config_valid' => $valid,
        'config_error' => $error,
        'config_fingerprint' => hash('sha256', json_encode([$master, $mode, $emergency, $canonical], JSON_THROW_ON_ERROR)),
        'worker_generation' => $generation,
        'worker_pid_hash' => hash('sha256', gethostname() . '|' . getmypid()),
    ];
}

function clinical_m6_observability_write_window_state(): array
{
    require_once __DIR__ . '/clinical_m6_write_window.php';
    try {
        $status = clinical_m6_write_window_status();
        return [
            'state' => $status['state'] ?? 'UNKNOWN',
            'generation' => $status['generation'] ?? null,
            'live_writer_leases' => $status['live_writer_leases'] ?? null,
            'stale_leases_recovered' => $status['stale_leases_recovered'] ?? 0,
            'updated_at' => $status['updated_at'] ?? null,
            'authority' => $status['authority'] ?? 'unknown',
            'healthy' => true,
        ];
    } catch (Throwable) {
        return ['state'=>'FAIL_CLOSED','generation'=>null,'live_writer_leases'=>null,'authority'=>'unavailable','healthy'=>false];
    }
}

function clinical_m6_observability_classification(?string $error, int $status): string
{
    if (in_array($error, ['M6_WRITE_WINDOW_BLOCKED','M6_LEGACY_WRITE_BLOCKED','NOTE_CAPTURE_TOKEN_EXPIRED',
        'NOTE_CAPTURE_TOKEN_CANCELLED','NOTE_CAPTURE_TOKEN_CONSUMED','IDEMPOTENCY_KEY_REUSED'], true)) return 'expected_protection';
    if (in_array($error, ['M6_COHORT_CONFIG_INVALID','DOCUMENT_CONTEXT_MISMATCH','SCHEMA_NOT_READY',
        'DOCUMENT_BINARY_INTEGRITY_MISMATCH','M6_WRITE_WINDOW_CONFIG_INVALID'], true)) return 'failure';
    if ($status >= 500) return 'failure';
    if ($status >= 400) return 'protection';
    return 'success';
}

function clinical_m6_observability_emit(string $eventType, array $fields = []): bool
{
    try {
        $root = clinical_m6_observability_root();
        if ($root === null) return false;
        if (!is_dir($root) || is_link($root) || !is_writable($root)) return false;
        $route = is_array($GLOBALS['clinical_m6_observability_route'] ?? null)
            ? $GLOBALS['clinical_m6_observability_route'] : [];
        $gate = clinical_m6_observability_feature_state();
        $window = clinical_m6_observability_write_window_state();
        $safe = clinical_m6_observability_safe_fields($fields);
        $event = array_merge([
            'timestamp' => gmdate('Y-m-d\TH:i:s.u\Z'),
            'schema' => CLINICAL_M6_OBSERVABILITY_SCHEMA,
            'event_type' => clinical_m6_observability_safe_label($eventType),
            'correlation_id' => clinical_m6_observability_correlation_id(),
            'route_family' => $route['route_family'] ?? 'UNKNOWN',
            'operation' => $route['operation'] ?? 'UNKNOWN',
            'authority_branch' => $route['authority_branch'] ?? 'UNCLASSIFIED',
            'gate' => $gate,
            'write_window' => $window,
        ], $safe);
        $json = json_encode($event, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) . "\n";
        clinical_m6_observability_append($root, $json);
        return true;
    } catch (Throwable $e) {
        error_log('M6_MONITORING_VISIBILITY_LOSS:' . clinical_m6_observability_error_identity($e->getMessage()));
        return false;
    }
}

function clinical_m6_observability_safe_fields(array $fields): array
{
    $forbidden = '/patient|doctor|token|password|secret|credential|content|payload|allowlist|path|filename|binary/i';
    $safe = [];
    foreach ($fields as $key => $value) {
        $key = (string)$key;
        if (!preg_match('/^[a-z][a-z0-9_]{0,63}$/D', $key) || preg_match($forbidden, $key)) continue;
        if ($value === null || is_bool($value) || is_int($value) || is_float($value)) {
            $safe[$key] = $value;
        } elseif (is_string($value) && strlen($value) <= 256 && !preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', $value)) {
            $safe[$key] = $value;
        }
    }
    return $safe;
}

function clinical_m6_observability_append(string $root, string $line): void
{
    $lockPath = $root . DIRECTORY_SEPARATOR . '.telemetry.lock';
    $lock = fopen($lockPath, 'c+');
    if (!is_resource($lock) || !flock($lock, LOCK_EX)) throw new RuntimeException('MONITORING_LOCK_FAILED');
    try {
        @chmod($lockPath, 0660);
        $active = $root . DIRECTORY_SEPARATOR . 'events.ndjson';
        $rotated = $root . DIRECTORY_SEPARATOR . 'events.previous.ndjson';
        clearstatcache(true, $active);
        $size = is_file($active) ? filesize($active) : 0;
        if (is_int($size) && $size + strlen($line) > clinical_m6_observability_max_bytes()) {
            if (is_file($rotated) && !unlink($rotated)) throw new RuntimeException('MONITORING_ROTATION_FAILED');
            if (is_file($active) && !rename($active, $rotated)) throw new RuntimeException('MONITORING_ROTATION_FAILED');
        }
        $handle = fopen($active, 'ab');
        if (!is_resource($handle) || fwrite($handle, $line) !== strlen($line) || !fflush($handle)) {
            if (is_resource($handle)) fclose($handle);
            throw new RuntimeException('MONITORING_APPEND_FAILED');
        }
        fclose($handle);
        @chmod($active, 0660);
    } finally {
        flock($lock, LOCK_UN);
        fclose($lock);
    }
}

function clinical_m6_observability_response(array $response, int $status): void
{
    $error = clinical_m6_observability_error_identity($response['error'] ?? null);
    $meta = is_object($response['meta'] ?? null) ? (array)$response['meta'] : (array)($response['meta'] ?? []);
    $duration = max(0, (int)round((microtime(true) - clinical_m6_observability_request_started_at()) * 1000));
    clinical_m6_observability_emit('request_outcome', [
        'outcome' => clinical_m6_observability_classification($error, $status),
        'http_status' => $status,
        'http_status_class' => intdiv($status, 100) . 'xx',
        'error_identity' => $error,
        'duration_ms' => $duration,
        'idempotency_replay' => ($meta['idempotency_replay'] ?? false) === true,
        'unexpected_fallback' => (bool)($GLOBALS['clinical_m6_observability_unexpected_fallback'] ?? false),
        'parallel_writer_evidence' => (bool)($GLOBALS['clinical_m6_observability_parallel_writer'] ?? false),
    ]);
}

function clinical_m6_observability_storage(string $stage, bool $success, ?string $error = null): void
{
    clinical_m6_observability_emit('private_storage', [
        'stage' => clinical_m6_observability_safe_label($stage),
        'outcome' => $success ? 'success' : 'failure',
        'error_identity' => clinical_m6_observability_error_identity($error),
    ]);
}

function clinical_m6_observability_unexpected_fallback(string $operation): void
{
    $GLOBALS['clinical_m6_observability_unexpected_fallback'] = true;
    clinical_m6_observability_emit('unexpected_fallback', [
        'operation' => clinical_m6_observability_safe_label($operation),
        'outcome' => 'failure',
        'unexpected_fallback' => true,
    ]);
}

function clinical_m6_observability_parallel_writer_evidence(string $operation): void
{
    $GLOBALS['clinical_m6_observability_parallel_writer'] = true;
    clinical_m6_observability_emit('parallel_writer_evidence', [
        'operation' => clinical_m6_observability_safe_label($operation),
        'outcome' => 'failure',
        'parallel_writer_evidence' => true,
    ]);
}

function clinical_m6_observability_read_events(?int $sinceEpoch = null): array
{
    $root = clinical_m6_observability_root();
    if ($root === null) throw new RuntimeException('MONITORING_ROOT_REQUIRED');
    $events = [];
    foreach (['events.previous.ndjson', 'events.ndjson'] as $name) {
        $path = $root . DIRECTORY_SEPARATOR . $name;
        if (!is_file($path)) continue;
        $handle = fopen($path, 'rb');
        if (!is_resource($handle)) throw new RuntimeException('MONITORING_READ_FAILED');
        while (($line = fgets($handle)) !== false) {
            $row = json_decode($line, true);
            if (!is_array($row) || ($row['schema'] ?? null) !== CLINICAL_M6_OBSERVABILITY_SCHEMA) {
                fclose($handle);
                throw new RuntimeException('MONITORING_EVENT_INVALID');
            }
            $epoch = strtotime((string)($row['timestamp'] ?? ''));
            if ($sinceEpoch === null || ($epoch !== false && $epoch >= $sinceEpoch)) $events[] = $row;
        }
        fclose($handle);
    }
    return $events;
}

function clinical_m6_observability_aggregate(array $events): array
{
    $summary = ['event_count'=>count($events),'request_count'=>0,'route_counts'=>[],'authority_counts'=>[],
        'request_route_counts'=>[],'request_authority_counts'=>[],'request_authority_stats'=>[],
        'operation_counts'=>[],'outcome_counts'=>[],'status_counts'=>[],
        'error_counts'=>[],'storage_failures'=>0,'unexpected_fallback_count'=>0,'parallel_writer_evidence_count'=>0,
        'expected_protection_count'=>0,'unexpected_failure_count'=>0,
        'latency'=>[],'latest_event_at'=>null];
    $latencies = [];
    foreach ($events as $event) {
        $family = (string)($event['route_family'] ?? 'UNKNOWN');
        $authority = (string)($event['authority_branch'] ?? 'UNCLASSIFIED');
        $summary['route_counts'][$family] = ($summary['route_counts'][$family] ?? 0) + 1;
        $summary['authority_counts'][$authority] = ($summary['authority_counts'][$authority] ?? 0) + 1;
        if (($event['event_type'] ?? '') === 'REQUEST_OUTCOME') {
            $summary['request_count']++;
            $summary['request_route_counts'][$family] = ($summary['request_route_counts'][$family] ?? 0) + 1;
            $summary['request_authority_counts'][$authority] = ($summary['request_authority_counts'][$authority] ?? 0) + 1;
            $summary['request_authority_stats'][$authority] ??= ['requests'=>0,'unexpected_failures'=>0,'expected_protections'=>0];
            $summary['request_authority_stats'][$authority]['requests']++;
            $operation = (string)($event['operation'] ?? 'UNKNOWN');
            $outcome = (string)($event['outcome'] ?? 'unknown');
            $summary['operation_counts'][$operation] = ($summary['operation_counts'][$operation] ?? 0) + 1;
            $summary['outcome_counts'][$outcome] = ($summary['outcome_counts'][$outcome] ?? 0) + 1;
            if ($outcome === 'expected_protection') {
                $summary['expected_protection_count']++;
                $summary['request_authority_stats'][$authority]['expected_protections']++;
            } elseif ($outcome === 'failure') {
                $summary['unexpected_failure_count']++;
                $summary['request_authority_stats'][$authority]['unexpected_failures']++;
            }
        }
        if (isset($event['http_status'])) {
            $status = (string)$event['http_status'];
            $summary['status_counts'][$status] = ($summary['status_counts'][$status] ?? 0) + 1;
        }
        $error = (string)($event['error_identity'] ?? '');
        if ($error !== '') $summary['error_counts'][$error] = ($summary['error_counts'][$error] ?? 0) + 1;
        if (($event['event_type'] ?? '') === 'PRIVATE_STORAGE' && ($event['outcome'] ?? '') === 'failure') $summary['storage_failures']++;
        if (($event['unexpected_fallback'] ?? false) === true) $summary['unexpected_fallback_count']++;
        if (($event['parallel_writer_evidence'] ?? false) === true) $summary['parallel_writer_evidence_count']++;
        if (isset($event['duration_ms']) && is_numeric($event['duration_ms'])) $latencies[$family][] = (int)$event['duration_ms'];
        $summary['latest_event_at'] = $event['timestamp'] ?? $summary['latest_event_at'];
    }
    foreach ($latencies as $family => $values) {
        sort($values, SORT_NUMERIC); $count = count($values);
        $summary['latency'][$family] = ['count'=>$count,'min_ms'=>$values[0],'max_ms'=>$values[$count-1],
            'avg_ms'=>round(array_sum($values)/$count, 2),'p95_ms'=>$values[(int)ceil($count*.95)-1]];
    }
    $summary['unexpected_error_event_count'] = $summary['unexpected_failure_count'] + $summary['storage_failures']
        + $summary['unexpected_fallback_count'] + $summary['parallel_writer_evidence_count'];
    foreach (['route_counts','authority_counts','request_route_counts','request_authority_counts','request_authority_stats','operation_counts',
        'outcome_counts','status_counts','error_counts'] as $key) ksort($summary[$key]);
    return $summary;
}

function clinical_m6_observability_self_health(bool $emitHeartbeat = false): array
{
    $root = clinical_m6_observability_root();
    if ($root === null) return ['healthy'=>false,'error'=>'MONITORING_VISIBILITY_LOSS',
        'cause'=>'MONITORING_ROOT_REQUIRED','visibility_loss'=>true,'stale'=>true];
    $writable = is_dir($root) && !is_link($root) && is_writable($root);
    if ($emitHeartbeat && $writable) clinical_m6_observability_emit('monitoring_heartbeat', ['outcome'=>'success']);
    try {
        $events = clinical_m6_observability_read_events();
        $latest = $events === [] ? null : ($events[array_key_last($events)]['timestamp'] ?? null);
        $latestHeartbeat = null;
        foreach ($events as $event) {
            if (($event['event_type'] ?? '') === 'MONITORING_HEARTBEAT') $latestHeartbeat = $event['timestamp'] ?? $latestHeartbeat;
        }
        $latestEpoch = is_string($latest) ? strtotime($latest) : false;
        $staleSecondsRaw = getenv('MXMED_CLINICAL_M6_MONITORING_STALE_SECONDS');
        $staleSeconds = is_string($staleSecondsRaw) && preg_match('/^[0-9]+$/D', $staleSecondsRaw) ? max(30, (int)$staleSecondsRaw) : 300;
        return ['healthy'=>$writable,'sink_writable'=>$writable,'sink_readable'=>true,'aggregation_parseable'=>true,
            'latest_event_at'=>$latest,'latest_heartbeat_at'=>$latestHeartbeat,
            'stale'=>$latestEpoch === false || time()-$latestEpoch > $staleSeconds,
            'visibility_loss'=>!$writable,'feature_gate_source_readable'=>true,
            'write_window_source_readable'=>clinical_m6_observability_write_window_state()['healthy']];
    } catch (Throwable $e) {
        return ['healthy'=>false,'sink_writable'=>$writable,'sink_readable'=>false,'aggregation_parseable'=>false,
            'error'=>'MONITORING_VISIBILITY_LOSS','cause'=>clinical_m6_observability_error_identity($e->getMessage()),
            'visibility_loss'=>true,'stale'=>true];
    }
}
