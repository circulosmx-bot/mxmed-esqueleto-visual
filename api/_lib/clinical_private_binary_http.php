<?php
declare(strict_types=1);

require_once __DIR__ . '/clinical_m6_observability.php';

/** Pure response metadata; only accepted manifest MIME controls the extension. */
function clinical_binary_http_headers(array $binary): array
{
    $extensions = ['application/pdf'=>'pdf','image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'];
    $mime = $binary['mime_type'] ?? '';
    if (!isset($extensions[$mime]) || !is_int($binary['byte_length'] ?? null) || $binary['byte_length'] <= 0) {
        throw new RuntimeException('DOCUMENT_BINARY_INTEGRITY_MISMATCH');
    }
    // A generated filename avoids propagating stored metadata into response headers.
    return [
        'Content-Type' => $mime,
        'X-Content-Type-Options' => 'nosniff',
        'Cache-Control' => 'private, no-store',
        'Content-Length' => (string)$binary['byte_length'],
        'Content-Disposition' => 'inline; filename="document.'.$extensions[$mime].'"',
    ];
}

function clinical_binary_http_error(Throwable $error): array
{
    return match ($error->getMessage()) {
        'DOCUMENT_BINARY_NOT_FOUND' => [404, 'not_found', 'resource not found'],
        'DOCUMENT_BINARY_MISSING' => [503, 'BINARY_UNAVAILABLE', 'binary unavailable'],
        'DOCUMENT_BINARY_INTEGRITY_MISMATCH' => [503, 'BINARY_INTEGRITY_FAILED', 'binary unavailable'],
        'PRIVATE_BINARY_STORAGE_NOT_CONFIGURED' => [503, 'PRIVATE_BINARY_STORAGE_NOT_CONFIGURED', 'binary unavailable'],
        default => [500, 'server_error', 'server error'],
    };
}

/** Takes ownership of the already-verified stream; never reopens a private path. */
function clinical_binary_http_transfer($stream, int $length, callable $emit, callable $disconnected): void
{
    $sent = 0;
    try {
        while ($sent < $length) {
            if ($disconnected()) {
                throw new RuntimeException('BINARY_CLIENT_DISCONNECTED');
            }
            $chunk = fread($stream, min(65536, $length - $sent));
            if ($chunk === false || $chunk === '') {
                throw new RuntimeException('BINARY_STREAM_SHORT');
            }
            $emit($chunk);
            $sent += strlen($chunk);
        }
    } finally {
        if (is_resource($stream)) {
            fclose($stream);
        }
    }
}

/** Headers/body phase is isolated: failures cannot escape to the router JSON catch. */
function clinical_binary_http_send(array $descriptor, array $headers): void
{
    $previous = ignore_user_abort(true);
    try {
        http_response_code(200);
        foreach ($headers as $name => $value) {
            header($name.': '.$value);
        }
        clinical_binary_http_transfer($descriptor['stream'], $descriptor['binary']['byte_length'],
            static function (string $chunk): void { echo $chunk; },
            static fn(): bool => connection_aborted() !== 0);
        clinical_m6_observability_storage('PRIVATE_HTTP_READ', true);
    } catch (Throwable $error) {
        clinical_m6_observability_storage('PRIVATE_HTTP_READ_FAILED', false, $error->getMessage());
        error_log('CLINICAL_BINARY_TRANSFER_FAILED');
    } finally {
        if (is_resource($descriptor['stream'])) {
            fclose($descriptor['stream']);
        }
        ignore_user_abort((bool)$previous);
    }
}
