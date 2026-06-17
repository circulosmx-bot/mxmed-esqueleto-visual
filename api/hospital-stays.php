<?php
declare(strict_types=1);

/**
 * Endpoint: api/hospital-stays.php
 * Actions: current, start, close.
 * Minimal hospital stay API for the Manejo hospitalario tab.
 */

require_once __DIR__ . '/_lib/http.php';
require_once __DIR__ . '/_lib/db.php';

$action = trim((string)($_GET['action'] ?? ''));

try {
    $pdo = mxmed_pdo();
} catch (Throwable $e) {
    mxmed_json_response([
        'ok' => false,
        'data' => null,
        'error' => 'server_error',
        'message' => $e->getMessage(),
    ], 500);
}

function mxmed_hospital_stays_is_uuid_v4(string $value): bool {
    return preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{12}$/i', $value) === 1;
}

function mxmed_hospital_stays_is_canonical_patient_id(string $value): bool {
    if (mxmed_hospital_stays_is_uuid_v4($value)) {
        return true;
    }

    if (preg_match('/^p_[a-f0-9]{12}$/i', $value) === 1) {
        return true;
    }

    return preg_match('/^p_[A-Za-z0-9_-]{6,62}$/', $value) === 1;
}

function mxmed_hospital_stays_patient_exists(PDO $pdo, string $patientId): bool {
    $stmt = $pdo->prepare("SELECT patient_id FROM patients_patients WHERE patient_id = :patient_id LIMIT 1");
    $stmt->bindValue(':patient_id', $patientId, PDO::PARAM_STR);
    $stmt->execute();
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return is_array($row) && trim((string)($row['patient_id'] ?? '')) !== '';
}

function mxmed_hospital_stays_error(string $error, string $message, int $status): void {
    mxmed_json_response([
        'ok' => false,
        'data' => null,
        'error' => $error,
        'message' => $message,
    ], $status);
}

function mxmed_hospital_stays_read_patient_id($value): string {
    if (!is_scalar($value)) {
        mxmed_hospital_stays_error('invalid_params', 'patient_id must be canonical', 400);
    }

    $patientId = trim((string)$value);
    if ($patientId === '' || !mxmed_hospital_stays_is_canonical_patient_id($patientId)) {
        mxmed_hospital_stays_error('invalid_params', 'patient_id must be canonical', 400);
    }

    return $patientId;
}

function mxmed_hospital_stays_validate_patient_id(PDO $pdo, $value): string {
    $patientId = mxmed_hospital_stays_read_patient_id($value);

    if (!mxmed_hospital_stays_patient_exists($pdo, $patientId)) {
        mxmed_hospital_stays_error('not_found', 'patient not found', 404);
    }

    return $patientId;
}

function mxmed_hospital_stays_read_stay_id($value): string {
    if (!is_scalar($value)) {
        mxmed_hospital_stays_error('invalid_params', 'hospital_stay_id is required', 400);
    }

    $stayId = trim((string)$value);
    if ($stayId === '' || strlen($stayId) > 64 || preg_match('/^[A-Za-z0-9_-]+$/', $stayId) !== 1) {
        mxmed_hospital_stays_error('invalid_params', 'hospital_stay_id is required', 400);
    }

    return $stayId;
}

function mxmed_hospital_stays_clean_text($value, int $maxLength): ?string {
    if (!is_scalar($value)) {
        return null;
    }

    $text = preg_replace('/\s+/u', ' ', trim((string)$value));
    if (!is_string($text) || $text === '') {
        return null;
    }

    if (function_exists('mb_substr')) {
        return mb_substr($text, 0, $maxLength, 'UTF-8');
    }

    return substr($text, 0, $maxLength);
}

function mxmed_hospital_stays_row_to_stay(array $row): array {
    $stayId = (string)($row['hospital_stay_id'] ?? '');

    return [
        'id' => $stayId,
        'db_id' => isset($row['id']) ? (int)$row['id'] : null,
        'hospital_stay_id' => $stayId,
        'patient_id' => (string)($row['patient_id'] ?? ''),
        'service' => $row['service'] ?? null,
        'room' => $row['room'] ?? null,
        'bed' => $row['bed'] ?? null,
        'attending_user_id' => $row['attending_user_id'] ?? null,
        'admission_diagnosis' => $row['admission_diagnosis'] ?? null,
        'admission_reason' => $row['admission_reason'] ?? null,
        'started_at' => $row['started_at'] ?? null,
        'closed_at' => $row['closed_at'] ?? null,
        'created_at' => $row['created_at'] ?? null,
        'updated_at' => $row['updated_at'] ?? null,
    ];
}

function mxmed_hospital_stays_find_open(PDO $pdo, string $patientId, bool $forUpdate = false): ?array {
    $sql = "
        SELECT *
        FROM hospital_stays
        WHERE patient_id = :patient_id
          AND closed_at IS NULL
        ORDER BY started_at DESC, id DESC
        LIMIT 1
    ";
    if ($forUpdate) {
        $sql .= " FOR UPDATE";
    }

    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':patient_id', $patientId, PDO::PARAM_STR);
    $stmt->execute();
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    return is_array($row) ? $row : null;
}

function mxmed_hospital_stays_fetch_by_stay_id(PDO $pdo, string $patientId, string $stayId): ?array {
    $stmt = $pdo->prepare("
        SELECT *
        FROM hospital_stays
        WHERE patient_id = :patient_id
          AND hospital_stay_id = :hospital_stay_id
        LIMIT 1
    ");
    $stmt->bindValue(':patient_id', $patientId, PDO::PARAM_STR);
    $stmt->bindValue(':hospital_stay_id', $stayId, PDO::PARAM_STR);
    $stmt->execute();
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    return is_array($row) ? $row : null;
}

function mxmed_hospital_stays_generate_id(): string {
    return 'hs_' . bin2hex(random_bytes(12));
}

function mxmed_hospital_stays_success(?array $stay, string $message = 'ok', int $status = 200): void {
    mxmed_json_response([
        'ok' => true,
        'data' => $stay,
        'stay' => $stay,
        'error' => null,
        'message' => $message,
    ], $status);
}

try {
    if ($action === 'current') {
        $patientId = mxmed_hospital_stays_validate_patient_id($pdo, $_GET['patient_id'] ?? null);
        $row = mxmed_hospital_stays_find_open($pdo, $patientId);
        mxmed_hospital_stays_success($row ? mxmed_hospital_stays_row_to_stay($row) : null);
    }

    if ($action === 'start') {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            mxmed_hospital_stays_error('method_not_allowed', 'method not allowed', 405);
        }

        $body = mxmed_read_json_body();
        $patientId = mxmed_hospital_stays_validate_patient_id($pdo, $body['patient_id'] ?? null);

        $pdo->beginTransaction();
        $open = mxmed_hospital_stays_find_open($pdo, $patientId, true);
        if ($open) {
            $pdo->commit();
            mxmed_hospital_stays_success(mxmed_hospital_stays_row_to_stay($open));
        }

        $stayId = mxmed_hospital_stays_generate_id();
        $stmt = $pdo->prepare("
            INSERT INTO hospital_stays (
                hospital_stay_id,
                patient_id,
                service,
                room,
                bed,
                attending_user_id,
                admission_diagnosis,
                admission_reason,
                started_at
            ) VALUES (
                :hospital_stay_id,
                :patient_id,
                :service,
                :room,
                :bed,
                :attending_user_id,
                :admission_diagnosis,
                :admission_reason,
                NOW()
            )
        ");
        $stmt->execute([
            ':hospital_stay_id' => $stayId,
            ':patient_id' => $patientId,
            ':service' => mxmed_hospital_stays_clean_text($body['service'] ?? null, 160),
            ':room' => mxmed_hospital_stays_clean_text($body['room'] ?? null, 80),
            ':bed' => mxmed_hospital_stays_clean_text($body['bed'] ?? null, 80),
            ':attending_user_id' => mxmed_hospital_stays_clean_text($body['attending_user_id'] ?? null, 128),
            ':admission_diagnosis' => mxmed_hospital_stays_clean_text($body['admission_diagnosis'] ?? null, 4000),
            ':admission_reason' => mxmed_hospital_stays_clean_text($body['admission_reason'] ?? null, 4000),
        ]);

        $row = mxmed_hospital_stays_fetch_by_stay_id($pdo, $patientId, $stayId);
        $pdo->commit();

        mxmed_hospital_stays_success($row ? mxmed_hospital_stays_row_to_stay($row) : null, 'created', 201);
    }

    if ($action === 'close') {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            mxmed_hospital_stays_error('method_not_allowed', 'method not allowed', 405);
        }

        $body = mxmed_read_json_body();
        $patientId = mxmed_hospital_stays_validate_patient_id($pdo, $body['patient_id'] ?? null);
        $stayId = mxmed_hospital_stays_read_stay_id($body['hospital_stay_id'] ?? ($body['stay_id'] ?? ($body['id'] ?? null)));

        $pdo->beginTransaction();
        $stmt = $pdo->prepare("
            SELECT *
            FROM hospital_stays
            WHERE patient_id = :patient_id
              AND hospital_stay_id = :hospital_stay_id
              AND closed_at IS NULL
            LIMIT 1
            FOR UPDATE
        ");
        $stmt->bindValue(':patient_id', $patientId, PDO::PARAM_STR);
        $stmt->bindValue(':hospital_stay_id', $stayId, PDO::PARAM_STR);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!is_array($row)) {
            $pdo->rollBack();
            mxmed_hospital_stays_error('not_found', 'hospital stay not found', 404);
        }

        $stmt = $pdo->prepare("
            UPDATE hospital_stays
            SET closed_at = NOW()
            WHERE id = :id
              AND closed_at IS NULL
        ");
        $stmt->bindValue(':id', (int)$row['id'], PDO::PARAM_INT);
        $stmt->execute();

        $closed = mxmed_hospital_stays_fetch_by_stay_id($pdo, $patientId, $stayId);
        $pdo->commit();

        mxmed_hospital_stays_success($closed ? mxmed_hospital_stays_row_to_stay($closed) : null, 'closed');
    }

    mxmed_hospital_stays_error('not_found', 'action not found', 404);
} catch (Throwable $e) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
        try { $pdo->rollBack(); } catch (Throwable $rollbackError) { }
    }

    mxmed_json_response([
        'ok' => false,
        'data' => null,
        'error' => 'server_error',
        'message' => $e->getMessage(),
    ], 500);
}
