<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli' || ($argv[1] ?? null) !== '--response') exit(1);
require_once __DIR__.'/../../../api/_lib/clinical_study_contract.php';
require_once __DIR__.'/../../../api/_lib/clinical_study_catalog_read.php';
$query = json_decode((string)($argv[2] ?? '{}'), true, 512, JSON_THROW_ON_ERROR);
if (!is_array($query)) exit(1);
$pdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$pdo->exec('CREATE TABLE clinical_study_types (study_type_id INTEGER PRIMARY KEY, study_type_key TEXT, display_name_es TEXT, category_key TEXT, aliases_json TEXT, is_active INTEGER)');
$insert = $pdo->prepare('INSERT INTO clinical_study_types VALUES (?,?,?,?,?,1)');
$file = fopen(__DIR__.'/../../../docs/clinical/STUDY_SEARCH01_AUTHORITY_MATRIX.csv', 'rb');
if ($file === false) exit(1);
$header = fgetcsv($file, null, ',', '"', '');
$id = 0;
while (($values = fgetcsv($file, null, ',', '"', '')) !== false) {
    $row = array_combine($header, $values);
    $insert->execute([++$id, $row['study_key'], $row['canonical_display_name'], $row['category'], $row['current_aliases']]);
}
fclose($file);
echo json_encode(['ok' => true, 'data' => clinical_study_catalog_read($pdo, $query)], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
