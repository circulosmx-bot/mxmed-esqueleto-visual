<?php
namespace Agenda\Repositories;

use Agenda\Repositories\PatientFlagsWriteRepository;
use DateTime;
use DateTimeZone;
use PDO;
use PDOException;
use RuntimeException;
use Throwable;

require_once __DIR__ . '/../repositories/PatientFlagsWriteRepository.php';

class AppointmentWriteRepository
{
    private PDO $pdo;
    private ?string $appointmentsTable = null;
    private ?string $eventsTable = null;
    private ?string $appointmentPk = null;
    private array $columnsCache = [];
    private ?PatientFlagsWriteRepository $patientFlagsRepository = null;
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

    public function rescheduleAppointment(string $appointmentId, array $payload): array
    {
        $this->ensureAppointmentsTable();
        $this->ensureEventsTable();
        $pkColumn = $this->appointmentPk ?: 'appointment_id';
        $this->ensurePrimaryKeyColumn($pkColumn);

        $current = $this->fetchAppointment($appointmentId, $pkColumn);

        $this->pdo->beginTransaction();
        try {
            $this->update($this->appointmentsTable, $pkColumn, $appointmentId, [
                'start_at' => $payload['to_start_at'],
                'end_at' => $payload['to_end_at'],
            ]);
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
            'to_start_at' => $payload['to_start_at'],
            'to_end_at' => $payload['to_end_at'],
            'motivo_code' => $payload['motivo_code'] ?? null,
            'motivo_text' => $payload['motivo_text'] ?? null,
            'notify_patient' => isset($payload['notify_patient']) ? (int)$payload['notify_patient'] : 0,
            'contact_method' => $payload['contact_method'] ?? 'whatsapp',
        ];
    }

    private function appendRescheduleEvent(string $appointmentId, array $payload, array $current): void
    {
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
        ];
        $this->insert($this->eventsTable, $eventData);
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

        $this->pdo->beginTransaction();
        try {
            $this->updateCancelFields($pkColumn, $appointmentId, 'canceled', $cancelledAt);
            $eventId = $this->appendCancelEvent($appointmentId, $payload, $current);
            $lateCancelResult = $this->appendLateCancelEventIfNeeded($appointmentId, $payload, $current, $cancelledAt);
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
            'flags_appended' => $lateCancelResult['flag_appended'] ?? 0,
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

    private function appendLateCancelEventIfNeeded(string $appointmentId, array $payload, array $current, string $cancelledAt): array
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

        $flagAppended = $this->maybeAppendFlag(
            $this->resolvePatientIdForFlag($current, $payload),
            'grey',
            'late_cancel',
            $appointmentId,
            $payload,
            'auto: late_cancel'
        );

        return ['event_appended' => 1, 'flag_appended' => $flagAppended];
    }

    public function markNoShow(string $appointmentId, array $payload): array
    {
        $this->ensureAppointmentsTable();
        $this->ensureEventsTable();
        $pkColumn = $this->appointmentPk ?: 'appointment_id';
        $this->ensurePrimaryKeyColumn($pkColumn);

        $current = $this->fetchAppointment($appointmentId, $pkColumn);
        $observedAt = $payload['observed_at'] ?? (new DateTime('now', new DateTimeZone(self::TIMEZONE)))->format('Y-m-d H:i:s');
        $columns = $this->getColumns($this->appointmentsTable);
        $statusExists = in_array('status', $columns, true);
        $statusValue = strtolower((string)($current['status'] ?? ''));
        if (($statusExists && $statusValue === 'no_show') || $this->eventExists($appointmentId, 'appointment_no_show')) {
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

        $this->pdo->beginTransaction();
        try {
            $this->updateStatusIfExists($pkColumn, $appointmentId, 'no_show');
            $this->appendNoShowEvent($appointmentId, $payload, $current, $observedAt);
            $flagAppended = $this->maybeAppendFlag(
                $this->resolvePatientIdForFlag($current, $payload),
                'black',
                'no_show',
                $appointmentId,
                $payload,
                'auto: no_show'
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
            return 0;
        }
        if (!$patientId) {
            return 0;
        }
        try {
            if ($this->patientFlagsRepository->flagExists($patientId, $reasonCode)) {
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
