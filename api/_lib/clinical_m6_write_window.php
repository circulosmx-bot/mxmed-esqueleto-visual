<?php
declare(strict_types=1);

final class ClinicalM6WriteWindowBlockedException extends RuntimeException
{
    public function __construct() { parent::__construct('M6_WRITE_WINDOW_BLOCKED'); }
    public function httpStatus(): int { return 503; }
}

final class ClinicalM6WriteWindowConfigException extends RuntimeException
{
    public function __construct() { parent::__construct('M6_WRITE_WINDOW_CONFIG_INVALID'); }
}

function clinical_m6_write_window_mode(): string
{
    $raw = getenv('MXMED_CLINICAL_WRITE_WINDOW_CONTROL');
    $mode = $raw === false ? '' : strtoupper(trim((string)$raw));
    if ($mode === '' || $mode === 'OPEN') return 'OPEN';
    if ($mode === 'FILE') return 'FILE';
    throw new ClinicalM6WriteWindowConfigException();
}

function clinical_m6_write_window_path(): string
{
    $raw = getenv('MXMED_CLINICAL_WRITE_WINDOW_STATE_PATH');
    $path = $raw === false ? '' : trim((string)$raw);
    if ($path === '' || str_contains($path, "\0")) throw new ClinicalM6WriteWindowConfigException();
    return $path;
}

/** @return array{version:int,state:string,generation:int,updated_at:string} */
function clinical_m6_write_window_validate_state(mixed $decoded): array
{
    if (!is_array($decoded)
        || ($decoded['version'] ?? null) !== 2
        || !in_array($decoded['state'] ?? null, ['OPEN', 'BLOCK_WRITES'], true)
        || !is_int($decoded['generation'] ?? null)
        || $decoded['generation'] < 0
        || !is_string($decoded['updated_at'] ?? null)
        || trim($decoded['updated_at']) === '') {
        throw new ClinicalM6WriteWindowConfigException();
    }
    return $decoded;
}

function clinical_m6_write_window_lease_dir(): string
{
    return clinical_m6_write_window_path() . '.leases';
}

function clinical_m6_write_window_assert_lease_authority(): string
{
    $dir = clinical_m6_write_window_lease_dir();
    if (!is_dir($dir) || is_link($dir) || !is_readable($dir) || !is_writable($dir)) {
        throw new ClinicalM6WriteWindowConfigException();
    }
    return $dir;
}

/** State lock must already be held. Returns the live lease count and reaps unlocked artifacts. */
function clinical_m6_write_window_reconcile_leases_locked(): int
{
    $dir = clinical_m6_write_window_assert_lease_authority();
    $names = scandir($dir);
    if (!is_array($names)) throw new ClinicalM6WriteWindowConfigException();
    $live = 0;
    foreach ($names as $name) {
        if (!preg_match('/^writer-[a-f0-9]{32}\.lease$/', $name)) continue;
        $path = $dir . DIRECTORY_SEPARATOR . $name;
        $lease = @fopen($path, 'r+');
        if (!is_resource($lease)) throw new ClinicalM6WriteWindowConfigException();
        if (!flock($lease, LOCK_EX | LOCK_NB)) {
            $live++;
            fclose($lease);
            continue;
        }
        if (!@unlink($path)) {
            flock($lease, LOCK_UN);
            fclose($lease);
            throw new ClinicalM6WriteWindowConfigException();
        }
        $GLOBALS['clinical_m6_write_window_stale_recovered'] = (int)($GLOBALS['clinical_m6_write_window_stale_recovered'] ?? 0) + 1;
        flock($lease, LOCK_UN);
        fclose($lease);
    }
    return $live;
}

/** @return resource */
function clinical_m6_write_window_open_locked(int $lock)
{
    if (clinical_m6_write_window_mode() !== 'FILE') throw new ClinicalM6WriteWindowConfigException();
    $handle = @fopen(clinical_m6_write_window_path(), 'r+');
    if (!is_resource($handle) || !flock($handle, $lock)) {
        if (is_resource($handle)) fclose($handle);
        throw new ClinicalM6WriteWindowConfigException();
    }
    return $handle;
}

/** @param resource $handle */
function clinical_m6_write_window_read_locked($handle): array
{
    rewind($handle);
    $raw = stream_get_contents($handle);
    if (!is_string($raw) || trim($raw) === '') throw new ClinicalM6WriteWindowConfigException();
    return clinical_m6_write_window_validate_state(json_decode($raw, true));
}

/** @param resource $handle */
function clinical_m6_write_window_write_locked($handle, array $state): void
{
    $state = clinical_m6_write_window_validate_state($state);
    $json = json_encode($state, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    if (!is_string($json) || !rewind($handle) || !ftruncate($handle, 0)
        || fwrite($handle, $json . "\n") === false || !fflush($handle)) {
        throw new ClinicalM6WriteWindowConfigException();
    }
}

function clinical_m6_write_window_status(): array
{
    if (clinical_m6_write_window_mode() === 'OPEN') {
        return ['version'=>2,'state'=>'OPEN','active_writers'=>0,'live_writer_leases'=>0,'generation'=>0,
            'updated_at'=>'repository-default','authority'=>'repository_default'];
    }
    $handle = clinical_m6_write_window_open_locked(LOCK_EX);
    try {
        $state = clinical_m6_write_window_read_locked($handle);
        $state['active_writers'] = clinical_m6_write_window_reconcile_leases_locked();
        $state['live_writer_leases'] = $state['active_writers'];
        $state['stale_leases_recovered'] = (int)($GLOBALS['clinical_m6_write_window_stale_recovered'] ?? 0);
    }
    finally { flock($handle, LOCK_UN); fclose($handle); }
    $state['authority'] = 'locked_file_v2_crash_safe_leases';
    $state['shared_lock_namespace'] = hash('sha256', clinical_m6_write_window_lease_dir());
    $stateMode = @fileperms(clinical_m6_write_window_path());
    $leaseMode = @fileperms(clinical_m6_write_window_lease_dir());
    $state['state_authority_permissions_valid'] = is_int($stateMode) && (($stateMode & 0007) === 0);
    $state['lease_authority_permissions_valid'] = is_int($leaseMode) && (($leaseMode & 0007) === 0);
    return $state;
}

function clinical_m6_write_window_blocks_writes(): bool
{
    try { return clinical_m6_write_window_status()['state'] !== 'OPEN'; }
    catch (ClinicalM6WriteWindowConfigException) { return true; }
}

function clinical_m6_write_window_route_is_clinical_writer(string $method, array $segments): bool
{
    $method = strtoupper(trim($method));
    if (in_array($method, ['GET', 'HEAD', 'OPTIONS'], true)) return false;
    if (($segments[0] ?? '') === 'note-capture-tokens') {
        if ($method === 'POST' && count($segments) === 1) return false;
        if ($method === 'DELETE' && count($segments) === 2) return false;
        if (($segments[2] ?? '') !== 'upload') return false;
    }
    return true;
}

function clinical_m6_write_window_admit(): void
{
    if (is_resource($GLOBALS['clinical_m6_write_window_lease_handle'] ?? null)) return;
    if (clinical_m6_write_window_mode() === 'OPEN') {
        $GLOBALS['clinical_m6_write_window_admitted'] = 'default-open';
        return;
    }
    $handle = clinical_m6_write_window_open_locked(LOCK_EX);
    try {
        $state = clinical_m6_write_window_read_locked($handle);
        if ($state['state'] !== 'OPEN') throw new ClinicalM6WriteWindowBlockedException();
        $dir = clinical_m6_write_window_assert_lease_authority();
        $leaseId = bin2hex(random_bytes(16));
        $leasePath = $dir . DIRECTORY_SEPARATOR . 'writer-' . $leaseId . '.lease';
        $lease = @fopen($leasePath, 'x+');
        if (!is_resource($lease) || !flock($lease, LOCK_EX | LOCK_NB)) {
            if (is_resource($lease)) fclose($lease);
            @unlink($leasePath);
            throw new ClinicalM6WriteWindowConfigException();
        }
        @chmod($leasePath, 0660);
        $metadata = json_encode(['lease_id'=>$leaseId,'pid'=>getmypid(),'admitted_at'=>gmdate('c')]);
        if (!is_string($metadata) || fwrite($lease, $metadata . "\n") === false || !fflush($lease)) {
            flock($lease, LOCK_UN); fclose($lease); @unlink($leasePath);
            throw new ClinicalM6WriteWindowConfigException();
        }
        $GLOBALS['clinical_m6_write_window_admitted'] = true;
        $GLOBALS['clinical_m6_write_window_lease_handle'] = $lease;
        $GLOBALS['clinical_m6_write_window_lease_path'] = $leasePath;
        register_shutdown_function('clinical_m6_write_window_release');
    } finally {
        flock($handle, LOCK_UN);
        fclose($handle);
    }
}

function clinical_m6_write_window_release(): void
{
    $lease = $GLOBALS['clinical_m6_write_window_lease_handle'] ?? null;
    $leasePath = (string)($GLOBALS['clinical_m6_write_window_lease_path'] ?? '');
    if (!is_resource($lease)) return;
    $GLOBALS['clinical_m6_write_window_admitted'] = false;
    $GLOBALS['clinical_m6_write_window_lease_handle'] = null;
    $GLOBALS['clinical_m6_write_window_lease_path'] = null;
    try {
        $handle = clinical_m6_write_window_open_locked(LOCK_EX);
        try {
            clinical_m6_write_window_read_locked($handle);
            flock($lease, LOCK_UN);
            fclose($lease);
            if ($leasePath === '' || !@unlink($leasePath)) throw new ClinicalM6WriteWindowConfigException();
        } finally { flock($handle, LOCK_UN); fclose($handle); }
    } catch (Throwable $e) {
        error_log('M6_WRITE_WINDOW_RELEASE_FAILED:' . $e->getMessage());
    }
}

function clinical_m6_write_window_set_state(string $next): array
{
    $next = strtoupper(trim($next));
    if (!in_array($next, ['OPEN', 'BLOCK_WRITES'], true)) throw new ClinicalM6WriteWindowConfigException();
    $handle = clinical_m6_write_window_open_locked(LOCK_EX);
    try {
        $state = clinical_m6_write_window_read_locked($handle);
        if ($state['state'] !== $next) $state['generation']++;
        $state['state'] = $next;
        $state['updated_at'] = gmdate('c');
        clinical_m6_write_window_write_locked($handle, $state);
        $live = clinical_m6_write_window_reconcile_leases_locked();
    } finally { flock($handle, LOCK_UN); fclose($handle); }
    $state['active_writers'] = $live;
    $state['live_writer_leases'] = $live;
    $state['authority'] = 'locked_file_v2_crash_safe_leases';
    $state['stale_leases_recovered'] = (int)($GLOBALS['clinical_m6_write_window_stale_recovered'] ?? 0);
    if (function_exists('clinical_m6_observability_emit')) {
        clinical_m6_observability_emit('write_window_state_change', ['outcome'=>'success',
            'state'=>$next,'live_writer_leases'=>$live,'stale_leases_recovered'=>$state['stale_leases_recovered']]);
    }
    return $state;
}

function clinical_m6_write_window_initialize_file(string $path): array
{
    if ($path === '' || str_contains($path, "\0")) throw new ClinicalM6WriteWindowConfigException();
    $dir = dirname($path);
    if (!is_dir($dir) || !is_writable($dir)) throw new ClinicalM6WriteWindowConfigException();
    $leaseDir = $path . '.leases';
    if (file_exists($path) || file_exists($leaseDir)) throw new ClinicalM6WriteWindowConfigException();
    if (!@mkdir($leaseDir, 0770) || !@chmod($leaseDir, 0770)) throw new ClinicalM6WriteWindowConfigException();
    $handle = @fopen($path, 'x+');
    if (!is_resource($handle) || !flock($handle, LOCK_EX)) throw new ClinicalM6WriteWindowConfigException();
    $state = ['version'=>2,'state'=>'OPEN','generation'=>0,'updated_at'=>gmdate('c')];
    try { clinical_m6_write_window_write_locked($handle, $state); }
    finally { flock($handle, LOCK_UN); fclose($handle); }
    @chmod($path, 0660);
    $state['active_writers'] = 0;
    $state['live_writer_leases'] = 0;
    return $state;
}

function clinical_m6_write_window_assert_bridge_open(): void
{
    if (clinical_m6_write_window_blocks_writes()) throw new ClinicalM6WriteWindowBlockedException();
}
