<?php
declare(strict_types=1);

require_once __DIR__ . '/../_lib/db.php';
require_once __DIR__ . '/../../modules/media/bootstrap.php';

use Media\Repositories\MediaAssetsRepository;

function mediaPublicError(int $status = 404, string $error = 'public_media_not_found'): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=UTF-8');
    header('Cache-Control: no-store');
    echo json_encode([
        'ok' => false,
        'error' => $error,
        'message' => str_replace('_', ' ', $error),
        'data' => null,
        'meta' => (object)[],
    ], JSON_UNESCAPED_SLASHES);
}

$method = strtoupper(trim((string)($_SERVER['REQUEST_METHOD'] ?? 'GET')));
$path = (string)(parse_url((string)($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH) ?? '');
$marker = '/api/media/index.php/public/';
$position = strpos($path, $marker);
$mediaId = $position === false ? '' : rawurldecode(substr($path, $position + strlen($marker)));

if ($method !== 'GET' && $method !== 'HEAD') {
    header('Allow: GET, HEAD');
    mediaPublicError(405, 'method_not_allowed');
    return;
}
if (preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/', $mediaId) !== 1) {
    mediaPublicError();
    return;
}

try {
    $asset = (new MediaAssetsRepository(mxmed_pdo()))->findPublicReady($mediaId);
    if (!is_array($asset)) {
        mediaPublicError();
        return;
    }
    $stored = mxmed_public_media_storage()->openReadStream((string)$asset['storage_key']);
    $hashContext = hash_init('sha256');
    hash_update_stream($hashContext, $stored['stream']);
    $storedChecksum = hash_final($hashContext);
    rewind($stored['stream']);
    if ((int)$stored['bytes'] !== (int)$asset['byte_size']
        || !hash_equals((string)$asset['checksum_sha256'], $storedChecksum)) {
        if (is_resource($stored['stream'])) {
            fclose($stored['stream']);
        }
        mediaPublicError();
        return;
    }

    $etag = '"' . (string)$asset['checksum_sha256'] . '"';
    header('Content-Type: ' . (string)$asset['mime_type']);
    header('Content-Length: ' . (string)$stored['bytes']);
    header('Cache-Control: public, max-age=31536000, immutable');
    header('ETag: ' . $etag);
    header('X-Content-Type-Options: nosniff');
    header('Content-Disposition: inline');
    if (trim((string)($_SERVER['HTTP_IF_NONE_MATCH'] ?? '')) === $etag) {
        fclose($stored['stream']);
        http_response_code(304);
        return;
    }
    if ($method === 'HEAD') {
        fclose($stored['stream']);
        return;
    }
    fpassthru($stored['stream']);
    fclose($stored['stream']);
} catch (Throwable $e) {
    mediaPublicError();
}
