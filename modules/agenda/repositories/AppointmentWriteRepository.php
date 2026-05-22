<?php
namespace Agenda\Repositories;

use Agenda\Repositories\PatientFlagsWriteRepository;
use Agenda\Repositories\PatientIncidentsWriteRepository;
use Agenda\Services\ClinicalEncounterBridge;
use DateTime;
use DateTimeZone;
use PDO;
use PDOException;
use RuntimeException;
use Throwable;

require_once __DIR__ . '/../repositories/PatientFlagsWriteRepository.php';
require_once __DIR__ . '/../repositories/PatientIncidentsWriteRepository.php';
require_once __DIR__ . '/../services/ClinicalEncounterBridge.php';

class AppointmentWriteRepository
{
    private PDO $pdo;
    private ?string $appointmentsTable = null;
    private ?string $eventsTable = null;
    private ?string $appointmentPk = null;
    private array $columnsCache = [];
    private ?PatientFlagsWriteRepository $patientFlagsRepository = null;
    private ?PatientIncidentsWriteRepository $patientIncidentsRepository = null;
    private ?ClinicalEncounterBridge $clinicalEncounterBridge = null;
    private const TIMEZONE = 'America/Mexico_City';
    private const LATE_CANCEL_THRESHOLD_MINUTES = 1080;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $config = $this->loadConfig();
        $this->appointmentsTable = $this->sanitizeIdentifier($config['appointments_table'] ?? '');
        $this->eventsTable = $this->sanitizeIdentifier($config['appointment_events_table'] ?? '');
        $this->appointmentPk = $this->sanitizeIdentifier($config['appointment_pk'] ?? 'appointment_id');
        $patientFlagsDriven = trim((string)($config['patient_flags_table'] ?? ''));
        if ($patientFlagsDriven !== '') {
            try {
                $this->patientFlagsRepository = new PatientFlagsWriteRepository($this->pdo);
            } catch (RuntimeException $e) {
                // swallow: flags table missing or not ready, cancel should continue
                $this->patientFlagsRepository = null;
            }
        }
        $patientIncidentsDriven = trim((string)($config['patient_incidents_table'] ?? ''));
        if ($patientIncidentsDriven !== '') {
            try {
                $this->patientIncidentsRepository = new PatientIncidentsWriteRepository($this->pdo);
            } catch (RuntimeException $e) {
                // swallow: incidents table missing or not ready, write flows must continue
                $this->patientIncidentsRepository = null;
            }
        }
        $this->clinicalEncounterBridge = new ClinicalEncounterBridge($config);
    }

    public function createAppointment(array $payload): array
    {
        $this->ensureAppointmentsTable();
        $this->ensureEventsTable();

        $appointmentId = $this->generateId();
        $createdAt = (new DateTime('now', new DateTimeZone(self::TIMEZONE)))->format('Y-m-d H:i:s');
        $appointmentData = [
            'appointment_id' => $appointmentId,
            'doctor_id' => $payload['doctor_id'],
            'consultorio_id' => $payload['consultorio_id'],
            'patient_id' => $payload['patient_id'] ?? null,
            'start_at' => $payload['start_at'],
            'end_at' => $payload['end_at'],
            'modality' => $payload['modality'],
            'status' => $payload['status'] ?? 'tentative',
            'channel_origin' => $payload['channel_origin'],
            'created_by_role' => $payload['created_by_role'],
            'created_by_id' => $payload['created_by_id'],
            'operator_id' => $payload['operator_id'] ?? null,
            'operator_slot' => $payload['operator_slot'] ?? null,
            'operator_number' => $payload['operator_number'] ?? null,
            'operator_alias' => $payload['operator_alias'] ?? null,
            'origin_visual_key' => $payload['origin_visual_key'] ?? null,
            'created_at' => $createdAt,
        ];

        $this->pdo->beginTransaction();
        try {
            $this->insert($this->appointmentsTable, $appointmentData);
            $this->appendEvent($appointmentId, $payload, $createdAt);
            $this->pdo->commit(); // commit within the try scope after all inserts succeed
        } catch (PDOException $e) {
            $this->pdo->rollBack();
            throw $e;
        } catch (RuntimeException $e) {
            $this->pdo->rollBack();
            throw $e;
        }

        $this->bridgeClinicalEncounterIfCompleted($appointmentData);

        return [
            'appointment_id' => $appointmentId,
            'created_at' => $createdAt,
        ];
    }

    private function appendEvent(string $appointmentId, array $payload, string $createdAt): void
    {
        $eventData = [
            'event_id' => $this->generateId(),
            'appointment_id' => $appointmentId,
            'event_type' => 'appointment_created',
            'timestamp' => $createdAt,
            'actor_role' => $payload['created_by_role'],
            'actor_id' => $payload['created_by_id'],
            'channel_origin' => $payload['channel_origin'],
            'from_datetime' => $payload['start_at'],
            'to_datetime' => $payload['end_at'],
        ];
        $this->insert($this->eventsTable, $eventData);
    }

    public function appendWaitlistAssignmentEvent(string $appointmentId, array $payload, array $entry): string
    {
        $this->ensureEventsTable();
        $timestamp = (new DateTime('now', new DateTimeZone(self::TIMEZONE)))->format('Y-m-d H:i:s');
        $eventData = [
            'event_id' => $this->generateId(),
            'appointment_id' => $appointmentId,
            'event_type' => 'appointment_reassigned_from_waitlist',
            'timestamp' => $timestamp,
            'from_datetime' => $payload['start_at'] ?? null,
            'to_datetime' => $payload['end_at'] ?? null,
            'from_start_at' => $payload['start_at'] ?? null,
            'to_end_at' => $payload['end_at'] ?? null,
            'actor_role' => $payload['actor_role'] ?? null,
            'actor_id' => $payload['actor_id'] ?? null,
            'channel_origin' => $payload['channel_origin'] ?? null,
            'notes' => $this->buildWaitlistEventNotes($payload, $entry),
        ];
        $this->insert($this->eventsTable, $eventData);
        return $eventData['event_id'];
    }

    private function buildWaitlistEventNotes(array $payload, array $entry): string
    {
        $structured = [
            'source' => 'waitlist_assign',
            'waitlist_entry_id' => trim((string)($entry['id'] ?? '')) ?: null,
            'consultorio_id' => trim((string)($payload['consultorio_id'] ?? $entry['consultorio_id'] ?? '')) ?: null,
            'assigned_slot' => [
                'start_at' => trim((string)($payload['start_at'] ?? '')) ?: null,
                'end_at' => trim((string)($payload['end_at'] ?? '')) ?: null,
                'slot_minutes' => isset($payload['slot_minutes']) ? (int)$payload['slot_minutes'] : null,
            ],
            'actor_display_name' => trim((string)($payload['actor_display_name'] ?? '')) ?: null,
            'override' => !empty($payload['override']),
            'override_reason' => trim((string)($payload['override_reason'] ?? '')) ?: null,
            'linked_cancelled_appointment_id' => trim((string)($payload['linked_cancelled_appointment_id'] ?? '')) ?: null,
            'entry_notes' => trim((string)($entry['notes'] ?? '')) ?: null,
            'action' => trim((string)($payload['action'] ?? 'waitlist_assigned')) ?: 'waitlist_assigned',
            'entity_type' => trim((string)($payload['entity_type'] ?? 'waitlist_entry')) ?: 'waitlist_entry',
            'entity_id' => trim((string)($payload['entity_id'] ?? $entry['id'] ?? '')) ?: null,
            'occurred_at' => trim((string)($payload['occurred_at'] ?? '')) ?: null,
        ];

        $encoded = json_encode($structured, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($encoded !== false) {
            return $encoded;
        }

        $parts = ['source=waitlist_assign'];
        if (!empty($structured['waitlist_entry_id'])) {
            $parts[] = 'waitlist_entry_id:' . $structured['waitlist_entry_id'];
        }
        if (!empty($structured['consultorio_id'])) {
            $parts[] = 'consultorio_id:' . $structured['consultorio_id'];
        }
        if (!empty($structured['assigned_slot']['start_at']) || !empty($structured['assigned_slot']['end_at'])) {
            $parts[] = 'assigned_slot:' . ($structured['assigned_slot']['start_at'] ?? '') . '->' . ($structured['assigned_slot']['end_at'] ?? '');
        }
        if (!empty($structured['linked_cancelled_appointment_id'])) {
            $parts[] = 'linked_cancelled:' . $structured['linked_cancelled_appointment_id'];
        }
        return implode(' | ', $parts);
    }

    public function rescheduleAppointment(string $appointmentId, array $payload): array
    {
        $this->ensureAppointmentsTable();
        $this->ensureEventsTable();
        $pkColumn = $this->appointmentPk ?: 'appointment_id';
        $this->ensurePrimaryKeyColumn($pkColumn);

        $current = $this->fetchAppointment($appointmentId, $pkColumn);
        $notifyPatient = $this->resolveOptionalBoolean($payload['notify_patient'] ?? null) === true ? 1 : 0;
        $payload['notify_patient'] = $notifyPatient;

        $this->pdo->beginTransaction();
        try {
            $updatePayload = [
                'start_at' => $payload['to_start_at'],
                'end_at' => $payload['to_end_at'],
            ];
            $targetConsultorioId = trim((string)($payload['to_consultorio_id'] ?? ''));
            if ($targetConsultorioId !== '') {
                $updatePayload['consultorio_id'] = $targetConsultorioId;
            }
            $this->update($this->appointmentsTable, $pkColumn, $appointmentId, $updatePayload);
            $this->appendRescheduleEvent($appointmentId, $payload, $current);
            $this->pdo->commit();
        } catch (PDOException $e) {
            $this->pdo->rollBack();
            throw $e;
        } catch (RuntimeException $e) {
            $this->pdo->rollBack();
            throw $e;
        }

        return [
            'appointment_id' => $appointmentId,
            'from_start_at' => $current['start_at'] ?? null,
            'from_end_at' => $current['end_at'] ?? null,
            'from_consultorio_id' => $current['consultorio_id'] ?? null,
            'to_start_at' => $payload['to_start_at'],
            'to_end_at' => $payload['to_end_at'],
            'to_consultorio_id' => $payload['to_consultorio_id'] ?? ($current['consultorio_id'] ?? null),
            'motivo_code' => $payload['motivo_code'] ?? null,
            'motivo_text' => $payload['motivo_text'] ?? null,
            'notify_patient' => $notifyPatient,
            'contact_method' => $payload['contact_method'] ?? 'whatsapp',
        ];
    }

    private function appendRescheduleEvent(string $appointmentId, array $payload, array $current): void
    {
        $fromConsultorioId = isset($current['consultorio_id']) ? trim((string)$current['consultorio_id']) : '';
        $toConsultorioId = trim((string)($payload['to_consultorio_id'] ?? ''));
        if ($toConsultorioId === '') {
            $toConsultorioId = $fromConsultorioId;
        }

        $eventData = [
            'event_id' => $this->generateId(),
            'appointment_id' => $appointmentId,
            'event_type' => 'appointment_rescheduled',
            'timestamp' => (new DateTime('now', new DateTimeZone(self::TIMEZONE)))->format('Y-m-d H:i:s'),
            'from_datetime' => $current['start_at'] ?? null,
            'to_datetime' => $payload['to_end_at'],
            'from_start_at' => $current['start_at'] ?? null,
            'from_end_at' => $current['end_at'] ?? null,
            'to_start_at' => $payload['to_start_at'],
            'to_end_at' => $payload['to_end_at'],
            'motivo_code' => $payload['motivo_code'] ?? null,
            'motivo_text' => $payload['motivo_text'] ?? null,
            'notify_patient' => $payload['notify_patient'] ?? 0,
            'contact_method' => $payload['contact_method'] ?? 'whatsapp',
            'actor_role' => $payload['actor_role'] ?? $payload['created_by_role'] ?? null,
            'actor_id' => $payload['actor_id'] ?? $payload['created_by_id'] ?? null,
            'channel_origin' => $payload['channel_origin'] ?? null,
            // Persistimos metadatos de consultorio en `notes` para trazabilidad
            // sin depender de migraciones de columnas en agenda_appointment_events.
            'notes' => $this->buildRescheduleEventNotes($fromConsultorioId, $toConsultorioId),
        ];
        $this->insert($this->eventsTable, $eventData);
    }

    private function buildRescheduleEventNotes(string $fromConsultorioId, string $toConsultorioId): ?string
    {
        $payload = [
            'from_consultorio_id' => $fromConsultorioId !== '' ? $fromConsultorioId : null,
            'to_consultorio_id' => $toConsultorioId !== '' ? $toConsultorioId : null,
        ];

        $encoded = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($encoded === false) {
            return null;
        }

        return $encoded;
    }

    public function cancelAppointment(string $appointmentId, array $payload): array
    {
        $this->ensureAppointmentsTable();
        $this->ensureEventsTable();
        $pkColumn = $this->appointmentPk ?: 'appointment_id';
        $this->ensurePrimaryKeyColumn($pkColumn);

        $current = $this->fetchAppointment($appointmentId, $pkColumn);
        $columns = $this->getColumns($this->appointmentsTable);
        $statusExists = in_array('status', $columns, true);
        $cancelledAt = $current['cancelled_at'] ?? $current['canceled_at'] ?? null;
        if ($statusExists) {
            $statusValue = strtolower((string)($current['status'] ?? ''));
            if (in_array($statusValue, ['cancelled', 'canceled'], true)) {
                return [
                    'appointment_id' => $appointmentId,
                    'status' => $statusValue ?: 'canceled',
                    'start_at' => $current['start_at'] ?? null,
                    'end_at' => $current['end_at'] ?? null,
                    'motivo_code' => $payload['motivo_code'] ?? null,
                    'motivo_text' => $payload['motivo_text'] ?? null,
                    'notify_patient' => isset($payload['notify_patient']) ? (int)$payload['notify_patient'] : 0,
                    'contact_method' => $payload['contact_method'] ?? 'none',
                    'cancelled_at' => $cancelledAt,
                    'event_id' => null,
                    'events_appended' => 0,
                    'already_cancelled' => true,
                ];
            }
        }

        $cancelledAt = (new DateTime('now', new DateTimeZone(self::TIMEZONE)))->format('Y-m-d H:i:s');
        $lateCancelResult = ['event_appended' => 0, 'flag_appended' => 0];
        $manualCancelFlagResult = ['event_appended' => 0, 'flag_appended' => 0];
        $applyLateCancelFlag = $this->resolveOptionalBoolean($payload['apply_late_cancel_flag'] ?? null);
        $cancelFlagType = $this->normalizeCancelFlagType($payload['cancel_flag_type'] ?? null);

        $this->pdo->beginTransaction();
        try {
            $this->updateCancelFields($pkColumn, $appointmentId, 'canceled', $cancelledAt);
            $eventId = $this->appendCancelEvent($appointmentId, $payload, $current);
            $lateCancelResult = $this->appendLateCancelEventIfNeeded(
                $appointmentId,
                $payload,
                $current,
                $cancelledAt,
                $applyLateCancelFlag
            );
            $manualCancelFlagResult = $this->appendManualCancelFlagIfNeeded(
                $appointmentId,
                $payload,
                $current,
                $cancelFlagType
            );
            $this->pdo->commit();
        } catch (PDOException $e) {
            $this->pdo->rollBack();
            throw $e;
        } catch (RuntimeException $e) {
            $this->pdo->rollBack();
            throw $e;
        }

        return [
            'appointment_id' => $appointmentId,
            'status' => 'canceled',
            'start_at' => $current['start_at'] ?? null,
            'end_at' => $current['end_at'] ?? null,
            'motivo_code' => $payload['motivo_code'] ?? null,
            'motivo_text' => $payload['motivo_text'] ?? null,
            'notify_patient' => isset($payload['notify_patient']) ? (int)$payload['notify_patient'] : 0,
            'contact_method' => $payload['contact_method'] ?? 'none',
            'cancelled_at' => $cancelledAt,
            'event_id' => $eventId ?? null,
            'events_appended' => 1,
            'flags_appended' => (int)($lateCancelResult['flag_appended'] ?? 0) + (int)($manualCancelFlagResult['flag_appended'] ?? 0),
            'cancel_flag_type_applied' => $cancelFlagType,
        ];
    }

    private function updateCancelFields(string $pkColumn, string $appointmentId, string $status, string $cancelledAt): void
    {
        $columns = $this->getColumns($this->appointmentsTable);
        $data = [];
        if (in_array('status', $columns, true)) {
            $data['status'] = $status;
        }
        if (in_array('cancelled_at', $columns, true)) {
            $data['cancelled_at'] = $cancelledAt;
        } elseif (in_array('canceled_at', $columns, true)) {
            $data['canceled_at'] = $cancelledAt;
        }
        if (empty($data)) {
            return;
        }
        $this->update($this->appointmentsTable, $pkColumn, $appointmentId, $data);
    }

    private function updateStatusIfExists(string $pkColumn, string $appointmentId, string $status): void
    {
        $columns = $this->getColumns($this->appointmentsTable);
        if (!in_array('status', $columns, true)) {
            return;
        }
        $this->update($this->appointmentsTable, $pkColumn, $appointmentId, ['status' => $status]);
    }

    private function appendCancelEvent(string $appointmentId, array $payload, array $current): string
    {
               $eventId = $this->generateId();
        $eventData = [
            'event_id' => $eventId,
            'appointment_id' => $appointmentId,
            'event_type' => 'appointment_canceled',
            'timestamp' => (new DateTime('now', new DateTimeZone(self::TIMEZONE)))->format('Y-m-d H:i:s'),
            'from_datetime' => $current['start_at'] ?? null,
            'from_start_at' => $current['start_at'] ?? null,
            'from_end_at' => $current['end_at'] ?? null,
            'motivo_code' => $payload['motivo_code'] ?? null,
            'motivo_text' => $payload['motivo_text'] ?? null,
            'notify_patient' => isset($payload['notify_patient']) ? (int)$payload['notify_patient'] : 0,
            'contact_method' => $payload['contact_method'] ?? 'none',
            'actor_role' => $payload['actor_role'] ?? $payload['created_by_role'] ?? null,
            'actor_id' => $payload['actor_id'] ?? $payload['created_by_id'] ?? null,
            'channel_origin' => $payload['channel_origin'] ?? null,
        ];
        $this->insert($this->eventsTable, $eventData);
        return $eventId;
    }

    private function insert(string $table, array $data): void
    {
        $columns = $this->getColumns($table);
        $available = array_intersect_key($data, array_flip($columns));
        if (empty($available)) {
            throw new RuntimeException('no columns available for insert');
        }
        $placeholders = array_map(fn($col) => ':' . $col, array_keys($available));
        $sql = sprintf("INSERT INTO %s (%s) VALUES (%s)", $table, implode(',', array_keys($available)), implode(',', $placeholders));
        $stmt = $this->pdo->prepare($sql);
        foreach ($available as $column => $value) {
            $stmt->bindValue(':' . $column, $value);
        }
        $stmt->execute();
    }

    private function getColumns(string $table): array
    {
        if (!isset($this->columnsCache[$table])) {
            $stmt = $this->pdo->prepare('SELECT column_name FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = :table');
            $stmt->execute(['table' => $table]);
            $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
            $this->columnsCache[$table] = $columns;
        }
        return $this->columnsCache[$table];
    }

    private function ensureAppointmentsTable(): void
    {
        if (!$this->appointmentsTable) {
            throw new RuntimeException('appointments table not ready');
        }
        if (!$this->tableExists($this->appointmentsTable)) {
            throw new RuntimeException('appointments table not ready');
        }
    }

    private function ensureEventsTable(): void
    {
        if (!$this->eventsTable) {
            throw new RuntimeException('appointment events not ready');
        }
        if (!$this->tableExists($this->eventsTable)) {
            throw new RuntimeException('appointment events not ready');
        }
    }

    private function ensurePrimaryKeyColumn(string $pkColumn): void
    {
        $columns = $this->getColumns($this->appointmentsTable);
        if (!in_array($pkColumn, $columns, true)) {
            throw new RuntimeException('appointments table not ready');
        }
    }

    private function fetchAppointment(string $appointmentId, string $pkColumn): array
    {
        $sql = sprintf('SELECT * FROM %s WHERE %s = :id LIMIT 1', $this->appointmentsTable, $pkColumn);
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['id' => $appointmentId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            throw new RuntimeException('appointment not found');
        }
        return $row;
    }

    private function update(string $table, string $pkColumn, string $pkValue, array $data): void
    {
        $columns = $this->getColumns($table);
        $available = array_intersect_key($data, array_flip($columns));
        if (empty($available)) {
            throw new RuntimeException('database error');
        }
        $sets = [];
        foreach ($available as $column => $value) {
            $sets[] = sprintf('%s = :%s', $column, $column);
        }
        $sql = sprintf('UPDATE %s SET %s WHERE %s = :pk', $table, implode(',', $sets), $pkColumn);
        $stmt = $this->pdo->prepare($sql);
        foreach ($available as $column => $value) {
            $stmt->bindValue(':' . $column, $value);
        }
        $stmt->bindValue(':pk', $pkValue);
        $stmt->execute();
    }

    private function tableExists(string $table): bool
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = :table');
        $stmt->execute(['table' => $table]);
        return (int)$stmt->fetchColumn() > 0;
    }

    private function sanitizeIdentifier(?string $value): string
    {
        if (!$value) {
            return '';
        }
        return preg_replace('/[^a-zA-Z0-9_]/', '', $value) ?: '';
    }

    private function loadConfig(): array
    {
        $path = __DIR__ . '/../config/agenda.php';
        if (!is_file($path)) {
            return [];
        }
        $config = require $path;
        return is_array($config) ? $config : [];
    }

    private function generateId(): string
    {
        return bin2hex(random_bytes(12));
    }

    private function appendLateCancelEventIfNeeded(
        string $appointmentId,
        array $payload,
        array $current,
        string $cancelledAt,
        ?bool $applyLateCancelFlag
    ): array
    {
        $startAt = $current['start_at'] ?? null;
        if (!$startAt) {
            return ['event_appended' => 0, 'flag_appended' => 0];
        }
        $startDt = DateTime::createFromFormat('Y-m-d H:i:s', $startAt, new DateTimeZone(self::TIMEZONE));
        $cancelledDt = DateTime::createFromFormat('Y-m-d H:i:s', $cancelledAt, new DateTimeZone(self::TIMEZONE));
        if (!$startDt || !$cancelledDt) {
            return ['event_appended' => 0, 'flag_appended' => 0];
        }
        $diffMinutes = (int)(($startDt->getTimestamp() - $cancelledDt->getTimestamp()) / 60);
        if ($diffMinutes < 0 || $diffMinutes >= self::LATE_CANCEL_THRESHOLD_MINUTES) {
            return ['event_appended' => 0, 'flag_appended' => 0];
        }
        if ($applyLateCancelFlag !== true) {
            return ['event_appended' => 0, 'flag_appended' => 0];
        }
        if ($this->eventExists($appointmentId, 'appointment_late_cancel')) {
            return ['event_appended' => 0, 'flag_appended' => 0];
        }

        $eventData = [
            'event_id' => $this->generateId(),
            'appointment_id' => $appointmentId,
            'event_type' => 'appointment_late_cancel',
            'timestamp' => (new DateTime('now', new DateTimeZone(self::TIMEZONE)))->format('Y-m-d H:i:s'),
            'from_datetime' => $startAt,
            'from_start_at' => $startAt,
            'from_end_at' => $current['end_at'] ?? null,
            'motivo_code' => $payload['motivo_code'] ?? null,
            'motivo_text' => $payload['motivo_text'] ?? null,
            'notify_patient' => isset($payload['notify_patient']) ? (int)$payload['notify_patient'] : 0,
            'contact_method' => $payload['contact_method'] ?? 'whatsapp',
            'actor_role' => $payload['actor_role'] ?? $payload['created_by_role'] ?? null,
            'actor_id' => $payload['actor_id'] ?? $payload['created_by_id'] ?? null,
            'channel_origin' => $payload['channel_origin'] ?? null,
        ];
        $this->insert($this->eventsTable, $eventData);

        $patientId = $this->resolvePatientIdForFlag($current, $payload);
        $doctorId = $this->resolveDoctorIdForIncident($current, $payload);

        $flagAppended = $this->maybeAppendFlag(
            $patientId,
            'grey',
            'late_cancel',
            $appointmentId,
            $payload,
            'auto: late_cancel'
        );
        $this->maybeAppendIncident($patientId, $doctorId, $appointmentId, 'late_cancel', 'auto');

        return ['event_appended' => 1, 'flag_appended' => $flagAppended];
    }

    private function resolveOptionalBoolean($value): ?bool
    {
        if ($value === null) {
            return null;
        }
        if (in_array($value, [true, 1, '1', 'true'], true)) {
            return true;
        }
        if (in_array($value, [false, 0, '0', 'false'], true)) {
            return false;
        }
        return null;
    }
    private function normalizeCancelFlagType($value): string
    {
        $normalized = strtolower(trim((string)($value ?? '')));
        if ($normalized === '' || $normalized === 'none') {
            return 'none';
        }
        if (in_array($normalized, ['grey', 'gray', 'gris', 'lista_gris'], true)) {
            return 'grey';
        }
        if (in_array($normalized, ['black', 'negra', 'lista_negra'], true)) {
            return 'black';
        }
        return 'none';
    }
    private function appendManualCancelFlagIfNeeded(
        string $appointmentId,
        array $payload,
        array $current,
        string $cancelFlagType
    ): array
    {
        if (!in_array($cancelFlagType, ['grey', 'black'], true)) {
            return ['event_appended' => 0, 'flag_appended' => 0];
        }

        $reasonCode = $cancelFlagType === 'black' ? 'cancel_blacklist' : 'cancel_greylist';
        $eventType = $cancelFlagType === 'black' ? 'appointment_cancel_blacklist' : 'appointment_cancel_greylist';
        $flagType = $cancelFlagType === 'black' ? 'black' : 'grey';

        $eventData = [
            'event_id' => $this->generateId(),
            'appointment_id' => $appointmentId,
            'event_type' => $eventType,
            'timestamp' => (new DateTime('now', new DateTimeZone(self::TIMEZONE)))->format('Y-m-d H:i:s'),
            'from_datetime' => $current['start_at'] ?? null,
            'from_start_at' => $current['start_at'] ?? null,
            'from_end_at' => $current['end_at'] ?? null,
            'motivo_code' => $reasonCode,
            'motivo_text' => $payload['motivo_text'] ?? null,
            'notify_patient' => isset($payload['notify_patient']) ? (int)$payload['notify_patient'] : 0,
            'contact_method' => $payload['contact_method'] ?? 'none',
            'actor_role' => $payload['actor_role'] ?? $payload['created_by_role'] ?? null,
            'actor_id' => $payload['actor_id'] ?? $payload['created_by_id'] ?? null,
            'channel_origin' => $payload['channel_origin'] ?? null,
        ];
        $this->insert($this->eventsTable, $eventData);

        $patientId = $this->resolvePatientIdForFlag($current, $payload);
        $doctorId = $this->resolveDoctorIdForIncident($current, $payload);

        $flagAppended = $this->maybeAppendFlag(
            $patientId,
            $flagType,
            $reasonCode,
            $appointmentId,
            $payload,
            'manual: ' . $reasonCode
        );
        $this->maybeAppendIncident($patientId, $doctorId, $appointmentId, $reasonCode, 'manual');

        return ['event_appended' => 1, 'flag_appended' => $flagAppended];
    }

    private function bridgeClinicalEncounterIfCompleted(array $appointmentData): void
    {
        $bridge = $this->clinicalEncounterBridge;
        if (!$bridge || !$bridge->isEnabled()) {
            return;
        }

        try {
            $bridge->syncCompletedAppointment($appointmentData);
        } catch (Throwable $e) {
            error_log(sprintf(
                '[agenda-clinical-bridge] failed appointment_id=%s patient_id=%s error=%s',
                (string)($appointmentData['appointment_id'] ?? ''),
                (string)($appointmentData['patient_id'] ?? ''),
                trim((string)$e->getMessage())
            ));
        }
    }

    public function markNoShow(string $appointmentId, array $payload): array
    {
        $this->logNoShowBackendDebug('enter markNoShow', [
            'appointment_id' => $appointmentId,
        ]);
        $this->ensureAppointmentsTable();
        $this->ensureEventsTable();
        $pkColumn = $this->appointmentPk ?: 'appointment_id';
        $this->ensurePrimaryKeyColumn($pkColumn);

        $this->applyNoShowLockTimeoutGuard();
        $current = $this->fetchAppointment($appointmentId, $pkColumn);
        $this->logNoShowBackendDebug('appointment loaded', [
            'appointment_id' => $appointmentId,
            'doctor_id_effective' => (string)($current['doctor_id'] ?? ''),
            'patient_id' => (string)($current['patient_id'] ?? ''),
        ]);
        $observedAt = $payload['observed_at'] ?? (new DateTime('now', new DateTimeZone(self::TIMEZONE)))->format('Y-m-d H:i:s');
        $columns = $this->getColumns($this->appointmentsTable);
        $statusExists = in_array('status', $columns, true);
        $statusValue = strtolower((string)($current['status'] ?? ''));
        if (($statusExists && $statusValue === 'no_show') || $this->eventExists($appointmentId, 'appointment_no_show')) {
            $this->logNoShowBackendDebug('early already_no_show', [
                'appointment_id' => $appointmentId,
            ]);
            return [
                'appointment_id' => $appointmentId,
                'start_at' => $current['start_at'] ?? null,
                'end_at' => $current['end_at'] ?? null,
                'observed_at' => $observedAt,
                'motivo_code' => $payload['motivo_code'] ?? null,
                'motivo_text' => $payload['motivo_text'] ?? null,
                'notify_patient' => isset($payload['notify_patient']) ? (int)$payload['notify_patient'] : 0,
                'contact_method' => $payload['contact_method'] ?? 'whatsapp',
                'events_appended' => 0,
                'flags_appended' => 0,
                'already_no_show' => true,
            ];
        }

        $this->logNoShowBackendDebug('before begin transaction', [
            'appointment_id' => $appointmentId,
        ]);
        $this->pdo->beginTransaction();
        try {
            $this->logNoShowBackendDebug('before update appointment status', [
                'appointment_id' => $appointmentId,
            ]);
            $this->updateStatusIfExists($pkColumn, $appointmentId, 'no_show');
            $this->logNoShowBackendDebug('after update appointment status', [
                'appointment_id' => $appointmentId,
            ]);
            $this->logNoShowBackendDebug('before insert event', [
                'appointment_id' => $appointmentId,
            ]);
            $this->appendNoShowEvent($appointmentId, $payload, $current, $observedAt);
            $this->logNoShowBackendDebug('after insert event', [
                'appointment_id' => $appointmentId,
            ]);
            $patientId = $this->resolvePatientIdForFlag($current, $payload);
            $doctorId = $this->resolveDoctorIdForIncident($current, $payload);
            $this->logNoShowBackendDebug('resolved patient/doctor for flag+incident', [
                'appointment_id' => $appointmentId,
                'patient_id' => (string)$patientId,
                'doctor_id' => (string)$doctorId,
            ]);
            $this->logNoShowBackendDebug('before insert flag', [
                'appointment_id' => $appointmentId,
            ]);
            $flagAppended = $this->maybeAppendFlag(
                $patientId,
                'black',
                'no_show',
                $appointmentId,
                $payload,
                'auto: no_show'
            );
            $this->logNoShowBackendDebug('after insert flag', [
                'appointment_id' => $appointmentId,
                'flag_appended' => (int)$flagAppended,
            ]);
            $this->logNoShowBackendDebug('before insert incident', [
                'appointment_id' => $appointmentId,
            ]);
            $this->maybeAppendIncident($patientId, $doctorId, $appointmentId, 'no_show', 'manual');
            $this->logNoShowBackendDebug('after insert incident', [
                'appointment_id' => $appointmentId,
            ]);
            $this->logNoShowBackendDebug('before commit', [
                'appointment_id' => $appointmentId,
            ]);
            $this->pdo->commit();
            $this->logNoShowBackendDebug('after commit', [
                'appointment_id' => $appointmentId,
            ]);
        } catch (PDOException $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            $this->logNoShowBackendDebug('pdo exception in markNoShow', [
                'appointment_id' => $appointmentId,
                'message' => $e->getMessage(),
            ]);
            throw $e;
        } catch (RuntimeException $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            $this->logNoShowBackendDebug('runtime exception in markNoShow', [
                'appointment_id' => $appointmentId,
                'message' => $e->getMessage(),
            ]);
            throw $e;
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            $this->logNoShowBackendDebug('throwable in markNoShow', [
                'appointment_id' => $appointmentId,
                'message' => $e->getMessage(),
            ]);
            throw $e;
        }

        $this->logNoShowBackendDebug('before return markNoShow', [
            'appointment_id' => $appointmentId,
        ]);
        return [
            'appointment_id' => $appointmentId,
            'start_at' => $current['start_at'] ?? null,
            'end_at' => $current['end_at'] ?? null,
            'observed_at' => $observedAt,
            'motivo_code' => $payload['motivo_code'] ?? null,
            'motivo_text' => $payload['motivo_text'] ?? null,
            'notify_patient' => isset($payload['notify_patient']) ? (int)$payload['notify_patient'] : 0,
            'contact_method' => $payload['contact_method'] ?? 'whatsapp',
            'events_appended' => 1,
            'flags_appended' => $flagAppended,
            'already_no_show' => false,
        ];
    }

    private function appendNoShowEvent(string $appointmentId, array $payload, array $current, string $observedAt): void
    {
        $eventData = [
            'event_id' => $this->generateId(),
            'appointment_id' => $appointmentId,
            'event_type' => 'appointment_no_show',
            'timestamp' => (new DateTime('now', new DateTimeZone(self::TIMEZONE)))->format('Y-m-d H:i:s'),
            'observed_at' => $observedAt,
            'from_start_at' => $current['start_at'] ?? null,
            'from_end_at' => $current['end_at'] ?? null,
            'motivo_code' => $payload['motivo_code'] ?? null,
            'motivo_text' => $payload['motivo_text'] ?? null,
            'notify_patient' => isset($payload['notify_patient']) ? (int)$payload['notify_patient'] : 0,
            'contact_method' => $payload['contact_method'] ?? 'whatsapp',
            'actor_role' => $payload['actor_role'] ?? $payload['created_by_role'] ?? null,
            'actor_id' => $payload['actor_id'] ?? $payload['created_by_id'] ?? null,
            'channel_origin' => $payload['channel_origin'] ?? null,
        ];
        $this->insert($this->eventsTable, $eventData);
    }

    private function maybeAppendFlag(
        ?string $patientId,
        string $flagType,
        string $reasonCode,
        string $appointmentId,
        array $payload,
        string $notes
    ): int {
        if (!$this->patientFlagsRepository) {
            $this->logNoShowBackendDebug('flag skipped repository not available', [
                'appointment_id' => $appointmentId,
            ]);
            return 0;
        }
        if (!$patientId) {
            $this->logNoShowBackendDebug('flag skipped missing patient_id', [
                'appointment_id' => $appointmentId,
            ]);
            return 0;
        }
        try {
            if ($this->patientFlagsRepository->flagExists($patientId, $reasonCode)) {
                $this->logNoShowBackendDebug('flag skipped already exists', [
                    'appointment_id' => $appointmentId,
                    'patient_id' => $patientId,
                    'reason_code' => $reasonCode,
                ]);
                return 0;
            }
            $this->patientFlagsRepository->appendFlag([
                'patient_id' => $patientId,
                'flag_type' => $flagType,
                'reason_code' => $reasonCode,
                'source_appointment_id' => $appointmentId,
                'notes' => $notes,
                'actor_role' => $payload['actor_role'] ?? $payload['created_by_role'] ?? null,
                'actor_id' => $payload['actor_id'] ?? $payload['created_by_id'] ?? null,
                'channel_origin' => $payload['channel_origin'] ?? null,
            ]);
            return 1;
        } catch (Throwable $e) {
            $this->logNoShowBackendDebug('flag append failed (non-blocking)', [
                'appointment_id' => $appointmentId,
                'patient_id' => $patientId,
                'reason_code' => $reasonCode,
                'message' => $e->getMessage(),
            ]);
            return 0;
        }
    }

    private function resolvePatientIdForFlag(array $current, array $payload): ?string
    {
        if (!empty($current['patient_id'])) {
            return (string)$current['patient_id'];
        }
        if (!empty($payload['patient_id'])) {
            return (string)$payload['patient_id'];
        }
        return null;
    }

    private function resolveDoctorIdForIncident(array $current, array $payload): ?string
    {
        if (!empty($current['doctor_id'])) {
            return (string)$current['doctor_id'];
        }
        if (!empty($payload['doctor_id'])) {
            return (string)$payload['doctor_id'];
        }
        return null;
    }

    private function maybeAppendIncident(
        ?string $patientId,
        ?string $doctorId,
        string $appointmentId,
        string $incidentType,
        string $origin
    ): int {
        if (!$this->patientIncidentsRepository) {
            $this->logNoShowBackendDebug('incident skipped repository not available', [
                'appointment_id' => $appointmentId,
            ]);
            return 0;
        }
        if (!$patientId || !$doctorId) {
            $this->logNoShowBackendDebug('incident skipped missing patient/doctor scope', [
                'appointment_id' => $appointmentId,
                'patient_id' => (string)$patientId,
                'doctor_id' => (string)$doctorId,
            ]);
            return 0;
        }
        try {
            $this->patientIncidentsRepository->appendIncident([
                'patient_id' => $patientId,
                'doctor_id' => $doctorId,
                'appointment_id' => $appointmentId,
                'incident_type' => $incidentType,
                'origin' => $origin,
            ]);
            return 1;
        } catch (Throwable $e) {
            $this->logNoShowBackendDebug('incident append failed (non-blocking)', [
                'appointment_id' => $appointmentId,
                'patient_id' => $patientId,
                'doctor_id' => $doctorId,
                'incident_type' => $incidentType,
                'message' => $e->getMessage(),
            ]);
            return 0;
        }
    }

    private function applyNoShowLockTimeoutGuard(): void
    {
        try {
            $this->pdo->exec('SET SESSION innodb_lock_wait_timeout = 5');
        } catch (Throwable $e) {
            $this->logNoShowBackendDebug('failed to set innodb_lock_wait_timeout', [
                'message' => $e->getMessage(),
            ]);
        }
        try {
            $this->pdo->exec('SET SESSION lock_wait_timeout = 5');
        } catch (Throwable $e) {
            $this->logNoShowBackendDebug('failed to set lock_wait_timeout', [
                'message' => $e->getMessage(),
            ]);
        }
    }

    private function logNoShowBackendDebug(string $label, array $meta = []): void
    {
        $payload = [
            'label' => $label,
            'meta' => $meta,
        ];
        $encoded = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($encoded === false) {
            $encoded = $label;
        }
        error_log('AGENDA NO_SHOW BACKEND DEBUG: ' . $encoded);
    }

    private function eventExists(string $appointmentId, string $eventType): bool
    {
        $columns = $this->getColumns($this->eventsTable);
        if (!in_array('appointment_id', $columns, true) || !in_array('event_type', $columns, true)) {
            return false;
        }
        $sql = sprintf('SELECT COUNT(*) FROM %s WHERE appointment_id = :appointment_id AND event_type = :event_type', $this->eventsTable);
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['appointment_id' => $appointmentId, 'event_type' => $eventType]);
        return (int)$stmt->fetchColumn() > 0;
    }
}
