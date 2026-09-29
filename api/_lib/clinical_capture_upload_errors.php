<?php
declare(strict_types=1);

require_once __DIR__ . '/clinical_private_binary_storage.php';

/** Capture transport diagnostics only; the existing writer remains the file authority. */
function clinical_capture_upload_failure(?array $file, int $contentLength, string $postMaxSize): ?array
{
    $error = $file === null ? null : (int)($file['error'] ?? UPLOAD_ERR_NO_FILE);
    $postLimit = ini_parse_quantity($postMaxSize);
    $postExceeded = $file === null && $postLimit > 0 && $contentLength > $postLimit;
    $tooLarge = $postExceeded || in_array($error, [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)
        || ($error === UPLOAD_ERR_OK && (int)($file['size'] ?? 0) > ClinicalPrivateBinaryStorage::MAX_BYTES);
    if ($tooLarge) {
        return ['code' => 'UPLOAD_TOO_LARGE', 'message' => 'El archivo supera el límite permitido de 25 MB.',
            'upload_error' => $error, 'post_max_size_exceeded' => $postExceeded];
    }
    if ($error === UPLOAD_ERR_OK) return null;
    [$code, $message] = match ($error) {
        UPLOAD_ERR_PARTIAL => ['UPLOAD_PARTIAL', 'La carga del archivo quedó incompleta. Intenta nuevamente.'],
        null, UPLOAD_ERR_NO_FILE => ['UPLOAD_NO_FILE', 'Selecciona un archivo para enviar.'],
        UPLOAD_ERR_NO_TMP_DIR => ['UPLOAD_NO_TMP_DIR', 'El servidor no tiene disponible la carpeta temporal de carga.'],
        UPLOAD_ERR_CANT_WRITE => ['UPLOAD_CANT_WRITE', 'El servidor no pudo guardar el archivo recibido.'],
        UPLOAD_ERR_EXTENSION => ['UPLOAD_EXTENSION_BLOCKED', 'Una extensión del servidor bloqueó la carga del archivo.'],
        default => ['UPLOAD_FAILED', 'No se pudo recibir el archivo. Intenta nuevamente.'],
    };
    return ['code' => $code, 'message' => $message, 'upload_error' => $error, 'post_max_size_exceeded' => false];
}
