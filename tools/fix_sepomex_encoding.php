<?php
$baseDir = __DIR__;
$configPath = realpath($baseDir . '/../sepomex-db.config.php');
if (!$configPath) {
    fwrite(STDERR, "No se encontró sepomex-db.config.php\n");
    exit(1);
}
$config = include $configPath;
if (!is_array($config) || empty($config['mysql'])) {
    fwrite(STDERR, "No se pudo leer la configuración de base de datos.\n");
    exit(1);
}
$dbCfg = $config['mysql'];
$mysqli = @new mysqli($dbCfg['host'], $dbCfg['user'], $dbCfg['pass'], $dbCfg['dbname'], (int)$dbCfg['port']);
if ($mysqli->connect_errno) {
    fwrite(STDERR, "Error de conexión ({$mysqli->connect_errno}): {$mysqli->connect_error}\n");
    exit(1);
}
if (!$mysqli->set_charset($dbCfg['charset'] ?? 'utf8mb4')) {
    fwrite(STDERR, "No se pudo fijar charset: {$mysqli->error}\n");
    exit(1);
}
$fields = ['d_asenta','d_tipo_asenta','d_mnpio','d_estado','d_ciudad','d_zona'];
$whereParts = [];
foreach ($fields as $field) {
    $whereParts[] = "$field LIKE BINARY '%Ã%'";
    $whereParts[] = "$field LIKE BINARY '%Â%'";
    $whereParts[] = "$field LIKE BINARY '%�%'";
}
$where = implode(' OR ', $whereParts);
$sql = "SELECT id_asenta_cpcons, " . implode(',', $fields) . " FROM sepomex WHERE $where";
$res = $mysqli->query($sql);
if (!$res) {
    fwrite(STDERR, "Consulta falló: {$mysqli->error}\n");
    exit(1);
}
$apply = in_array('--apply', $argv, true);
$total = 0;
$affected = 0;
$stmt = $mysqli->prepare("UPDATE sepomex SET d_asenta=?, d_tipo_asenta=?, d_mnpio=?, d_estado=?, d_ciudad=?, d_zona=? WHERE id_asenta_cpcons=?");
if (!$stmt) {
    fwrite(STDERR, "No se pudo preparar UPDATE: {$mysqli->error}\n");
    exit(1);
}
while ($row = $res->fetch_assoc()) {
    $total++;
    $converted = [];
    $needsUpdate = false;
    foreach ($fields as $field) {
        $value = $row[$field];
        if ($value === null) {
            $converted[$field] = null;
            continue;
        }
        $fixed = mb_convert_encoding($value, 'UTF-8', 'Windows-1252');
        if ($fixed !== $value) {
            $needsUpdate = true;
        }
        $converted[$field] = $fixed;
    }
    if (!$needsUpdate) {
        continue;
    }
    if ($apply) {
        $stmt->bind_param(
            'sssssss',
            $converted['d_asenta'],
            $converted['d_tipo_asenta'],
            $converted['d_mnpio'],
            $converted['d_estado'],
            $converted['d_ciudad'],
            $converted['d_zona'],
            $row['id_asenta_cpcons']
        );
        if (!$stmt->execute()) {
            fwrite(STDERR, "No se pudo actualizar {$row['id_asenta_cpcons']}: {$stmt->error}\n");
            continue;
        }
        $affected++;
        echo "Actualizado ID {$row['id_asenta_cpcons']}\n";
    } else {
        echo "Detectado ID {$row['id_asenta_cpcons']} (sin aplicar)\n";
    }
}
$res->free();
$stmt->close();
$mysqli->close();
if (!$apply) {
    echo "Analizados $total registros con posibles caracteres dañados. Ejecuta con --apply para corregir.\n";
} else {
    echo "Análisis: $total coincidencias; actualizados $affected registros.\n";
}
?>
