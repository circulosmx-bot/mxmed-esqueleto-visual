<?php
declare(strict_types=1);

function mxmed_load_db_config(): array {
    $path = __DIR__ . '/../mxmed-db.config.php';
    if (is_file($path)) {
        $cfg = require $path;
        if (is_array($cfg)) return $cfg;
    }

    $host = getenv('MXMED_DB_HOST') ?: '127.0.0.1';
    $port = (int)(getenv('MXMED_DB_PORT') ?: 3306);
    $dbname = getenv('MXMED_DB_NAME') ?: '';
    $user = getenv('MXMED_DB_USER') ?: '';
    $pass = getenv('MXMED_DB_PASS') ?: '';
    $charset = getenv('MXMED_DB_CHARSET') ?: 'utf8mb4';
    $collation = getenv('MXMED_DB_COLLATION') ?: 'utf8mb4_unicode_ci';

    return [
        'mysql' => [
            'host' => $host,
            'port' => $port,
            'dbname' => $dbname,
            'user' => $user,
            'pass' => $pass,
            'charset' => $charset,
            'collation' => $collation,
        ],
    ];
}

function mxmed_pdo(): PDO {
    $cfg = mxmed_load_db_config();
    if (empty($cfg['mysql']['dbname']) || empty($cfg['mysql']['user'])) {
        throw new RuntimeException('DB no configurada: crea api/mxmed-db.config.php o variables de entorno MXMED_DB_*');
    }

    $h = $cfg['mysql']['host'];
    $p = (int)$cfg['mysql']['port'];
    $db = $cfg['mysql']['dbname'];
    $u = $cfg['mysql']['user'];
    $pw = $cfg['mysql']['pass'];
    $ch = $cfg['mysql']['charset'] ?? 'utf8mb4';
    $co = $cfg['mysql']['collation'] ?? 'utf8mb4_unicode_ci';

    $dsn = "mysql:host={$h};port={$p};dbname={$db};charset={$ch}";
    $pdo = new PDO($dsn, $u, $pw, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    try { $pdo->exec("SET NAMES {$ch} COLLATE {$co}"); } catch (Throwable $e) { }
    return $pdo;
}

