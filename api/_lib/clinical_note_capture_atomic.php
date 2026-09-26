<?php
declare(strict_types=1);

// Same connection-scoped MySQL mutex pattern as Agenda. It survives the canonical
// writer's internal commits; disconnect releases it. Never use the bearer as a name.
function clinical_note_capture_lock(PDO $pdo, string $token): string
{
    $database = (string)$pdo->query('SELECT DATABASE()')->fetchColumn();
    // Token lookup uses the table's collation. Lock the persisted identity so
    // case/accent aliases cannot take different mutexes for the same row.
    $lookup = $pdo->prepare('SELECT id FROM clinical_note_capture_tokens WHERE token=? LIMIT 1');
    $lookup->execute([trim($token)]);
    $id = $lookup->fetchColumn();
    $identity = $id !== false ? 'row:' . $id : 'missing:' . hash('sha256', trim($token));
    $name = 'capture:' . substr(hash('sha256', $database . '|' . $identity), 0, 56);
    $stmt = $pdo->prepare('SELECT GET_LOCK(?, 10)');
    $stmt->execute([$name]);
    if ((int)$stmt->fetchColumn() !== 1) {
        throw new RuntimeException('CAPTURE_TOKEN_BUSY');
    }
    return $name;
}

function clinical_note_capture_unlock(PDO $pdo, string $name): void
{
    $stmt = $pdo->prepare('SELECT RELEASE_LOCK(?)');
    $stmt->execute([$name]);
}

// Called in the SAME transaction that inserts the document, including C21's
// canonical resource callback. An ambiguous COMMIT cannot split these effects.
function clinical_note_capture_complete_document(PDO $pdo, array $row, int $documentId, string $uuid, string $preview = ''): void
{
    if (!$pdo->inTransaction() || $documentId <= 0 || $uuid === '') {
        throw new RuntimeException('CAPTURE_DOCUMENT_TRANSACTION_REQUIRED');
    }
    $stmt = $pdo->prepare("UPDATE clinical_note_capture_tokens SET status='uploaded',
        uploaded_at=UTC_TIMESTAMP(), updated_at=UTC_TIMESTAMP(), document_id=?, document_uuid=?, preview_url=?
        WHERE id=? AND status='pending'");
    $stmt->execute([$documentId, $uuid, $preview !== '' ? $preview : null, (int)$row['id']]);
    if ($stmt->rowCount() !== 1) throw new RuntimeException('CAPTURE_TOKEN_TERMINAL');
}

// Legacy gateway does not own a transaction. Caller prepares schema before this
// boundary and holds the token mutex. Rollback leaves pending only with no document.
function clinical_note_capture_legacy_transaction(PDO $pdo, array $row, callable $writer): array
{
    if ($pdo->inTransaction()) throw new RuntimeException('CAPTURE_OUTER_TRANSACTION_NOT_ALLOWED');
    if (!$pdo->beginTransaction()) throw new RuntimeException('CAPTURE_TRANSACTION_BEGIN_FAILED');
    try {
        $document = $writer();
        clinical_note_capture_complete_document($pdo, $row,
            (int)($document['document_db_id'] ?? 0),
            (string)($document['document_id'] ?? $document['document_uuid'] ?? ''),
            clinical_note_capture_extract_preview_url($document));
        if (!$pdo->commit()) throw new RuntimeException('CAPTURE_TRANSACTION_COMMIT_UNKNOWN');
        return $document;
    } catch (Throwable $e) {
        // No compensating reset: if COMMIT's result is unknown the next locked
        // read observes either the entire document+token commit or neither.
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
}
