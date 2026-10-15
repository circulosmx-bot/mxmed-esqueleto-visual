<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../api/_lib/clinical_prescription_v2.php';

function std03(bool $condition, string $label): void
{
    if (!$condition) {
        throw new RuntimeException('FAIL ' . $label);
    }
    echo "PASS {$label}\n";
}

$pdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$pdo->exec('CREATE TABLE profiles_doctors (doctor_id TEXT PRIMARY KEY, logo_url TEXT)');
$pdo->exec('CREATE TABLE media_assets (
    media_id TEXT PRIMARY KEY, owner_type TEXT, owner_id TEXT, purpose TEXT,
    classification TEXT, public_url TEXT, mime_type TEXT, status TEXT
)');

$uuid = 'a1e44098-a0ef-403f-b51e-8bee88100ef8';
$url = '/api/media/index.php/public/' . $uuid;
$pdo->prepare('INSERT INTO profiles_doctors VALUES (?, ?)')->execute(['doctor-1', $url]);
$insert = $pdo->prepare('INSERT INTO media_assets VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
$insert->execute([$uuid, 'PHYSICIAN', 'doctor-1', 'PHYSICIAN_PERSONAL_LOGO', 'PUBLIC', $url, 'image/webp', 'READY']);

std03(clinical_prescription_v2_published_logo($pdo, 'doctor-1') === $url, 'HEADER01 approved canonical logo resolves');

$pdo->exec("UPDATE media_assets SET status='DELETED'");
std03(clinical_prescription_v2_published_logo($pdo, 'doctor-1') === '', 'HEADER03 non-ready logo rejected');
$pdo->exec("UPDATE media_assets SET status='READY', classification='PRIVATE'");
std03(clinical_prescription_v2_published_logo($pdo, 'doctor-1') === '', 'SECURITY01 private logo rejected');
$pdo->exec("UPDATE media_assets SET classification='PUBLIC', owner_id='doctor-2'");
std03(clinical_prescription_v2_published_logo($pdo, 'doctor-1') === '', 'SECURITY01 cross-owner logo rejected');
$pdo->exec("UPDATE media_assets SET owner_id='doctor-1'");
$pdo->exec("UPDATE profiles_doctors SET logo_url='https://example.test/logo.webp'");
std03(clinical_prescription_v2_published_logo($pdo, 'doctor-1') === '', 'HEADER03 arbitrary external logo rejected');
std03(!clinical_prescription_v2_published_logo_url('data:image/webp;base64,AAAA'), 'HEADER03 data logo rejected');

$payload = [
    'contract_version' => 2,
    'patient_snapshot' => ['full_name' => 'Paciente QA', 'age' => '40', 'sex' => 'F'],
    'physician_snapshot' => [
        'full_name' => 'Dra. Médica QA', 'specialty' => 'Medicina interna',
        'license' => 'QA-123', 'specialty_license' => 'QA-456',
    ],
    'professional_context' => ['logo_url' => $url, 'consultorio' => null],
    'presentation' => ['version' => 1, 'professional_header' => 'shown'],
    'diagnostic_context' => ['mode' => 'sin_diagnostico', 'text' => ''],
    'prescription' => ['items' => [['name' => 'Medicamento QA']], 'observaciones' => ''],
    'issue_date' => '2026-10-14', 'signatures' => ['doctor' => null],
];
$html = clinical_prescription_v2_render($payload);
std03(str_contains($html, 'src="' . $url . '"'), 'HEADER01 approved logo renders');
std03(str_contains($html, 'Dra. Médica QA') && str_contains($html, 'QA-123') && str_contains($html, 'QA-456'), 'HEADER04 physician credentials render');
$payload['professional_context']['logo_url'] = '';
std03(!str_contains(clinical_prescription_v2_render($payload), '<img'), 'HEADER02 missing logo has no broken image');
$payload['presentation']['professional_header'] = 'hidden';
$hidden = clinical_prescription_v2_render($payload);
std03(!str_contains($hidden, 'clinical-doc-head') && !str_contains($hidden, 'doc-base-signature')
    && !str_contains($hidden, 'Dra. Médica QA') && !str_contains($hidden, 'QA-123')
    && !str_contains($hidden, 'QA-456'), 'HEADER05 hidden mode removes all physician identity');

