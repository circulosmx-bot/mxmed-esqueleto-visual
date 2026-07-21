<?php
declare(strict_types=1);

require_once __DIR__ . '/../_lib/db.php';

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

function catalog_json_response(array $payload, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function catalog_resolve_segments(): array
{
    $uriPath = (string)(parse_url((string)($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?? '/');
    $scriptName = (string)($_SERVER['SCRIPT_NAME'] ?? '');
    $relative = '';

    $marker = '/api/catalog/index.php';
    $pos = strpos($uriPath, $marker);
    if ($pos !== false) {
        $relative = substr($uriPath, $pos + strlen($marker));
    } elseif ($scriptName !== '' && strpos($uriPath, $scriptName) === 0) {
        $relative = substr($uriPath, strlen($scriptName));
    } else {
        $catalogMarker = '/api/catalog/';
        $posCatalog = strpos($uriPath, $catalogMarker);
        if ($posCatalog !== false) {
            $relative = substr($uriPath, $posCatalog + strlen($catalogMarker));
        }
    }

    $relative = trim((string)$relative, '/');
    if ($relative === '') {
        return [];
    }
    $segments = explode('/', $relative);
    if (!empty($segments) && $segments[0] === 'index.php') {
        array_shift($segments);
    }
    return array_values(array_filter($segments, static function ($value) {
        return (string)$value !== '';
    }));
}

function catalog_table_is_missing(PDOException $exception): bool
{
    $sqlState = (string)($exception->errorInfo[0] ?? $exception->getCode());
    $driverCode = (int)($exception->errorInfo[1] ?? 0);
    return $sqlState === '42S02' || $driverCode === 1146;
}

$method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
if ($method !== 'GET') {
    catalog_json_response([
        'ok' => false,
        'error' => 'method_not_allowed',
        'message' => 'GET required',
    ], 405);
}

$segments = catalog_resolve_segments();
$resource = (string)($segments[0] ?? '');
$cp = (string)($segments[1] ?? ($_GET['cp'] ?? ''));
$cp = preg_replace('/\D/', '', $cp);

if ($resource !== 'cp') {
    catalog_json_response([
        'ok' => false,
        'error' => 'not_found',
        'message' => 'route not found',
    ], 404);
}

if (strlen((string)$cp) !== 5) {
    catalog_json_response([
        'ok' => false,
        'error' => 'invalid_cp',
        'message' => 'cp must contain 5 digits',
    ], 400);
}

try {
    $pdo = mxmed_pdo();

    $stmt = $pdo->prepare(
        'SELECT cp, colonia, municipio, estado
           FROM catalog_cp_colonias
          WHERE cp = :cp
            AND is_active = 1
          ORDER BY colonia ASC'
    );
    $stmt->execute([':cp' => $cp]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($rows)) {
        catalog_json_response([
            'ok' => false,
            'error' => 'not_found',
            'message' => 'cp not found',
            'cp' => $cp,
            'estado' => '',
            'municipio' => '',
            'colonias' => [],
        ], 404);
    }

    $colonias = [];
    foreach ($rows as $row) {
        $name = trim((string)($row['colonia'] ?? ''));
        if ($name !== '') {
            $colonias[] = $name;
        }
    }
    $colonias = array_values(array_unique($colonias));

    catalog_json_response([
        'ok' => true,
        'cp' => $cp,
        'estado' => trim((string)($rows[0]['estado'] ?? '')),
        'municipio' => trim((string)($rows[0]['municipio'] ?? '')),
        'colonias' => $colonias,
    ]);
} catch (PDOException $e) {
    if (catalog_table_is_missing($e)) {
        catalog_json_response([
            'ok' => false,
            'error' => 'catalog_not_initialized',
            'message' => 'catalog is not initialized',
        ], 503);
    }
    catalog_json_response([
        'ok' => false,
        'error' => 'internal_error',
        'message' => 'internal error',
    ], 500);
} catch (Throwable $e) {
    catalog_json_response([
        'ok' => false,
        'error' => 'internal_error',
        'message' => 'internal error',
    ], 500);
}
