<?php
declare(strict_types=1);

$host = getenv('MXMED_DB_HOST') ?: '127.0.0.1';
$port = (int)(getenv('MXMED_DB_PORT') ?: 3306);
$user = getenv('MXMED_DB_USER') ?: 'root';
$pass = getenv('MXMED_DB_PASS') ?: '';
$database = 'doc_rec_std03a_' . bin2hex(random_bytes(6));
$root = dirname(__DIR__, 3);
$admin = new PDO("mysql:host={$host};port={$port};charset=utf8mb4", $user, $pass, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);

function std03a(bool $condition, string $label): void
{
    if (!$condition) throw new RuntimeException('FAIL ' . $label);
    echo "PASS {$label}\n";
}

function std03aRejects(callable $operation, string $label): void
{
    try {
        $operation();
    } catch (PDOException $e) {
        std03a((string)($e->errorInfo[0] ?? '') === '23000', $label);
        return;
    }
    throw new RuntimeException('FAIL ' . $label);
}

try {
    $admin->exec("CREATE DATABASE `{$database}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $pdo = new PDO("mysql:host={$host};port={$port};dbname={$database};charset=utf8mb4", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    $pdo->exec((string)file_get_contents($root . '/modules/agenda/db/consultorios_schema.sql'));
    // Reconstruct the exact pre-migration table contract for the forward rehearsal.
    $pdo->exec((string)file_get_contents($root . '/modules/agenda/db/migrations/2026_10_14_10_rollback_consultorio_document_presentation.sql'));
    $pdo->exec("INSERT INTO consultorios
        (doctor_id,consultorio_id,titulo,calle,num_ext,telefonos_json,whatsapp,urgencias_json)
        VALUES
        ('doctor-a','office-1','Centro','Calle Uno','10',JSON_ARRAY('449 111 1111'),'449 999 9999',JSON_ARRAY('449 911 0000')),
        ('doctor-a','office-2','Norte','Calle Dos','20',JSON_ARRAY('449 222 2222'),NULL,JSON_ARRAY()),
        ('doctor-a','office-3','Sur','Calle Tres','30',JSON_ARRAY(),NULL,JSON_ARRAY()),
        ('doctor-a','office-4','Poniente','Calle Cuatro','40',JSON_ARRAY(),NULL,JSON_ARRAY()),
        ('doctor-b','office-1','Otro médico','Calle B','1',JSON_ARRAY('449 333 3333'),NULL,JSON_ARRAY())");

    $before = $pdo->query('SELECT doctor_id,consultorio_id,titulo,telefonos_json,whatsapp,urgencias_json FROM consultorios ORDER BY doctor_id,consultorio_id')->fetchAll();
    $pdo->exec((string)file_get_contents($root . '/modules/agenda/db/migrations/2026_10_14_10_consultorio_document_presentation.sql'));

    $defaults = $pdo->query('SELECT document_is_primary,document_display_order,document_publication_enabled FROM consultorios')->fetchAll();
    std03a(count(array_filter($defaults, static fn(array $row): bool =>
        (int)$row['document_is_primary'] !== 0 || $row['document_display_order'] !== null
        || (int)$row['document_publication_enabled'] !== 0)) === 0,
        'legacy locations default private, unordered and non-primary');

    $after = $pdo->query('SELECT doctor_id,consultorio_id,titulo,telefonos_json,whatsapp,urgencias_json FROM consultorios ORDER BY doctor_id,consultorio_id')->fetchAll();
    std03a($before === $after, 'migration preserves existing location and contact data');

    $pdo->exec("UPDATE consultorios SET document_is_primary=1,document_display_order=1,document_publication_enabled=1 WHERE doctor_id='doctor-a' AND consultorio_id='office-1'");
    std03a((int)$pdo->query("SELECT COUNT(*) FROM consultorios WHERE doctor_id='doctor-a' AND document_is_primary=1")->fetchColumn() === 1,
        'one explicit primary accepted');
    std03aRejects(static fn() => $pdo->exec("UPDATE consultorios SET document_is_primary=1 WHERE doctor_id='doctor-a' AND consultorio_id='office-2'"),
        'duplicate primary rejected by database');

    $pdo->beginTransaction();
    $pdo->query("SELECT consultorio_id FROM consultorios WHERE doctor_id='doctor-a' ORDER BY consultorio_id FOR UPDATE")->fetchAll();
    $pdo->exec("UPDATE consultorios SET document_is_primary=0 WHERE doctor_id='doctor-a' AND document_is_primary=1");
    $pdo->exec("UPDATE consultorios SET document_is_primary=1 WHERE doctor_id='doctor-a' AND consultorio_id='office-2'");
    $pdo->commit();
    std03a($pdo->query("SELECT consultorio_id FROM consultorios WHERE doctor_id='doctor-a' AND document_is_primary=1")->fetchColumn() === 'office-2',
        'primary change succeeds under physician row lock');

    $pdo->exec("UPDATE consultorios SET document_display_order=2,document_publication_enabled=1 WHERE doctor_id='doctor-a' AND consultorio_id='office-2'");
    $pdo->exec("UPDATE consultorios SET document_display_order=3,document_publication_enabled=1 WHERE doctor_id='doctor-a' AND consultorio_id='office-3'");
    $pdo->exec("UPDATE consultorios SET document_display_order=4,document_publication_enabled=1 WHERE doctor_id='doctor-a' AND consultorio_id='office-4'");
    std03aRejects(static fn() => $pdo->exec("UPDATE consultorios SET document_display_order=3 WHERE doctor_id='doctor-a' AND consultorio_id='office-4'"),
        'duplicate display order rejected per physician');

    $ids = $pdo->query("SELECT consultorio_id FROM consultorios WHERE doctor_id='doctor-a' AND document_publication_enabled=1 ORDER BY document_is_primary DESC,document_display_order ASC LIMIT 3")->fetchAll(PDO::FETCH_COLUMN);
    std03a($ids === ['office-2','office-1','office-3'], 'footer selection is primary first, explicit order, maximum three');

    $pdo->exec("UPDATE consultorios SET document_is_primary=1,document_display_order=1,document_publication_enabled=1 WHERE doctor_id='doctor-b' AND consultorio_id='office-1'");
    std03a((int)$pdo->query('SELECT COUNT(*) FROM consultorios WHERE document_is_primary=1')->fetchColumn() === 2,
        'primary uniqueness is isolated per physician');

    $pdo->exec((string)file_get_contents($root . '/modules/agenda/db/migrations/2026_10_14_10_rollback_consultorio_document_presentation.sql'));
    $columns = $pdo->query("SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='consultorios' AND column_name LIKE 'document_%'")->fetchColumn();
    std03a((int)$columns === 0, 'rollback removes only presentation authority columns');
    $afterRollback = $pdo->query('SELECT doctor_id,consultorio_id,titulo,telefonos_json,whatsapp,urgencias_json FROM consultorios ORDER BY doctor_id,consultorio_id')->fetchAll();
    std03a($before === $afterRollback, 'rollback preserves original location and contact data');
} finally {
    $admin->exec("DROP DATABASE IF EXISTS `{$database}`");
}

echo "DOC_REC_STD03A_MIGRATION_REHEARSAL=PASS\n";

