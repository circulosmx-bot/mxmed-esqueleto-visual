<?php
declare(strict_types=1);

/** The HTML/print page and PDF use the same already-authorized order projection and template. */
function clinical_portable_pdf_html(array $order): string
{
    $error = '';
    if (!defined('MXMED_PORTABLE_ORDER_TEMPLATE_ALLOWED')) {
        define('MXMED_PORTABLE_ORDER_TEMPLATE_ALLOWED', true);
    }
    $bufferLevel = ob_get_level();
    ob_start();
    try {
        require __DIR__ . '/../../modules/clinical/ui/portable-order-template.php';
        $html = ob_get_clean();
        if (!is_string($html) || $html === '') throw new RuntimeException('PORTABLE_PDF_RENDER_FAILED');
        return $html;
    } catch (Throwable $error) {
        while (ob_get_level() > $bufferLevel) ob_end_clean();
        throw $error;
    }
}

function clinical_portable_pdf_filename(array $order): string
{
    $name = trim((string)($order['patient']['name'] ?? ''));
    if (function_exists('transliterator_transliterate')) {
        $name = transliterator_transliterate('Any-Latin; Latin-ASCII', $name);
    }
    $name = trim((string)preg_replace('/[^A-Za-z0-9]+/', '-', $name), '-');
    $name = substr($name, 0, 56);
    if ($name === '') $name = 'Paciente';
    $reference = (string)($order['display_reference'] ?? '');
    if (preg_match('/^[A-F0-9]{12}$/D', $reference) !== 1) throw new RuntimeException('PORTABLE_PDF_RENDER_FAILED');
    return 'Orden-estudios-' . $name . '-' . $reference . '.pdf';
}

function clinical_portable_pdf_chrome(): string
{
    $configured = trim((string)(getenv('MXMED_PORTABLE_PDF_CHROME') ?: ''));
    $candidates = $configured !== '' ? [$configured]
        : ['/usr/bin/chromium', '/usr/bin/google-chrome',
            '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome'];
    foreach ($candidates as $candidate) {
        if (is_file($candidate) && is_executable($candidate)) return $candidate;
    }
    throw new RuntimeException('PORTABLE_PDF_RENDER_UNAVAILABLE');
}

function clinical_portable_pdf_remove_private_tree(string $directory): void
{
    if (!is_dir($directory)) return;
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($iterator as $entry) {
        if ($entry->isDir() && !$entry->isLink()) rmdir($entry->getPathname());
        else unlink($entry->getPathname());
    }
    rmdir($directory);
}

/** Generate fully in private temporary storage; never emit bytes until validation succeeds. */
function clinical_portable_pdf_generate(array $order): string
{
    if (count($order['studies'] ?? []) < 1 || count($order['studies']) > 100) {
        throw new RuntimeException('PORTABLE_PDF_RENDER_FAILED');
    }
    $html = clinical_portable_pdf_html($order);
    if (strlen($html) > 512 * 1024) throw new RuntimeException('PORTABLE_PDF_RENDER_FAILED');
    $tempRoot = realpath(sys_get_temp_dir());
    $documentRoot = realpath((string)($_SERVER['DOCUMENT_ROOT'] ?? ''));
    if ($tempRoot === false || ($documentRoot !== false
        && ($tempRoot === $documentRoot || str_starts_with($tempRoot, $documentRoot . DIRECTORY_SEPARATOR)))) {
        throw new RuntimeException('PORTABLE_PDF_RENDER_UNAVAILABLE');
    }
    $directory = $tempRoot . '/mxmed-portable-pdf-' . bin2hex(random_bytes(16));
    if (!mkdir($directory, 0700)) {
        throw new RuntimeException('PORTABLE_PDF_RENDER_UNAVAILABLE');
    }
    try {
        if (!mkdir($directory . '/profile', 0700)) {
            throw new RuntimeException('PORTABLE_PDF_RENDER_UNAVAILABLE');
        }
        $htmlPath = $directory . '/order.html';
        $pdfPath = $directory . '/order.pdf';
        if (file_put_contents($htmlPath, $html, LOCK_EX) !== strlen($html) || !chmod($htmlPath, 0600)) {
            throw new RuntimeException('PORTABLE_PDF_RENDER_FAILED');
        }
        $fileUrl = 'file://' . implode('/', array_map('rawurlencode', explode('/', $htmlPath)));
        $command = [
            clinical_portable_pdf_chrome(),
            '--headless=new', '--disable-gpu', '--disable-extensions', '--disable-dev-shm-usage',
            '--disable-background-networking', '--disable-background-mode',
            '--no-service-autorun', '--no-first-run', '--no-default-browser-check',
            '--no-pdf-header-footer', '--user-data-dir=' . $directory . '/profile',
            '--print-to-pdf=' . $pdfPath, $fileUrl,
        ];
        $process = proc_open($command, [
                0 => ['file', '/dev/null', 'r'],
                1 => ['file', '/dev/null', 'w'],
                2 => ['file', '/dev/null', 'w'],
            ], $pipes, $directory, [
                'HOME' => $directory, 'TMPDIR' => $directory,
                'LANG' => 'C.UTF-8', 'PATH' => '/usr/bin:/bin',
            ]);
        if (!is_resource($process)) throw new RuntimeException('PORTABLE_PDF_RENDER_UNAVAILABLE');
        $deadline = microtime(true) + 25;
        $bytes = null;
        $lastSize = 0;
        do {
            clearstatcache(true, $pdfPath);
            $size = is_file($pdfPath) ? filesize($pdfPath) : false;
            if (is_int($size) && $size > 1000 && $size <= 8 * 1024 * 1024 && $size === $lastSize) {
                $candidate = file_get_contents($pdfPath);
                if (is_string($candidate) && str_starts_with($candidate, '%PDF-')
                    && str_contains(substr($candidate, -1024), '%%EOF')) {
                    $bytes = $candidate;
                    break;
                }
            }
            if (is_int($size) && $size > 8 * 1024 * 1024) break;
            $lastSize = is_int($size) ? $size : 0;
            $status = proc_get_status($process);
            if (!is_array($status) || (!$status['running'] && $size === false)) break;
            usleep(50000);
        } while (microtime(true) <= $deadline);
        $status = proc_get_status($process);
        if (is_array($status) && $status['running']) {
            proc_terminate($process, 15);
            usleep(100000);
            $status = proc_get_status($process);
            if (is_array($status) && $status['running']) proc_terminate($process, 9);
        }
        proc_close($process);
        if ($bytes === null || (new finfo(FILEINFO_MIME_TYPE))->buffer($bytes) !== 'application/pdf') {
            throw new RuntimeException('PORTABLE_PDF_RENDER_FAILED');
        }
        return $bytes;
    } finally {
        clinical_portable_pdf_remove_private_tree($directory);
    }
}
