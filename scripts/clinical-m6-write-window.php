<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/api/_lib/clinical_m6_write_window.php';

if (PHP_SAPI !== 'cli') { fwrite(STDERR, "CLI only\n"); exit(2); }
$command = strtolower(trim((string)($argv[1] ?? 'status')));
$path = trim((string)($argv[2] ?? getenv('MXMED_CLINICAL_WRITE_WINDOW_STATE_PATH') ?: ''));
try {
    if ($command === 'init') {
        $state = clinical_m6_write_window_initialize_file($path);
    } else {
        putenv('MXMED_CLINICAL_WRITE_WINDOW_CONTROL=FILE');
        if ($path !== '') putenv('MXMED_CLINICAL_WRITE_WINDOW_STATE_PATH=' . $path);
        $state = match ($command) {
            'status' => clinical_m6_write_window_status(),
            'block' => clinical_m6_write_window_set_state('BLOCK_WRITES'),
            'open' => clinical_m6_write_window_set_state('OPEN'),
            default => throw new InvalidArgumentException('usage: init|status|block|open [state-path]'),
        };
    }
    $state['write_window_active'] = (($state['state'] ?? '') === 'BLOCK_WRITES');
    $state['clinical_writes_quiesced'] = $state['write_window_active'] && (($state['active_writers'] ?? -1) === 0);
    echo json_encode($state, JSON_UNESCAPED_SLASHES) . "\n";
} catch (Throwable $e) {
    fwrite(STDERR, $e->getMessage() . "\n");
    exit(1);
}
