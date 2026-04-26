<?php
declare(strict_types=1);

require_once __DIR__ . '/../api/_lib/db.php';

function usage(): void
{
    $cmd = basename(__FILE__);
    echo "Uso:\n";
    echo "  php tools/{$cmd} [--dump=RUTA_SQL] [--state=ESTADO] [--skip-sepomex-import]\n\n";
    echo "Opciones:\n";
    echo "  --dump=RUTA_SQL           Ruta al dump SQL de SEPOMEX (default: db_backups/sepomex_20251115_114415.sql)\n";
    echo "  --state=ESTADO            Importa solo ese estado (ej. Aguascalientes)\n";
    echo "  --skip-sepomex-import     No intenta cargar el dump en tabla sepomex\n";
    echo "  --help                    Muestra esta ayuda\n";
}

function tableExists(PDO $pdo, string $table): bool
{
    $stmt = $pdo->prepare('SHOW TABLES LIKE :table');
    $stmt->execute([':table' => $table]);
    return (bool)$stmt->fetchColumn();
}

function ensureCatalogTable(PDO $pdo): void
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

function getMysqlClientCommand(array $cfg, string $dumpPath): array
{
    $tmpFile = tempnam(sys_get_temp_dir(), 'mxmed_mysql_');
    if ($tmpFile === false) {
        throw new RuntimeException('No se pudo crear archivo temporal para credenciales MySQL');
    }
    $content = "[client]\n"
        . "host={$cfg['host']}\n"
        . "port={$cfg['port']}\n"
        . "user={$cfg['user']}\n"
        . "password={$cfg['pass']}\n"
        . "default-character-set=utf8mb4\n";
    file_put_contents($tmpFile, $content);
    chmod($tmpFile, 0600);

    $dbName = (string)$cfg['dbname'];
    $cmd = sprintf(
        'mysql --defaults-extra-file=%s %s < %s',
        escapeshellarg($tmpFile),
        escapeshellarg($dbName),
        escapeshellarg($dumpPath)
    );

    return [$cmd, $tmpFile];
}

function importSepomexDumpIfNeeded(PDO $pdo, array $cfg, string $dumpPath, bool $skipImport): void
{
    $hasSepomex = tableExists($pdo, 'sepomex');
    if ($hasSepomex) {
        $count = (int)$pdo->query('SELECT COUNT(*) FROM sepomex')->fetchColumn();
        if ($count > 0) {
            echo "SEPOMEX: tabla existente con {$count} filas, no se recarga dump.\n";
            return;
        }
    }

    if ($skipImport) {
        throw new RuntimeException('No existe tabla sepomex poblada y se indicó --skip-sepomex-import');
    }
    if (!is_file($dumpPath)) {
        throw new RuntimeException("Dump no encontrado: {$dumpPath}");
    }

    echo "SEPOMEX: cargando dump en MySQL desde {$dumpPath} ...\n";
    [$cmd, $tmpFile] = getMysqlClientCommand($cfg, $dumpPath);
    $exitCode = 0;
    passthru($cmd, $exitCode);
    @unlink($tmpFile);

    if ($exitCode !== 0) {
        throw new RuntimeException("Falló la carga del dump SEPOMEX (exit {$exitCode})");
    }

    if (!tableExists($pdo, 'sepomex')) {
        throw new RuntimeException('La tabla sepomex no quedó creada tras importar dump');
    }
    $count = (int)$pdo->query('SELECT COUNT(*) FROM sepomex')->fetchColumn();
    echo "SEPOMEX: importado OK ({$count} filas).\n";
}

function importCatalogFromSepomex(PDO $pdo, ?string $stateFilter): void
{
    $normColonia = "CASE
        WHEN TRIM(COALESCE(d_asenta, '')) REGEXP '[ÃÂ├┬]' THEN CONVERT(BINARY CONVERT(TRIM(COALESCE(d_asenta, '')) USING cp850) USING utf8mb4)
        ELSE TRIM(COALESCE(d_asenta, ''))
    END";
    $normMunicipio = "CASE
        WHEN TRIM(COALESCE(d_mnpio, '')) REGEXP '[ÃÂ├┬]' THEN CONVERT(BINARY CONVERT(TRIM(COALESCE(d_mnpio, '')) USING cp850) USING utf8mb4)
        ELSE TRIM(COALESCE(d_mnpio, ''))
    END";
    $normEstado = "CASE
        WHEN TRIM(COALESCE(d_estado, '')) REGEXP '[ÃÂ├┬]' THEN CONVERT(BINARY CONVERT(TRIM(COALESCE(d_estado, '')) USING cp850) USING utf8mb4)
        ELSE TRIM(COALESCE(d_estado, ''))
    END";

    $where = "WHERE d_codigo REGEXP '^[0-9]{5}$'\n"
        . "  AND {$normColonia} <> ''\n";
    $params = [];
    if ($stateFilter !== null && $stateFilter !== '') {
        $where .= "  AND {$normEstado} = :estado\n";
        $params[':estado'] = $stateFilter;
    }

    $sql = "INSERT INTO catalog_cp_colonias (cp, colonia, municipio, estado, is_active)
            SELECT
              TRIM(d_codigo) AS cp,
              {$normColonia} AS colonia,
              {$normMunicipio} AS municipio,
              {$normEstado} AS estado,
              1 AS is_active
            FROM sepomex
            {$where}
            GROUP BY
              TRIM(d_codigo),
              {$normColonia},
              {$normMunicipio},
              {$normEstado}
            ON DUPLICATE KEY UPDATE
              municipio = VALUES(municipio),
              estado = VALUES(estado),
              is_active = 1,
              updated_at = CURRENT_TIMESTAMP";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
}

$opts = getopt('', ['dump::', 'state::', 'skip-sepomex-import', 'help']);
if (isset($opts['help'])) {
    usage();
    exit(0);
}

$baseDir = dirname(__DIR__);
$dumpPath = (string)($opts['dump'] ?? ($baseDir . '/db_backups/sepomex_20251115_114415.sql'));
$stateFilter = isset($opts['state']) ? trim((string)$opts['state']) : null;
$skipSepomexImport = isset($opts['skip-sepomex-import']);

try {
    $pdo = mxmed_pdo();
    $cfg = mxmed_load_db_config()['mysql'] ?? [];
    if (empty($cfg['host']) || empty($cfg['user']) || empty($cfg['dbname'])) {
        throw new RuntimeException('Configuración MySQL incompleta');
    }

    ensureCatalogTable($pdo);
    $beforeRows = (int)$pdo->query('SELECT COUNT(*) FROM catalog_cp_colonias')->fetchColumn();
    $beforeCp = (int)$pdo->query('SELECT COUNT(DISTINCT cp) FROM catalog_cp_colonias')->fetchColumn();
    echo "CATALOG antes: {$beforeRows} filas, {$beforeCp} CPs.\n";

    importSepomexDumpIfNeeded($pdo, $cfg, $dumpPath, $skipSepomexImport);
    importCatalogFromSepomex($pdo, $stateFilter);

    $afterRows = (int)$pdo->query('SELECT COUNT(*) FROM catalog_cp_colonias')->fetchColumn();
    $afterCp = (int)$pdo->query('SELECT COUNT(DISTINCT cp) FROM catalog_cp_colonias')->fetchColumn();
    echo "CATALOG después: {$afterRows} filas, {$afterCp} CPs.\n";
    echo 'Filas netas agregadas: ' . ($afterRows - $beforeRows) . "\n";

    $cpStmt = $pdo->prepare(
        'SELECT cp, colonia, municipio, estado
           FROM catalog_cp_colonias
          WHERE cp = :cp AND is_active = 1
          ORDER BY colonia'
    );
    $cpStmt->execute([':cp' => '20235']);
    $cpRows = $cpStmt->fetchAll(PDO::FETCH_ASSOC);
    echo 'CP 20235 colonias activas: ' . count($cpRows) . "\n";
    foreach (array_slice($cpRows, 0, 10) as $row) {
        echo '- ' . $row['cp'] . ' | ' . $row['colonia'] . ' | ' . $row['municipio'] . ' | ' . $row['estado'] . "\n";
    }

    echo "Importación completada.\n";
    exit(0);
} catch (Throwable $e) {
    fwrite(STDERR, "ERROR: " . $e->getMessage() . "\n");
    exit(1);
}
