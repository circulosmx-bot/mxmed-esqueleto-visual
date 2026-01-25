<?php
// Utilidad simple para importar el catálogo SEPOMEX a una BD local (MySQL o SQLite)
// Uso rápido:
// 1) Copia sepomex-db.config.sample.php a sepomex-db.config.php y ajusta credenciales.
// 2) Descarga el TXT oficial (CPdescarga.txt) y colócalo en assets/data/sepomex/.
// 3) Abre /sepomex-import.php en el navegador y ejecuta la importación.

ini_set('max_execution_time', '0');
header('Content-Type: text/plain; charset=utf-8');

$cfgFile = __DIR__ . '/sepomex-db.config.php';
if (!is_file($cfgFile)) {
  echo "Config no encontrada. Copia sepomex-db.config.sample.php a sepomex-db.config.php y ajusta valores.\n";
  exit(1);
}
$cfg = include $cfgFile;
$driver = $cfg['driver'] ?? 'mysql';

$DATA_DIR = __DIR__ . '/assets/data/sepomex';
@mkdir($DATA_DIR, 0775, true);
$TXT = $DATA_DIR . '/CPdescarga.txt';
$ZIP = $DATA_DIR . '/CPdescarga.zip';

// (Opcional) Descargar automáticamente si se solicita
$download = isset($_GET['download']);
if ($download) {
  $candidates = [
    // Enlaces comunes (pueden cambiar). Si ninguno funciona, descargar manualmente.
    'https://www.correosdemexico.gob.mx/SSLServicios/ConsultaCP/Descarga.aspx',
    'https://www.correosdemexico.gob.mx/SSLServicios/ConsultaCP/CPdescarga.aspx',
    'https://www.correosdemexico.gob.mx/SSLServicios/ConsultaCP/CPdescarga.zip',
  ];
  foreach ($candidates as $url) {
    echo "Intentando descargar: {$url}\n";
    $ctx = stream_context_create(['http'=>['timeout'=>30],'https'=>['timeout'=>30]]);
    $bin = @file_get_contents($url, false, $ctx);
    if ($bin !== false) {
      file_put_contents($ZIP, $bin);
      echo "Descargado en: {$ZIP}\n";
      break;
    }
  }
  if (!is_file($ZIP) || filesize($ZIP) < 1024) {
    echo "No se pudo descargar automáticamente. Descarga manualmente el TXT y colócalo en {$TXT}\n";
  }
}

if (!is_file($TXT)) {
  // Intentar extraer si existe ZIP
  if (is_file($ZIP)) {
    if (class_exists('ZipArchive')) {
      $zip = new ZipArchive();
      if ($zip->open($ZIP) === true) {
        for ($i=0; $i < $zip->numFiles; $i++) {
          $name = $zip->getNameIndex($i);
          if (preg_match('/\.txt$/i', $name)) {
            copy("zip://{$ZIP}#{$name}", $TXT);
            break;
          }
        }
        $zip->close();
      }
    }
  }
}

if (!is_file($TXT)) {
  echo "No se encontró el archivo TXT.\n";
  echo "Descarga el catálogo (CPdescarga.txt) y colócalo en: {$TXT}\n";
  exit(1);
}

// Conectar BD
try {
  if ($driver === 'sqlite') {
    $path = $cfg['sqlite']['path'];
    @mkdir(dirname($path), 0775, true);
    $pdo = new PDO('sqlite:' . $path);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
  } else {
    $h = $cfg['mysql']['host']; $p = (int)$cfg['mysql']['port']; $db = $cfg['mysql']['dbname'];
    $u = $cfg['mysql']['user']; $pw = $cfg['mysql']['pass']; $ch = $cfg['mysql']['charset'] ?? 'utf8mb4';
    $co = $cfg['mysql']['collation'] ?? 'utf8mb4_unicode_ci';
    // Intentar crear la BD si no existe
    try {
      $dsn0 = "mysql:host={$h};port={$p};charset={$ch}";
      $pdo0 = new PDO($dsn0, $u, $pw, [ PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION ]);
      $pdo0->exec("CREATE DATABASE IF NOT EXISTS `{$db}` DEFAULT CHARACTER SET {$ch} COLLATE {$co}");
    } catch (Throwable $e) { /* continuar aunque falle la creación */ }
    $dsn = "mysql:host={$h};port={$p};dbname={$db};charset={$ch}";
    $pdo = new PDO($dsn, $u, $pw, [ PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::MYSQL_ATTR_LOCAL_INFILE => true ]);
    try { $pdo->exec("SET NAMES {$ch} COLLATE {$co}"); } catch (Throwable $e) { /* no fatal */ }
  }
} catch (Throwable $e) {
  echo "Error de conexión BD: ".$e->getMessage()."\n";
  exit(1);
}

// Crear tabla mínima (campos más usados)
$pdo->exec("CREATE TABLE IF NOT EXISTS sepomex (
  d_codigo    VARCHAR(10),
  d_asenta    VARCHAR(150),
  d_tipo_asenta VARCHAR(60),
  d_mnpio     VARCHAR(150),
  d_estado    VARCHAR(150),
  d_ciudad    VARCHAR(150),
  d_cp        VARCHAR(10),
  c_estado    VARCHAR(10),
  c_oficina   VARCHAR(20),
  c_tipo_asenta VARCHAR(10),
  c_mnpio     VARCHAR(10),
  id_asenta_cpcons VARCHAR(20),
  d_zona      VARCHAR(50),
  c_cve_ciudad VARCHAR(10)
)");

// Método de importación
$method = $_GET['method'] ?? 'auto';
if ($driver !== 'sqlite' && $method === 'auto') { $method = 'load'; } // LOAD DATA para MySQL

if ($method === 'load' && $driver !== 'sqlite') {
  echo "Importando con LOAD DATA LOCAL INFILE...\n";
  try {
    // Habilitar LOCAL INFILE si es posible
    $pdo->exec("SET GLOBAL local_infile = 1");
  } catch(Exception $e) { /* puede requerir permisos */ }
  $stmt = $pdo->prepare(
    "LOAD DATA LOCAL INFILE :path
     INTO TABLE sepomex
     CHARACTER SET latin1
     FIELDS TERMINATED BY '|' 
     LINES TERMINATED BY '\r\n'
     IGNORE 1 LINES
     (
       d_codigo,
       d_asenta,
       d_tipo_asenta,
       d_mnpio,
       d_estado,
       d_ciudad,
       d_cp,
       c_estado,
       c_oficina,
       @c_cp,             -- saltar columna c_CP del archivo fuente
       c_tipo_asenta,
       c_mnpio,
       id_asenta_cpcons,
       d_zona,
       c_cve_ciudad
     )"
  );
  $stmt->bindValue(':path', $TXT);
  try {
    $stmt->execute();
    echo "Importación terminada (LOAD DATA).\n";
  } catch (Throwable $e) {
    echo "LOAD DATA falló: ".$e->getMessage()."\nUsa method=insert para importar por lotes.\n";
  }
} else {
  echo "Importando por inserciones (puede tardar, usa ?limit= para probar)...\n";
  $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 0; // 0 = todo
  $pdo->beginTransaction();
  $ins = $pdo->prepare(
    "INSERT INTO sepomex
     (d_codigo,d_asenta,d_tipo_asenta,d_mnpio,d_estado,d_ciudad,d_cp,c_estado,c_oficina,c_tipo_asenta,c_mnpio,id_asenta_cpcons,d_zona,c_cve_ciudad)
     VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)"
  );
  $fh = fopen($TXT, 'r');
  // saltar encabezado
  fgets($fh);
  $n = 0; $batch = 0;
  while (($line = fgets($fh)) !== false) {
    $parts = array_map('trim', explode('|', rtrim($line, "\r\n")));
    if (count($parts) < 15) continue; // el archivo trae 15 columnas; omitiremos c_CP (índice 9)
    // Normalizar a UTF-8 desde latin1 para evitar errores de codificación
    $parts = array_map(function($s){ return iconv('ISO-8859-1','UTF-8//TRANSLIT',$s); }, $parts);
    // Remapear a 14 columnas sin c_CP
    $vals = [
      $parts[0],  // d_codigo
      $parts[1],  // d_asenta
      $parts[2],  // d_tipo_asenta
      $parts[3],  // d_mnpio
      $parts[4],  // d_estado
      $parts[5],  // d_ciudad
      $parts[6],  // d_cp
      $parts[7],  // c_estado
      $parts[8],  // c_oficina
      $parts[10], // c_tipo_asenta (saltamos [9] c_CP)
      $parts[11], // c_mnpio
      $parts[12], // id_asenta_cpcons
      $parts[13], // d_zona
      $parts[14], // c_cve_ciudad
    ];
    $ins->execute($vals);
    $n++; $batch++;
    if ($batch >= 2000) { $pdo->commit(); $pdo->beginTransaction(); $batch = 0; echo "."; }
    if ($limit && $n >= $limit) break;
  }
  fclose($fh);
  $pdo->commit();
  echo "\nImportación por inserciones completada. Filas: {$n}\n";
}

echo "\nPrueba ahora: /sepomex-local.php?cp=20230\n";
