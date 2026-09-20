<?php
declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/api/_lib/clinical_note_capture_multipart_adapter.php';

function c21_check(bool $condition, string $label): void
{
    if (!$condition) {
        throw new RuntimeException($label);
    }
    echo "PASS {$label}\n";
}

$row = ['id' => 42, 'token' => 'raw-secret-token'];
$same = ['id' => 42, 'token' => 'raw-secret-token'];
$other = ['id' => 43, 'token' => 'different-secret-token'];
$key = clinical_note_capture_command_key($row);
c21_check($key === clinical_note_capture_command_key($same), 'stable retry command key');
c21_check($key !== clinical_note_capture_command_key($other), 'token-specific command key');
c21_check(strpos($key, 'raw-secret-token') === false, 'raw token absent from command key');
c21_check(strlen($key) <= 128 && preg_match('/^[A-Za-z0-9._:-]+$/', $key) === 1, 'canonical key validation shape');
c21_check(clinical_note_capture_actor_id($row) === 'note-capture-token.42', 'non-secret actor authority');

$payload = ['payload' => ['source' => 'nota_modal_qr_v1', 'note_capture_token' => 'raw-secret-token']];
$canonical = clinical_note_capture_canonical_payload($payload, $row);
c21_check(!array_key_exists('note_capture_token', $canonical['payload']), 'raw token excluded from canonical metadata');
c21_check($canonical['payload']['note_capture_token_ref'] === 'row:42', 'durable non-secret token reference');
c21_check($payload['payload']['note_capture_token'] === 'raw-secret-token', 'legacy payload input unchanged');

foreach ([[], ['id' => 1, 'token' => '']] as $invalid) {
    try {
        clinical_note_capture_command_key($invalid);
        throw new RuntimeException('invalid token accepted');
    } catch (InvalidArgumentException $error) {
        c21_check($error->getMessage() === 'NOTE_CAPTURE_TOKEN_INVALID', 'invalid token fails closed');
    }
}

echo "MULTI06B_C21_ADAPTER_QA=PASS; ANY_DATABASE_CONNECTED=false\n";
