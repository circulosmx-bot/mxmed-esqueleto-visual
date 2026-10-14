<?php
declare(strict_types=1);

use Agenda\Repositories\ConsultoriosRepository;

require_once __DIR__ . '/../repositories/ConsultoriosRepository.php';
require_once dirname(__DIR__, 3) . '/api/_lib/clinical_professional_presentation.php';

$host = getenv('MXMED_DB_HOST') ?: '127.0.0.1';
$port = (int)(getenv('MXMED_DB_PORT') ?: 3306);
$user = getenv('MXMED_DB_USER') ?: 'root';
$pass = getenv('MXMED_DB_PASS') ?: '';
$root = dirname(__DIR__, 3);

function std03aIntegrationPdo(string $database = ''): PDO
{
    global $host, $port, $user, $pass;
    $dsn = "mysql:host={$host};port={$port};charset=utf8mb4" . ($database === '' ? '' : ";dbname={$database}");
    return new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
}

function std03aIntegrationAssert(bool $condition, string $label): void
{
    if (!$condition) throw new RuntimeException('FAIL ' . $label);
    echo "PASS {$label}\n";
}

function std03aPayload(string $doctor, string $office, array $overrides = []): array
{
    return array_replace([
        'doctor_id'=>$doctor, 'consultorio_id'=>$office, 'group_id'=>null,
        'titulo'=>'Consultorio ' . $office, 'grupo_nombre'=>null,
        'calle'=>'Avenida Salud', 'num_ext'=>'100', 'num_int'=>'4B', 'cp'=>'20000',
        'colonia'=>'Centro', 'municipio'=>'Aguascalientes', 'estado'=>'Aguascalientes',
        'telefonos_json'=>json_encode(['449 111 2233'], JSON_THROW_ON_ERROR),
        'whatsapp'=>'449 999 0000', 'urgencias_json'=>json_encode(['449 911 0000'], JSON_THROW_ON_ERROR),
        'logo_url'=>null, 'foto_url'=>null, 'lat'=>null, 'lng'=>null,
        'geocode_source'=>null, 'geocode_updated_at'=>null,
        'document_is_primary_is_set'=>0, 'document_display_order_is_set'=>0,
        'document_publication_enabled_is_set'=>0,
    ], $overrides);
}

if (($argv[1] ?? '') === '--worker') {
    $database = (string)($argv[2] ?? '');
    $office = (string)($argv[3] ?? '');
    $pdo = std03aIntegrationPdo($database);
    (new ConsultoriosRepository($pdo))->upsertConsultorio(std03aPayload('doctor-a', $office, [
        'document_is_primary'=>1, 'document_is_primary_is_set'=>1,
    ]));
    echo "WORKER_OK\n";
    exit(0);
}

$database = 'doc_rec_std03a_integration_' . bin2hex(random_bytes(6));
$admin = std03aIntegrationPdo();
try {
    $admin->exec("CREATE DATABASE `{$database}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $pdo = std03aIntegrationPdo($database);
    $pdo->exec((string)file_get_contents($root . '/modules/agenda/db/consultorios_schema.sql'));
    $repo = new ConsultoriosRepository($pdo);

    foreach (['office-1','office-2','office-3','office-4'] as $office) {
        $phones = $office === 'office-1'
            ? ['449 111 2233', '(449) 111-2233', '123'] : ['449 222 33' . substr($office, -1)];
        $repo->upsertConsultorio(std03aPayload('doctor-a', $office, [
            'telefonos_json'=>json_encode($phones, JSON_THROW_ON_ERROR),
        ]));
    }
    $repo->upsertConsultorio(std03aPayload('doctor-b', 'office-1'));
    $repo->upsertConsultorio(std03aPayload('doctor-c', 'office-1', [
        'document_publication_enabled'=>1, 'document_publication_enabled_is_set'=>1,
    ]));

    $repo->upsertConsultorio(std03aPayload('doctor-a', 'office-1', [
        'document_display_order'=>1, 'document_display_order_is_set'=>1,
        'document_publication_enabled'=>1, 'document_publication_enabled_is_set'=>1,
    ]));
    $repo->upsertConsultorio(std03aPayload('doctor-a', 'office-2', [
        'document_is_primary'=>1, 'document_is_primary_is_set'=>1,
        'document_display_order'=>2, 'document_display_order_is_set'=>1,
        'document_publication_enabled'=>1, 'document_publication_enabled_is_set'=>1,
    ]));
    $repo->upsertConsultorio(std03aPayload('doctor-a', 'office-3', [
        'document_display_order'=>3, 'document_display_order_is_set'=>1,
        'document_publication_enabled'=>1, 'document_publication_enabled_is_set'=>1,
    ]));
    $repo->upsertConsultorio(std03aPayload('doctor-a', 'office-4', [
        'document_display_order'=>null, 'document_display_order_is_set'=>1,
        'document_publication_enabled'=>0, 'document_publication_enabled_is_set'=>1,
    ]));

    $presentation = clinical_professional_presentation_resolve($pdo, 'doctor-a');
    std03aIntegrationAssert(array_column($presentation['offices'], 'name') === [
        'Consultorio office-2', 'Consultorio office-1', 'Consultorio office-3'
    ], 'primary first, explicit secondary order and maximum three');
    std03aIntegrationAssert($presentation['offices'][1]['phones'] === ['449 111 2233'],
        'regular phones are validated and deduplicated');
    $serialized = json_encode($presentation, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    std03aIntegrationAssert(!str_contains($serialized, '449 999 0000') && !str_contains($serialized, '449 911 0000'),
        'WhatsApp and emergency phones are excluded');
    std03aIntegrationAssert(str_contains($presentation['offices'][0]['address'], 'Avenida Salud 100, interior 4B')
        && str_contains($presentation['offices'][0]['address'], 'Col. Centro')
        && str_contains($presentation['offices'][0]['address'], 'C.P. 20000'),
        'complete professional address is preserved');
    std03aIntegrationAssert(clinical_professional_presentation_resolve($pdo, 'doctor-b')['offices'] === [],
        'cross-physician locations are isolated');
    std03aIntegrationAssert(clinical_professional_presentation_resolve($pdo, 'doctor-c')['offices'] === [],
        'published secondary without explicit order remains unconfigured and hidden');

    $repo->upsertConsultorio(std03aPayload('doctor-a', 'office-2', [
        'document_display_order'=>1, 'document_display_order_is_set'=>1,
    ]));
    $orders = $pdo->query("SELECT consultorio_id,document_display_order FROM consultorios WHERE doctor_id='doctor-a' AND consultorio_id IN ('office-1','office-2') ORDER BY consultorio_id")->fetchAll(PDO::FETCH_KEY_PAIR);
    std03aIntegrationAssert(array_map('intval', $orders) === ['office-1'=>2, 'office-2'=>1],
        'single-writer display-order conflict performs an atomic swap');

    $repo->upsertConsultorio(std03aPayload('doctor-a', 'office-1', [
        'document_is_primary'=>1, 'document_is_primary_is_set'=>1,
    ]));
    std03aIntegrationAssert((string)$pdo->query("SELECT consultorio_id FROM consultorios WHERE doctor_id='doctor-a' AND document_is_primary=1")->fetchColumn() === 'office-1',
        'changing primary atomically clears the previous primary');
    try {
        $pdo->exec("UPDATE consultorios SET document_is_primary=1 WHERE doctor_id='doctor-a' AND consultorio_id='office-2'");
        throw new RuntimeException('FAIL duplicate primary database rejection');
    } catch (PDOException $e) {
        std03aIntegrationAssert((string)($e->errorInfo[0] ?? '') === '23000', 'duplicate primary rejected by database');
    }

    $pdo->beginTransaction();
    $repo->upsertConsultorio(std03aPayload('doctor-a', 'office-2', [
        'document_is_primary'=>1, 'document_is_primary_is_set'=>1,
    ]));
    $process = proc_open([PHP_BINARY, __FILE__, '--worker', $database, 'office-1'],
        [1=>['pipe','w'], 2=>['pipe','w']], $pipes);
    if (!is_resource($process)) throw new RuntimeException('FAIL concurrent worker start');
    usleep(250000);
    std03aIntegrationAssert((proc_get_status($process)['running'] ?? false) === true,
        'concurrent primary update waits on physician row lock');
    $pdo->commit();
    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]); fclose($pipes[2]);
    $exit = proc_close($process);
    std03aIntegrationAssert($exit === 0 && str_contains($stdout, 'WORKER_OK') && $stderr === '',
        'concurrent primary update completes after lock release');
    std03aIntegrationAssert((int)$pdo->query("SELECT COUNT(*) FROM consultorios WHERE doctor_id='doctor-a' AND document_is_primary=1")->fetchColumn() === 1,
        'concurrent updates preserve exactly one primary');

    std03aIntegrationAssert(count($repo->listByDoctor('doctor-a')) === 4,
        'canonical consultorio writer remains compatible');
} finally {
    $admin->exec("DROP DATABASE IF EXISTS `{$database}`");
}

$privateAppSource = (string)file_get_contents($root . '/private-app.php');
$consultorioUiSource = (string)file_get_contents($root . '/assets/js/perfil/consultorio/multisede.js');
std03aIntegrationAssert(str_contains($privateAppSource, 'Consultorio principal')
    && str_contains($privateAppSource, 'Orden de presentación')
    && str_contains($privateAppSource, 'Mostrar en documentos'),
    'existing consultorio administration surface exposes all presentation controls');
std03aIntegrationAssert(str_contains($consultorioUiSource, 'document_is_primary:')
    && str_contains($consultorioUiSource, 'document_display_order:')
    && str_contains($consultorioUiSource, 'document_publication_enabled:'),
    'canonical consultorio PUT payload persists presentation controls');
std03aIntegrationAssert(str_contains($consultorioUiSource, "row?.document_is_primary === true")
    && str_contains($consultorioUiSource, "row?.document_publication_enabled === true"),
    'administration controls hydrate from persisted server state');

echo "DOC_REC_STD03A_INTEGRATION=PASS\n";
