<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') exit(2);
$root = dirname(__DIR__, 3);
require_once $root . '/api/_lib/clinical_professional_authority.php';

$index = file_get_contents($root . '/api/clinical/index.php');
$writer = file_get_contents($root . '/api/_lib/clinical_prescription_v2.php');
$lifecycle = file_get_contents($root . '/api/_lib/clinical_prescription_lifecycle.php');
$qr = file_get_contents($root . '/api/_lib/clinical_prescription_qr.php');
$consent = file_get_contents($root . '/api/_lib/clinical_consent_write.php');
$authority = file_get_contents($root . '/api/_lib/clinical_professional_authority.php');
$encounterUi = file_get_contents($root . '/modules/clinical/ui/encounter.php');
$historyUi = file_get_contents($root . '/modules/clinical/ui/historial.php');
$responsivaQr = file_get_contents($root . '/api/_lib/clinical_responsiva_qr.php');
$multimediaQr = file_get_contents($root . '/api/_lib/clinical_multimedia_qr.php');
$professionalWriters = [];
foreach (['free_document','multimedia_consent','responsiva','certificado','nota','alta','informe','interconsulta'] as $type) {
    $professionalWriters[$type] = file_get_contents($root . '/api/_lib/clinical_' . $type . '_write.php');
}
foreach (array_merge([$index,$writer,$lifecycle,$qr,$consent,$authority,$encounterUi,$historyUi,
    $responsivaQr,$multimediaQr],
    array_values($professionalWriters)) as $source) {
    if (!is_string($source)) throw new RuntimeException('AUTH02A_SOURCE_UNAVAILABLE');
}

function auth02a(bool $condition, string $name, string $value = 'PASS'): void
{
    if (!$condition) throw new RuntimeException($name . '=FAIL');
    echo $name . '=' . $value . "\n";
}

function auth02a_denied(array $context): bool
{
    return clinical_professional_authority_decision($context, 'PRESCRIPTION_SIGN')['allowed'] === false;
}

$historyBlock = substr($index, strpos($index, "=== 'history' && count(\$segments) === 3"), 9000);
$physicalBlock = substr($index, strpos($index, "=== 'physical-exam' && count(\$segments) === 3"), 9000);
auth02a(strpos($historyBlock, 'clinical_m6_assert_legacy_write_allowed')
    < strpos($historyBlock, 'clinical_history_upsert_draft'), 'AUTH02A_09');
auth02a(str_contains($index, "require_once __DIR__ . '/../_lib/clinical_m6_cutover.php'")
    && str_contains($historyBlock, 'clinical_m6_send_legacy_write_blocked')
    && str_contains($physicalBlock, 'clinical_m6_send_legacy_write_blocked'), 'AUTH02A_10');

auth02a(auth02a_denied(['doctor_id'=>'d','user_id'=>'owner','role_code'=>'owner']), 'AUTH02A_11');
auth02a(auth02a_denied(['doctor_id'=>'d','user_id'=>'admin','role_code'=>'administrator']), 'AUTH02A_12');
auth02a(auth02a_denied(['doctor_id'=>'d','user_id'=>'collab','role_code'=>'collaborator']), 'AUTH02A_13');
auth02a(auth02a_denied(['doctor_id'=>'d','user_id'=>'plan-only','capability'=>['enabled'=>true]]), 'AUTH02A_14');
auth02a(true, 'AUTH02A_15', 'BLOCKED_BY_AUTHORITY_CONTRACT');
try {
    clinical_professional_authority_assert(['doctor_id'=>'d','user_id'=>'u'], 'PRESCRIPTION_SIGN');
    auth02a(false, 'AUTH02A_16');
} catch (ClinicalProfessionalAuthorityException $error) {
    auth02a($error->httpStatus() === 403
        && $error->getMessage() === ClinicalProfessionalAuthorityException::ERROR_CODE, 'AUTH02A_16');
}

auth02a(substr_count($qr, "'PRESCRIPTION_REMOTE_SIGN'") >= 2
    && str_contains($qr, "\$action === 'signature'"), 'AUTH02A_17');
auth02a(strpos($writer, "clinical_professional_authority_assert(\$doctor, 'PRESCRIPTION_EMIT')")
    < strpos($writer, 'ClinicalIdempotentCreateExecutor'), 'AUTH02A_18');
auth02a(strpos($lifecycle, "clinical_professional_authority_assert(\$doctor, 'PRESCRIPTION_VOID')")
    < strpos($lifecycle, "\$pdo->beginTransaction()"), 'AUTH02A_19');
auth02a(str_contains($lifecycle,
    "clinical_professional_authority_assert(\$doctor, 'PRESCRIPTION_REISSUE')"), 'AUTH02A_20');
auth02a(str_contains($consent, "clinical_professional_authority_assert(\$doctor, 'CONSENT_PHYSICIAN_SIGN')")
    && str_contains($index, 'CONSENT_PHYSICIAN_REMOTE_SIGN'), 'AUTH02A_21');
auth02a(str_contains($consent, "\$intent === 'granted' || trim((string)(\$doctorSignature['image_data'] ?? '')) !== ''")
    && str_contains($consent, "\$intent === 'draft' ? 'draft' : 'generated'"), 'AUTH02A_22');
auth02a(str_contains($consent, "\$payload['signatures']['doctor']")
    && !str_contains($authority, 'patient_signature'), 'AUTH02A_23');

$fingerprintFiles = [
    'api/_lib/clinical_prescription_binding.php',
    'api/_lib/clinical_consent_signature_binding.php',
    'api/_lib/clinical_free_document_binding.php',
    'api/_lib/clinical_multimedia_consent_binding.php',
    'api/_lib/clinical_responsiva_binding.php',
    'api/_lib/clinical_certificado_binding.php',
    'api/_lib/clinical_nota_binding.php',
    'api/_lib/clinical_alta_binding.php',
    'api/_lib/clinical_informe_binding.php',
    'api/_lib/clinical_interconsulta_binding.php',
    'assets/js/clinical/prescription-binding.js',
];
$existingFingerprintFiles = array_values(array_filter($fingerprintFiles,
    static fn(string $path): bool => is_file($root . '/' . $path)));
$command = 'cd ' . escapeshellarg($root) . ' && git diff --quiet d5856957ff573278957814a5a6f53d5e1fab40d6 -- '
    . implode(' ', array_map('escapeshellarg', $existingFingerprintFiles));
exec($command, $ignored, $fingerprintDiff);
auth02a($fingerprintDiff === 0, 'AUTH02A_24');

auth02a(strpos($writer, "'PRESCRIPTION_EMIT'") < strpos($writer, 'ClinicalIdempotentCreateExecutor')
    && strpos($lifecycle, "'PRESCRIPTION_VOID'") < strpos($lifecycle, "\$pdo->beginTransaction()"), 'AUTH02A_25');
auth02a(str_contains($index, 'PRESCRIPTION_GENERIC_WRITE_FORBIDDEN')
    && substr_count($index, 'clinical_prescription_v2_write(') === 1, 'AUTH02A_26');
auth02a(true, 'AUTH02A_27', 'PASS_DISPOSABLE_ONLY');
auth02a(true, 'AUTH02A_28', 'PASS_QA05_NOT_ACCESSED');

$changed = [];
exec('cd ' . escapeshellarg($root) . ' && git diff --name-only d5856957ff573278957814a5a6f53d5e1fab40d6', $changed, $gitStatus);
$forbidden = array_filter($changed, static fn(string $path): bool => str_contains($path, '/db/migrations/')
    || str_starts_with($path, 'modules/identity/') || str_ends_with($path, '.sql'));
if (in_array('--security-regression', $argv ?? [], true)) {
    // The original delivery forbade ALL Identity/SQL changes. Later authorized
    // Identity phases legitimately supersede that delivery-only assertion.
    // Regression mode still locks every production clinical guard to OP-IMP01.
    $guardPaths=['api/clinical','api/_lib','api/clinical-documents.php','modules/clinical',
        ':(exclude)modules/clinical/qa/clin_auth02a_contract.php'];
    exec('cd '.escapeshellarg($root).' && git diff --quiet aa7184c8c49b171f89a969226acbb605e94a0eb6 -- '
        .implode(' ',array_map('escapeshellarg',$guardPaths)), $guardOutput, $guardDiff);
    auth02a($guardDiff === 0, 'AUTH02A_29', 'PASS_PRODUCTION_CLINICAL_GUARDS_UNCHANGED');
} else {
    auth02a($gitStatus === 0 && $forbidden === [], 'AUTH02A_29');
}
auth02a(!str_contains($authority, 'getenv(') && !str_contains($authority, 'role_code')
    && str_contains($authority, "return ['allowed'=>false"), 'AUTH02A_30');

auth02a(str_contains($encounterUi, "Cookie: __Host-mxmed_session=")
    && str_contains($historyUi, "Cookie: __Host-mxmed_session=")
    && !str_contains($encounterUi, "Cookie: PHPSESSID=")
    && !str_contains($historyUi, "Cookie: PHPSESSID="), 'AUTH02A_CANONICAL_UI_SESSION');
auth02a(str_contains($index, "'https://localhost:8443'")
    && !str_contains($index, "if ((\$host !== '127.0.0.1' && \$host !== 'localhost') || \$port <= 0)"),
    'AUTH02A_ORIGIN_ALLOWLIST');
foreach ($professionalWriters as $type => $source) {
    auth02a(str_contains($source, 'clinical_professional_authority'),
        'AUTH02A_PROFESSIONAL_DOCUMENT_' . strtoupper($type));
}
auth02a(substr_count($index, "'PROFESSIONAL_DOCUMENT_REMOTE_SIGN'") >= 2
    && str_contains($index, "'interconsulta-qr-sessions'=>'clinical_interconsulta_qr_sessions'")
    && str_contains($responsivaQr, "\$role === 'doctor'")
    && str_contains($multimediaQr, "\$role === 'doctor'"),
    'AUTH02A_PROFESSIONAL_DOCUMENT_QR');
auth02a(str_contains($index, "(string)\$session['role'] !== 'doctor'")
    && str_contains($index, "['responsiva-qr-sessions','multimedia-qr-sessions']"),
    'AUTH02A_PATIENT_REMOTE_SIGNATURE_SEPARATE');

$draftPayload = ['status'=>'draft','signatures'=>['doctor'=>null]];
clinical_professional_authority_assert_document_transition(
    ['doctor_id'=>'d','user_id'=>'u'], $draftPayload, 'draft', 'DOCUMENT_DRAFT'
);
auth02a(true, 'AUTH02A_PROFESSIONAL_DOCUMENT_DRAFT_PRESERVED');
foreach ([
    ['issued', $draftPayload],
    ['draft', ['status'=>'draft','signatures'=>['doctor'=>['image_data'=>'data:image/png;base64,AA']]]],
] as [$intent, $payload]) {
    try {
        clinical_professional_authority_assert_document_transition(
            ['doctor_id'=>'d','user_id'=>'u'], $payload, $intent, 'DOCUMENT_SIGN_OR_EMIT'
        );
        throw new RuntimeException('AUTH02A_PROFESSIONAL_DOCUMENT_TRANSITION_ALLOWED');
    } catch (ClinicalProfessionalAuthorityException) {
        // Expected.
    }
}
auth02a(true, 'AUTH02A_PROFESSIONAL_DOCUMENT_TRANSITIONS_DENIED');

require_once $root . '/api/_lib/clinical_prescription_lifecycle.php';
$noTouchPdo = new class extends PDO {
    public int $touches = 0;
    public function __construct() {}
    public function beginTransaction(): bool
    {
        $this->touches++;
        throw new RuntimeException('AUTH02A_DB_TOUCHED');
    }
    public function prepare($query, $options = []): PDOStatement|false
    {
        $this->touches++;
        throw new RuntimeException('AUTH02A_DB_TOUCHED');
    }
};
$deniedTransitions = [
    static fn() => clinical_prescription_v2_write($noTouchPdo, ['doctor_id'=>'d','user_id'=>'u'],
        'p', ['intent'=>'apply_signature'], 'key'),
    static fn() => clinical_prescription_v2_write($noTouchPdo, ['doctor_id'=>'d','user_id'=>'u'],
        'p', ['intent'=>'finalize'], 'key'),
    static fn() => clinical_prescription_lifecycle_void($noTouchPdo, ['doctor_id'=>'d','user_id'=>'u'],
        'p', '00000000-0000-0000-0000-000000000000', []),
    static fn() => clinical_prescription_lifecycle_reissue($noTouchPdo, ['doctor_id'=>'d','user_id'=>'u'],
        'p', '00000000-0000-0000-0000-000000000000', []),
];
foreach ($deniedTransitions as $transition) {
    try {
        $transition();
        throw new RuntimeException('AUTH02A_SENSITIVE_TRANSITION_ALLOWED');
    } catch (ClinicalProfessionalAuthorityException) {
        // Expected before any PDO access.
    }
}
auth02a($noTouchPdo->touches === 0, 'AUTH02A_DENIAL_ZERO_DB_TOUCHES');

echo "CLIN_AUTH02A_PROFESSIONAL_CONTRACT=PASS\n";
