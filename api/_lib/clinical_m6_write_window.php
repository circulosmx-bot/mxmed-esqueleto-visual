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

/** @return array{version:int,state:string,active_writers:int,generation:int,updated_at:string} */
function clinical_m6_write_window_validate_state(mixed $decoded): array
{
    if (!is_array($decoded)
        || ($decoded['version'] ?? null) !== 1
        || !in_array($decoded['state'] ?? null, ['OPEN', 'BLOCK_WRITES'], true)
        || !is_int($decoded['active_writers'] ?? null)
        || $decoded['active_writers'] < 0
        || !is_int($decoded['generation'] ?? null)
        || $decoded['generation'] < 0
        || !is_string($decoded['updated_at'] ?? null)
        || trim($decoded['updated_at']) === '') {
        throw new ClinicalM6WriteWindowConfigException();
    }
    return $decoded;
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
        return ['version'=>1,'state'=>'OPEN','active_writers'=>0,'generation'=>0,
            'updated_at'=>'repository-default','authority'=>'repository_default'];
    }
    $handle = clinical_m6_write_window_open_locked(LOCK_SH);
    try { $state = clinical_m6_write_window_read_locked($handle); }
    finally { flock($handle, LOCK_UN); fclose($handle); }
    $state['authority'] = 'locked_file_v1';
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
    if (($GLOBALS['clinical_m6_write_window_admitted'] ?? false) === true) return;
    if (clinical_m6_write_window_mode() === 'OPEN') {
        $GLOBALS['clinical_m6_write_window_admitted'] = 'default-open';
        return;
    }
    $handle = clinical_m6_write_window_open_locked(LOCK_EX);
    try {
        $state = clinical_m6_write_window_read_locked($handle);
        if ($state['state'] !== 'OPEN') throw new ClinicalM6WriteWindowBlockedException();
        $state['active_writers']++;
        $state['updated_at'] = gmdate('c');
        clinical_m6_write_window_write_locked($handle, $state);
        $GLOBALS['clinical_m6_write_window_admitted'] = true;
        register_shutdown_function('clinical_m6_write_window_release');
    } finally {
        flock($handle, LOCK_UN);
        fclose($handle);
    }
}

function clinical_m6_write_window_release(): void
{
    if (($GLOBALS['clinical_m6_write_window_admitted'] ?? false) !== true) return;
    $GLOBALS['clinical_m6_write_window_admitted'] = false;
    try {
        $handle = clinical_m6_write_window_open_locked(LOCK_EX);
        try {
            $state = clinical_m6_write_window_read_locked($handle);
            if ($state['active_writers'] < 1) throw new ClinicalM6WriteWindowConfigException();
            $state['active_writers']--;
            $state['updated_at'] = gmdate('c');
            clinical_m6_write_window_write_locked($handle, $state);
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
    } finally { flock($handle, LOCK_UN); fclose($handle); }
    $state['authority'] = 'locked_file_v1';
    return $state;
}

function clinical_m6_write_window_initialize_file(string $path): array
{
    if ($path === '' || str_contains($path, "\0")) throw new ClinicalM6WriteWindowConfigException();
    $dir = dirname($path);
    if (!is_dir($dir) || !is_writable($dir)) throw new ClinicalM6WriteWindowConfigException();
    $handle = @fopen($path, 'x+');
    if (!is_resource($handle) || !flock($handle, LOCK_EX)) throw new ClinicalM6WriteWindowConfigException();
    $state = ['version'=>1,'state'=>'OPEN','active_writers'=>0,'generation'=>0,'updated_at'=>gmdate('c')];
    try { clinical_m6_write_window_write_locked($handle, $state); }
    finally { flock($handle, LOCK_UN); fclose($handle); }
    @chmod($path, 0660);
    return $state;
}

function clinical_m6_write_window_assert_bridge_open(): void
{
    if (clinical_m6_write_window_blocks_writes()) throw new ClinicalM6WriteWindowBlockedException();
}
