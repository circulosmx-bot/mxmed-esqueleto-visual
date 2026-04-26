<?php
declare(strict_types=1);

require_once __DIR__ . '/../api/_lib/db.php';

function usageFixCatalogEncoding(): void
{
    $cmd = basename(__FILE__);
    echo "Uso:\n";
    echo "  php tools/{$cmd} [--apply]\n\n";
    echo "Opciones:\n";
    echo "  --apply   Aplica cambios (sin esta bandera solo hace diagnóstico)\n";
    echo "  --help    Muestra esta ayuda\n";
}

function ensureCatalogTableExists(PDO $pdo): void
{
    $exists = $pdo->query("SHOW TABLES LIKE 'catalog_cp_colonias'")->fetchColumn();
    if (!$exists) {
        throw new RuntimeException('No existe tabla catalog_cp_colonias');
    }
}

function ensureSepomexTableExists(PDO $pdo): void
{
    $exists = $pdo->query("SHOW TABLES LIKE 'sepomex'")->fetchColumn();
    if (!$exists) {
        throw new RuntimeException('No existe tabla sepomex (fuente para regenerar catálogo)');
    }
}

function countMojibakeRows(PDO $pdo): int
{
    $sql = "SELECT COUNT(*)
              FROM catalog_cp_colonias
             WHERE colonia REGEXP '[ÃÂ├┬]'
                OR municipio REGEXP '[ÃÂ├┬]'
                OR estado REGEXP '[ÃÂ├┬]'";
    return (int)$pdo->query($sql)->fetchColumn();
}

function buildTempNormalizedCatalog(PDO $pdo): void
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

    $pdo->exec('DROP TEMPORARY TABLE IF EXISTS tmp_catalog_cp_colonias_normalized');
    $sql = "CREATE TEMPORARY TABLE tmp_catalog_cp_colonias_normalized AS
            SELECT
              TRIM(d_codigo) AS cp,
              {$normColonia} AS colonia,
              {$normMunicipio} AS municipio,
              {$normEstado} AS estado
            FROM sepomex
            WHERE d_codigo REGEXP '^[0-9]{5}$'
              AND {$normColonia} <> ''
            GROUP BY
              TRIM(d_codigo),
              {$normColonia},
              {$normMunicipio},
              {$normEstado}";
    $pdo->exec($sql);
}

function printSummary(PDO $pdo, string $title): void
{
    $rows = (int)$pdo->query('SELECT COUNT(*) FROM catalog_cp_colonias')->fetchColumn();
    $cp = (int)$pdo->query('SELECT COUNT(DISTINCT cp) FROM catalog_cp_colonias')->fetchColumn();
    $bad = countMojibakeRows($pdo);
    echo "{$title}: rows={$rows}, cp={$cp}, mojibake_rows={$bad}\n";
}

$opts = getopt('', ['apply', 'help']);
if (isset($opts['help'])) {
    usageFixCatalogEncoding();
    exit(0);
}
$apply = isset($opts['apply']);

try {
    $pdo = mxmed_pdo();
    ensureCatalogTableExists($pdo);
    ensureSepomexTableExists($pdo);

    printSummary($pdo, 'ANTES');
    buildTempNormalizedCatalog($pdo);
    $tmpRows = (int)$pdo->query('SELECT COUNT(*) FROM tmp_catalog_cp_colonias_normalized')->fetchColumn();
    $tmpCp = (int)$pdo->query('SELECT COUNT(DISTINCT cp) FROM tmp_catalog_cp_colonias_normalized')->fetchColumn();
    echo "TEMP_NORMALIZED: rows={$tmpRows}, cp={$tmpCp}\n";

    if (!$apply) {
        echo "Modo diagnóstico. Ejecuta con --apply para regenerar catalog_cp_colonias desde fuente normalizada.\n";
        exit(0);
    }

    $pdo->exec('TRUNCATE TABLE catalog_cp_colonias');
    $pdo->exec("INSERT INTO catalog_cp_colonias (cp, colonia, municipio, estado, is_active)
                SELECT cp, colonia, municipio, estado, 1
                  FROM tmp_catalog_cp_colonias_normalized");

    printSummary($pdo, 'DESPUES');

    $stmt = $pdo->prepare("SELECT cp, colonia, municipio, estado
                             FROM catalog_cp_colonias
                            WHERE cp = :cp
                            ORDER BY colonia");
    $stmt->execute([':cp' => '20218']);
    $rows20218 = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "CP_20218_ROWS=" . count($rows20218) . "\n";
    foreach ($rows20218 as $row) {
        echo '- ' . $row['colonia'] . " | {$row['municipio']} | {$row['estado']}\n";
    }
} catch (Throwable $e) {
    fwrite(STDERR, "ERROR: " . $e->getMessage() . "\n");
    exit(1);
}
