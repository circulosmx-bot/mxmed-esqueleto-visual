<?php
declare(strict_types=1);

require_once __DIR__ . '/clinical_encounter_multipart_adapter.php';

/** Stable opaque command authority; the raw capture token never enters V1 metadata. */
function clinical_note_capture_command_key(array $tokenRow): string
{
    $token = trim((string)($tokenRow['token'] ?? ''));
    if ($token === '') {
        throw new InvalidArgumentException('NOTE_CAPTURE_TOKEN_INVALID');
    }
    return 'note-capture.' . hash('sha256', $token);
}

function clinical_note_capture_actor_id(array $tokenRow): string
{
    $id = (int)($tokenRow['id'] ?? 0);
    if ($id <= 0) {
        throw new InvalidArgumentException('NOTE_CAPTURE_TOKEN_INVALID');
    }
    return 'note-capture-token.' . $id;
}

/** Keep a durable non-secret reference without copying the bearer token into the document. */
function clinical_note_capture_canonical_payload(array $payload, array $tokenRow): array
{
    $data = is_array($payload['payload'] ?? null) ? $payload['payload'] : [];
    unset($data['note_capture_token']);
    $data['note_capture_token_ref'] = 'row:' . (int)($tokenRow['id'] ?? 0);
    $payload['payload'] = $data;
    return $payload;
}

function clinical_note_capture_multipart_execute(
    PDO $pdo,
    array $encounter,
    array $tokenRow,
    array $payload,
    array $files,
    string $createOperation,
    string $policyOperation,
    string $documentClass,
    array $policyContext
): array {
    $doctorId = trim((string)($encounter['doctor_id'] ?? ''));
    if ($doctorId === '') {
        throw new RuntimeException('DOCUMENT_CONTEXT_MISMATCH');
    }
    $doctor = [
        'doctor_id' => $doctorId,
        'user_id' => clinical_note_capture_actor_id($tokenRow),
    ];
    return clinical_encounter_multipart_execute(
        $pdo,
        $encounter,
        $doctor,
        clinical_note_capture_canonical_payload($payload, $tokenRow),
        $createOperation,
        $policyOperation,
        $documentClass,
        $policyContext,
        $files,
        clinical_note_capture_command_key($tokenRow)
    );
}
