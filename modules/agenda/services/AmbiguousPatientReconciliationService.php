<?php
declare(strict_types=1);

namespace Agenda\Services;

use DateTimeImmutable;
use DateTimeZone;
use PDO;
use RuntimeException;
use Patients\Services\PublicBookingPatientIdentityResolver;

require_once __DIR__ . '/../../patients/services/PublicBookingPatientIdentityResolver.php';

/**
 * Private, pre-clinical reconciliation boundary for public bookings whose
 * initial identity result was ambiguous. It never merges or deletes patients.
 */
final class AmbiguousPatientReconciliationService
{
    private const FLOW_TABLE = 'agenda_public_appointment_flows';
    private const APPOINTMENTS_TABLE = 'agenda_appointments';
    private const EVENTS_TABLE = 'agenda_appointment_events';

    public function __construct(private PDO $pdo)
    {
    }

    public function review(string $appointmentId, array $actor): array
    {
        $appointment = $this->findAppointment($appointmentId, false);
        $this->assertPrivateDoctorScope($appointment, $actor);
        $flow = $this->findFlow($appointmentId, false);
        if ($flow === null) {
            throw new ReconciliationException('not_found', 'Cita no disponible para conciliación.');
        }
        return $this->buildReview($appointment, $flow);
    }

    public function resolve(string $appointmentId, array $input, array $actor): array
    {
        $action = trim((string)($input['action'] ?? ''));
        if (!in_array($action, ['keep_new', 'relink_existing'], true)) {
            throw new ReconciliationException('invalid_params', 'Acción de conciliación inválida.');
        }

        $this->pdo->beginTransaction();
        try {
            $appointment = $this->findAppointment($appointmentId, true);
            $this->assertPrivateDoctorScope($appointment, $actor);
            $flow = $this->findFlow($appointmentId, true);
            if ($flow === null) {
                throw new ReconciliationException('not_found', 'Cita no disponible para conciliación.');
            }

            $payload = $this->decodePayload($flow);
            $identity = is_array($payload['patient_identity_resolution'] ?? null)
                ? $payload['patient_identity_resolution']
                : [];
            $existing = is_array($identity['admin_reconciliation'] ?? null)
                ? $identity['admin_reconciliation']
                : null;

            $currentPatientId = trim((string)($appointment['patient_id'] ?? ''));
            if ($currentPatientId === '') {
                throw new ReconciliationException('conflict', 'La cita no tiene un paciente conciliable.');
            }

            if ($existing !== null) {
                $result = $this->idempotentResult($existing, $action, $input, $appointment);
                $this->pdo->commit();
                return $result;
            }
            if (($identity['status'] ?? '') !== 'ambiguous') {
                throw new ReconciliationException('conflict', 'Este caso ya no requiere conciliación de identidad.');
            }

            $previousPatientId = $currentPatientId;
            $resultingPatientId = $previousPatientId;
            if ($action === 'relink_existing') {
                $selectedPatientId = trim((string)($input['candidate_patient_id'] ?? ''));
                if ($selectedPatientId === '') {
                    throw new ReconciliationException('invalid_params', 'Selecciona un paciente existente.');
                }
                $this->assertNoClinicalData($previousPatientId);
                $candidateIds = array_column($this->eligibleCandidates($appointment, $payload, $previousPatientId), 'patient_id');
                if (!in_array($selectedPatientId, $candidateIds, true)) {
                    throw new ReconciliationException('forbidden', 'El paciente seleccionado ya no es elegible para esta conciliación.');
                }
                $this->updateAppointmentPatientId($appointmentId, $selectedPatientId);
                $resultingPatientId = $selectedPatientId;
            }

            $now = $this->now();
            $identity['admin_reconciliation'] = [
                'action' => $action,
                'status' => $action === 'keep_new' ? 'kept_new' : 'relinked_existing',
                'resolved_at' => $now,
                'resolved_by' => $this->actorReference($actor),
                'reason' => 'ambiguous_patient_identity_reconciliation',
                'previous_patient_id' => $previousPatientId,
                'resulting_patient_id' => $resultingPatientId,
            ];
            $payload['patient_identity_resolution'] = $identity;
            $this->updateFlowPayload((int)$flow['flow_id'], $payload);
            $this->appendAuditEvent($appointmentId, $action, $previousPatientId, $resultingPatientId, $actor, $now);
            $this->pdo->commit();

            return [
                'appointment_id' => $appointmentId,
                'action' => $action,
                'status' => $identity['admin_reconciliation']['status'],
                'previous_patient_id' => $previousPatientId,
                'resulting_patient_id' => $resultingPatientId,
                'idempotent' => false,
            ];
        } catch (\Throwable $error) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $error;
        }
    }

    private function buildReview(array $appointment, array $flow): array
    {
        $payload = $this->decodePayload($flow);
        $identity = is_array($payload['patient_identity_resolution'] ?? null)
            ? $payload['patient_identity_resolution']
            : [];
        $reconciliation = is_array($identity['admin_reconciliation'] ?? null)
            ? $identity['admin_reconciliation']
            : null;
        $ambiguous = ($identity['status'] ?? '') === 'ambiguous';
        $currentPatientId = trim((string)($appointment['patient_id'] ?? ''));

        return [
            'appointment_id' => (string)$appointment['appointment_id'],
            'patient_type' => $this->patientTypeLabel((string)($payload['patient_type'] ?? '')),
            'identity_status' => $reconciliation !== null
                ? $this->reconciliationStatusLabel((string)($reconciliation['status'] ?? ''))
                : ($ambiguous ? 'Revisión necesaria' : 'Coincidencia confirmada'),
            'requires_review' => $ambiguous && $reconciliation === null,
            'candidates' => ($ambiguous && $reconciliation === null)
                ? $this->eligibleCandidates($appointment, $payload, $currentPatientId)
                : [],
            'resolution' => $reconciliation === null ? null : [
                'action' => (string)($reconciliation['action'] ?? ''),
                'status' => $this->reconciliationStatusLabel((string)($reconciliation['status'] ?? '')),
                'resolved_at' => (string)($reconciliation['resolved_at'] ?? ''),
            ],
        ];
    }

    private function eligibleCandidates(array $appointment, array $payload, string $excludePatientId): array
    {
        $patient = is_array($payload['patient'] ?? null) ? $payload['patient'] : [];
        $resolver = new PublicBookingPatientIdentityResolver($this->pdo);
        $candidates = $resolver->eligibleReviewCandidates(
            (string)$appointment['doctor_id'],
            $patient,
            $excludePatientId
        );
        return array_map(function (array $candidate): array {
            return [
                'patient_id' => $candidate['patient_id'],
                'full_name' => $candidate['display_name'],
                'birthdate' => $candidate['birthdate'] !== '' ? $candidate['birthdate'] : null,
                'sex' => $candidate['sex'],
                'phone_masked' => $this->maskPhone($candidate['phones'][0] ?? ''),
                'email_masked' => $this->maskEmail($candidate['emails'][0] ?? ''),
                'doctor_relationship' => 'active',
                'patient_status' => 'active',
            ];
        }, $candidates);
    }

    private function idempotentResult(array $existing, string $action, array $input, array $appointment): array
    {
        $existingAction = trim((string)($existing['action'] ?? ''));
        $resultingPatientId = trim((string)($existing['resulting_patient_id'] ?? ''));
        if ($existingAction === $action && ($action === 'keep_new' || $resultingPatientId === trim((string)($input['candidate_patient_id'] ?? '')))) {
            return [
                'appointment_id' => (string)$appointment['appointment_id'],
                'action' => $existingAction,
                'status' => (string)($existing['status'] ?? ''),
                'previous_patient_id' => (string)($existing['previous_patient_id'] ?? ''),
                'resulting_patient_id' => $resultingPatientId,
                'idempotent' => true,
            ];
        }
        throw new ReconciliationException('conflict', 'Este caso ya fue conciliado por otro usuario.');
    }

    private function assertPrivateDoctorScope(array $appointment, array $actor): void
    {
        if (($actor['is_authoritative'] ?? false) !== true || !in_array($actor['actor_role'] ?? '', ['doctor', 'operator'], true)) {
            throw new ReconciliationException('unauthorized', 'Autorización administrativa requerida.');
        }
        $doctorId = trim((string)($actor['doctor_id'] ?? ''));
        if ($doctorId === '' || $doctorId !== trim((string)($appointment['doctor_id'] ?? ''))) {
            throw new ReconciliationException('forbidden', 'Cita fuera del alcance del médico.');
        }
    }

    private function assertNoClinicalData(string $patientId): void
    {
        foreach ([
            'clinical_encounters', 'clinical_record_entries', 'clinical_consents',
            'clinical_cases', 'clinical_documents', 'patients_consents',
            'agenda_patient_flags', 'agenda_patient_incidents',
        ] as $table) {
            if (!$this->tableHasPatientId($table)) {
                continue;
            }
            $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM ' . $table . ' WHERE patient_id = :patient_id');
            $stmt->execute(['patient_id' => $patientId]);
            if ((int)$stmt->fetchColumn() > 0) {
                throw new ReconciliationException('clinical_data_exists', 'La identidad ya tiene información clínica asociada y requiere una conciliación avanzada.');
            }
        }
    }

    private function findAppointment(string $appointmentId, bool $forUpdate): array
    {
        $sql = 'SELECT * FROM ' . self::APPOINTMENTS_TABLE . ' WHERE appointment_id = :appointment_id LIMIT 1' . $this->forUpdate($forUpdate);
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['appointment_id' => $appointmentId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row)) {
            throw new ReconciliationException('not_found', 'Cita no encontrada.');
        }
        return $row;
    }

    private function findFlow(string $appointmentId, bool $forUpdate): ?array
    {
        $sql = 'SELECT * FROM ' . self::FLOW_TABLE . ' WHERE appointment_id = :appointment_id LIMIT 1' . $this->forUpdate($forUpdate);
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['appointment_id' => $appointmentId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $row : null;
    }

    private function updateAppointmentPatientId(string $appointmentId, string $patientId): void
    {
        $stmt = $this->pdo->prepare('UPDATE ' . self::APPOINTMENTS_TABLE . ' SET patient_id = :patient_id WHERE appointment_id = :appointment_id');
        $stmt->execute(['patient_id' => $patientId, 'appointment_id' => $appointmentId]);
    }

    private function updateFlowPayload(int $flowId, array $payload): void
    {
        $json = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        $stmt = $this->pdo->prepare('UPDATE ' . self::FLOW_TABLE . ' SET payload_json = :payload_json, updated_at = :updated_at WHERE flow_id = :flow_id');
        $stmt->execute(['payload_json' => $json, 'updated_at' => $this->now(), 'flow_id' => $flowId]);
    }

    private function appendAuditEvent(string $appointmentId, string $action, string $previousPatientId, string $resultingPatientId, array $actor, string $at): void
    {
        if (!$this->tableExists(self::EVENTS_TABLE)) {
            throw new RuntimeException('appointment events not ready');
        }
        $notes = json_encode([
            'action' => $action,
            'reason' => 'ambiguous_patient_identity_reconciliation',
            'previous_patient_id' => $previousPatientId,
            'resulting_patient_id' => $resultingPatientId,
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        $stmt = $this->pdo->prepare(
            'INSERT INTO ' . self::EVENTS_TABLE . ' (event_id, appointment_id, event_type, timestamp, actor_role, actor_id, channel_origin, notes)
             VALUES (:event_id, :appointment_id, :event_type, :timestamp, :actor_role, :actor_id, :channel_origin, :notes)'
        );
        $stmt->execute([
            'event_id' => 'air_' . bin2hex(random_bytes(12)),
            'appointment_id' => $appointmentId,
            'event_type' => 'patient_identity_reconciled',
            'timestamp' => $at,
            'actor_role' => (string)$actor['actor_role'],
            'actor_id' => (string)($actor['actor_id'] ?? $actor['user_id'] ?? ''),
            'channel_origin' => 'admin_agenda',
            'notes' => $notes,
        ]);
    }

    private function decodePayload(array $flow): array
    {
        $decoded = json_decode((string)($flow['payload_json'] ?? ''), true);
        return is_array($decoded) ? $decoded : [];
    }

    private function tableHasPatientId(string $table): bool
    {
        return $this->tableExists($table) && $this->columnExists($table, 'patient_id');
    }

    private function tableExists(string $table): bool
    {
        if ($this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') {
            $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM sqlite_master WHERE type = 'table' AND name = :table");
            $stmt->execute(['table' => $table]);
            return (int)$stmt->fetchColumn() > 0;
        }
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = :table');
        $stmt->execute(['table' => $table]);
        return (int)$stmt->fetchColumn() > 0;
    }

    private function columnExists(string $table, string $column): bool
    {
        if ($this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') {
            foreach ($this->pdo->query('PRAGMA table_info(' . $table . ')')->fetchAll(PDO::FETCH_ASSOC) as $row) {
                if (($row['name'] ?? '') === $column) return true;
            }
            return false;
        }
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = :table AND column_name = :column');
        $stmt->execute(['table' => $table, 'column' => $column]);
        return (int)$stmt->fetchColumn() > 0;
    }

    private function forUpdate(bool $requested): string
    {
        return $requested && $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME) !== 'sqlite' ? ' FOR UPDATE' : '';
    }

    private function patientTypeLabel(string $value): string
    {
        return $value === 'follow_up' ? 'Ya ha consultado antes' : 'Primera consulta';
    }

    private function reconciliationStatusLabel(string $value): string
    {
        return $value === 'kept_new' ? 'Paciente nuevo' : 'Paciente existente vinculado';
    }

    private function actorReference(array $actor): string
    {
        return trim((string)($actor['actor_id'] ?? $actor['user_id'] ?? 'admin'));
    }

    private function now(): string
    {
        return (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format('Y-m-d H:i:s');
    }

    private function maskPhone(string $phone): ?string
    {
        $digits = preg_replace('/\D+/', '', $phone) ?? '';
        return $digits === '' ? null : '*** *** ' . substr($digits, -4);
    }

    private function maskEmail(string $email): ?string
    {
        $email = trim($email);
        if ($email === '' || !str_contains($email, '@')) return null;
        [$local, $domain] = explode('@', $email, 2);
        return substr($local, 0, 1) . '***@' . $domain;
    }
}

final class ReconciliationException extends RuntimeException
{
    public function __construct(public readonly string $codeName, string $message)
    {
        parent::__construct($message);
    }
}
