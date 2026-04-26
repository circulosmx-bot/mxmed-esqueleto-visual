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

function catalog_ensure_table(PDO $pdo): void
{
    $sql = "CREATE TABLE IF NOT EXISTS catalog_cp_colonias (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        cp VARCHAR(5) NOT NULL,
        colonia VARCHAR(190) NOT NULL,
        municipio VARCHAR(190) NOT NULL,
        estado VARCHAR(190) NOT NULL,
        is_active TINYINT(1) NOT NULL DEFAULT 1,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY uq_catalog_cp_colonia (cp, colonia),
        KEY idx_catalog_cp (cp),
        KEY idx_catalog_cp_active (cp, is_active)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    $pdo->exec($sql);
}

function catalog_bootstrap_if_empty(PDO $pdo): void
{
    $count = (int)$pdo->query('SELECT COUNT(*) FROM catalog_cp_colonias')->fetchColumn();
    if ($count > 0) {
        return;
    }

    $seedRows = [];
    $fallbackPath = dirname(__DIR__, 2) . '/assets/data/sepomex-fallback.json';
    if (is_file($fallbackPath)) {
        $decoded = json_decode((string)file_get_contents($fallbackPath), true);
        if (is_array($decoded)) {
            foreach ($decoded as $cp => $row) {
                $cpVal = preg_replace('/\D/', '', (string)$cp);
                if (strlen((string)$cpVal) !== 5 || !is_array($row)) {
                    continue;
                }
                $estado = trim((string)($row['estado'] ?? ''));
                $municipio = trim((string)($row['municipio'] ?? ''));
                $colonias = $row['settlement'] ?? $row['colonias'] ?? [];
                if (!is_array($colonias)) {
                    continue;
                }
                foreach ($colonias as $coloniaRaw) {
                    $colonia = trim((string)$coloniaRaw);
                    if ($colonia === '') {
                        continue;
                    }
                    $seedRows[] = [
                        'cp' => $cpVal,
                        'colonia' => $colonia,
                        'municipio' => $municipio,
                        'estado' => $estado,
                    ];
                }
            }
        }
    }

    if (empty($seedRows)) {
        return;
    }

    $stmt = $pdo->prepare(
        'INSERT INTO catalog_cp_colonias (cp, colonia, municipio, estado, is_active)
         VALUES (:cp, :colonia, :municipio, :estado, 1)
         ON DUPLICATE KEY UPDATE
            municipio = VALUES(municipio),
            estado = VALUES(estado),
            is_active = 1'
    );
    foreach ($seedRows as $seed) {
        $stmt->execute([
            ':cp' => $seed['cp'],
            ':colonia' => $seed['colonia'],
            ':municipio' => $seed['municipio'],
            ':estado' => $seed['estado'],
        ]);
    }
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
    catalog_ensure_table($pdo);
    catalog_bootstrap_if_empty($pdo);

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
} catch (Throwable $e) {
    catalog_json_response([
        'ok' => false,
        'error' => 'db_error',
        'message' => 'database error',
    ], 500);
}
