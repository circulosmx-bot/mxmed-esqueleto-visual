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
    private const PATIENTS_TABLE = 'patients_patients';
    private const LINKS_TABLE = 'patients_doctor_links';

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

    /** Deactivates only the exact preclinical source left by a completed relink. */
    public function cleanup(string $appointmentId, array $input, array $actor): array
    {
        if (($input['confirmed'] ?? false) !== true) {
            throw new ReconciliationException('confirmation_required', 'Confirma la desactivación del registro duplicado.');
        }
        $this->pdo->beginTransaction();
        try {
            $appointment = $this->findAppointment($appointmentId, true);
            $this->assertPrivateDoctorScope($appointment, $actor);
            $flow = $this->findFlow($appointmentId, true);
            if ($flow === null) throw new ReconciliationException('not_found', 'Cita no disponible para conciliación.');
            [$payload, $identity, $reconciliation, $sourcePatientId, $targetPatientId] = $this->cleanupContext($appointment, $flow, $input);
            $patient = $this->findPatient($sourcePatientId, true);
            $links = $this->findDoctorLinks($sourcePatientId, true);
            $cleanup = is_array($reconciliation['cleanup'] ?? null) ? $reconciliation['cleanup'] : null;
            if (($cleanup['status'] ?? '') === 'deactivated' && ($patient['status'] ?? '') === 'inactive' && $this->allLinksInactive($links)) {
                $this->pdo->commit();
                return ['appointment_id' => $appointmentId, 'status' => 'deactivated', 'idempotent' => true];
            }
            $this->assertSafeOrphan($sourcePatientId, (string)$appointment['doctor_id'], $links);
            if (($patient['status'] ?? '') !== 'active') throw new ReconciliationException('conflict', 'El registro ya no está disponible para esta desactivación.');
            $now = $this->now();
            $this->deactivatePatient($sourcePatientId, $now);
            $this->endDoctorLink((string)$links[0]['link_id'], $now);
            $reconciliation['cleanup'] = [
                'status' => 'deactivated', 'action' => 'deactivate_preclinical_duplicate',
                'cleanup_at' => $now, 'cleanup_by' => $this->actorReference($actor),
                'source_patient_id' => $sourcePatientId, 'target_patient_id' => $targetPatientId,
                'reason' => 'safe_preclinical_orphan_after_relink',
            ];
            $identity['admin_reconciliation'] = $reconciliation;
            $payload['patient_identity_resolution'] = $identity;
            $this->updateFlowPayload((int)$flow['flow_id'], $payload);
            $this->appendAuditEvent($appointmentId, 'deactivate_preclinical_duplicate', $sourcePatientId, $targetPatientId, $actor, $now, 'preclinical_duplicate_deactivated', 'safe_preclinical_orphan_after_relink');
            $this->pdo->commit();
            return ['appointment_id' => $appointmentId, 'status' => 'deactivated', 'idempotent' => false];
        } catch (\Throwable $error) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
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

        $cleanup = $this->cleanupReview($appointment, $identity, $reconciliation);
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
            'cleanup' => $cleanup,
        ];
    }

    private function cleanupReview(array $appointment, array $identity, ?array $reconciliation): array
    {
        if ($reconciliation === null || ($reconciliation['action'] ?? '') !== 'relink_existing') return ['eligible' => false, 'status' => ''];
        if (($reconciliation['cleanup']['status'] ?? '') === 'deactivated') return ['eligible' => false, 'status' => 'Registro duplicado desactivado'];
        try {
            $source = trim((string)($reconciliation['previous_patient_id'] ?? ''));
            $target = trim((string)($reconciliation['resulting_patient_id'] ?? ''));
            if (($identity['status'] ?? '') !== 'ambiguous' || $source === '' || $target === '' || $target !== (string)$appointment['patient_id']) throw new RuntimeException();
            $patient = $this->findPatient($source, false);
            $links = $this->findDoctorLinks($source, false);
            if (($patient['status'] ?? '') !== 'active') throw new RuntimeException();
            $this->assertSafeOrphan($source, (string)$appointment['doctor_id'], $links);
            return ['eligible' => true, 'status' => ''];
        } catch (\Throwable) {
            return ['eligible' => false, 'status' => 'La desactivación simple ya no es segura; requiere conciliación avanzada.'];
        }
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

    /** Re-derives the source from immutable reconciliation state; browser ids are never trusted. */
    private function cleanupContext(array $appointment, array $flow, array $input): array
    {
        $payload = $this->decodePayload($flow);
        $identity = is_array($payload['patient_identity_resolution'] ?? null) ? $payload['patient_identity_resolution'] : [];
        $reconciliation = is_array($identity['admin_reconciliation'] ?? null) ? $identity['admin_reconciliation'] : [];
        $source = trim((string)($reconciliation['previous_patient_id'] ?? ''));
        $target = trim((string)($reconciliation['resulting_patient_id'] ?? ''));
        if (($identity['status'] ?? '') !== 'ambiguous' || ($reconciliation['action'] ?? '') !== 'relink_existing' || $source === '' || $target === '' || $source === $target || $target !== (string)$appointment['patient_id']) {
            throw new ReconciliationException('conflict', 'Este registro no cumple los requisitos para desactivación segura.');
        }
        $requested = trim((string)($input['source_patient_id'] ?? ''));
        if ($requested !== '' && $requested !== $source) throw new ReconciliationException('forbidden', 'El registro indicado no corresponde a esta conciliación.');
        return [$payload, $identity, $reconciliation, $source, $target];
    }

    private function findPatient(string $patientId, bool $forUpdate): array
    {
        if (!$this->tableExists(self::PATIENTS_TABLE)) throw new ReconciliationException('conflict', 'El registro no está disponible para esta desactivación.');
        $stmt = $this->pdo->prepare('SELECT * FROM ' . self::PATIENTS_TABLE . ' WHERE patient_id = :patient_id LIMIT 1' . $this->forUpdate($forUpdate));
        $stmt->execute(['patient_id' => $patientId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row)) throw new ReconciliationException('not_found', 'Registro de paciente no encontrado.');
        return $row;
    }

    private function findDoctorLinks(string $patientId, bool $forUpdate): array
    {
        if (!$this->tableExists(self::LINKS_TABLE)) throw new ReconciliationException('conflict', 'No es posible validar la relación profesional del registro.');
        $stmt = $this->pdo->prepare('SELECT * FROM ' . self::LINKS_TABLE . ' WHERE patient_id = :patient_id' . $this->forUpdate($forUpdate));
        $stmt->execute(['patient_id' => $patientId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Locks/reference-checks every known ownership surface before mutation. */
    private function assertSafeOrphan(string $patientId, string $doctorId, array $links): void
    {
        $activeLinks = array_values(array_filter($links, fn(array $link): bool => ($link['status'] ?? '') === 'active' && empty($link['ended_at'])));
        if (count($links) !== 1 || count($activeLinks) !== 1 || (string)($activeLinks[0]['doctor_id'] ?? '') !== $doctorId) $this->unsafeOrphan();
        foreach ([
            self::APPOINTMENTS_TABLE, 'agenda_patient_flags', 'agenda_patient_incidents', 'agenda_waitlist_entries',
            'clinical_encounters', 'clinical_record_entries', 'clinical_consents', 'clinical_cases', 'clinical_documents', 'hospital_stays',
            'patients_profiles', 'patients_addresses', 'patients_consents',
            'clinical_patient_identity_bridge', 'patient_identity_resolutions', 'patient_identity_legacy_links',
        ] as $table) {
            if ($this->tableHasPatientId($table) && $this->hasReference($table, 'patient_id', $patientId)) $this->unsafeOrphan();
        }
        foreach ([
            ['clinical_patient_identity_bridge', 'canonical_patient_id'], ['patient_identity_resolutions', 'resolved_patient_id'], ['patient_identity_legacy_links', 'canonical_patient_id'],
        ] as [$table, $column]) {
            if ($this->tableExists($table) && $this->columnExists($table, $column) && $this->hasReference($table, $column, $patientId)) $this->unsafeOrphan();
        }
        if ($this->tableExists('clinical_case_items') && $this->tableExists('clinical_cases') && $this->columnExists('clinical_case_items', 'case_id') && $this->columnExists('clinical_cases', 'case_id')) {
            $sql = 'SELECT ci.case_id FROM clinical_case_items ci JOIN clinical_cases cc ON cc.case_id = ci.case_id WHERE cc.patient_id = :patient_id LIMIT 1' . $this->forUpdate($this->pdo->inTransaction());
            $stmt = $this->pdo->prepare($sql); $stmt->execute(['patient_id' => $patientId]);
            if ($stmt->fetchColumn() !== false) $this->unsafeOrphan();
        }
    }

    private function hasReference(string $table, string $column, string $patientId): bool
    {
        $stmt = $this->pdo->prepare('SELECT ' . $column . ' FROM ' . $table . ' WHERE ' . $column . ' = :patient_id LIMIT 1' . $this->forUpdate($this->pdo->inTransaction()));
        $stmt->execute(['patient_id' => $patientId]);
        return $stmt->fetchColumn() !== false;
    }

    private function unsafeOrphan(): never
    {
        throw new ReconciliationException('unsafe_orphan', 'El registro ya tiene información asociada y no puede desactivarse mediante esta acción.');
    }

    private function allLinksInactive(array $links): bool
    {
        return count($links) === 1 && ($links[0]['status'] ?? '') === 'inactive' && !empty($links[0]['ended_at']);
    }

    private function deactivatePatient(string $patientId, string $at): void
    {
        $columns = ['status = :status']; $params = ['status' => 'inactive', 'patient_id' => $patientId];
        if ($this->columnExists(self::PATIENTS_TABLE, 'updated_at')) { $columns[] = 'updated_at = :updated_at'; $params['updated_at'] = $at; }
        $stmt = $this->pdo->prepare('UPDATE ' . self::PATIENTS_TABLE . ' SET ' . implode(', ', $columns) . ' WHERE patient_id = :patient_id AND status = :expected_status');
        $params['expected_status'] = 'active'; $stmt->execute($params);
        if ($stmt->rowCount() !== 1) throw new ReconciliationException('conflict', 'El registro ya no está disponible para esta desactivación.');
    }

    private function endDoctorLink(string $linkId, string $at): void
    {
        $stmt = $this->pdo->prepare('UPDATE ' . self::LINKS_TABLE . ' SET status = :status, ended_at = :ended_at WHERE link_id = :link_id AND status = :expected_status AND ended_at IS NULL');
        $stmt->execute(['status' => 'inactive', 'ended_at' => $at, 'link_id' => $linkId, 'expected_status' => 'active']);
        if ($stmt->rowCount() !== 1) throw new ReconciliationException('conflict', 'La relación profesional ya no está disponible para esta desactivación.');
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

    private function appendAuditEvent(string $appointmentId, string $action, string $previousPatientId, string $resultingPatientId, array $actor, string $at, string $eventType = 'patient_identity_reconciled', string $reason = 'ambiguous_patient_identity_reconciliation'): void
    {
        if (!$this->tableExists(self::EVENTS_TABLE)) {
            throw new RuntimeException('appointment events not ready');
        }
        $notes = json_encode([
            'action' => $action,
            'reason' => $reason,
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
            'event_type' => $eventType,
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
