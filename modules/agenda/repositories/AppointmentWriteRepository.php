<?php
namespace Agenda\Repositories;

use DateTime;
use DateTimeZone;
use PDO;
use PDOException;
use RuntimeException;

class AppointmentWriteRepository
{
    private PDO $pdo;
    private ?string $appointmentsTable = null;
    private ?string $eventsTable = null;
    private ?string $appointmentPk = null;
    private array $columnsCache = [];
    private const TIMEZONE = 'America/Mexico_City';

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $config = $this->loadConfig();
        $this->appointmentsTable = $this->sanitizeIdentifier($config['appointments_table'] ?? '');
        $this->eventsTable = $this->sanitizeIdentifier($config['appointment_events_table'] ?? '');
        $this->appointmentPk = $this->sanitizeIdentifier($config['appointment_pk'] ?? 'appointment_id');
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

        $this->pdo->beginTransaction();
        try {
            $this->updateStatusIfExists($pkColumn, $appointmentId, 'canceled');
            $this->appendCancelEvent($appointmentId, $payload, $current);
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
            'contact_method' => $payload['contact_method'] ?? 'whatsapp',
        ];
    }

    private function updateStatusIfExists(string $pkColumn, string $appointmentId, string $status): void
    {
        $columns = $this->getColumns($this->appointmentsTable);
        if (!in_array('status', $columns, true)) {
            return;
        }
        $this->update($this->appointmentsTable, $pkColumn, $appointmentId, ['status' => $status]);
    }

    private function appendCancelEvent(string $appointmentId, array $payload, array $current): void
    {
        $eventData = [
            'event_id' => $this->generateId(),
            'appointment_id' => $appointmentId,
            'event_type' => 'appointment_canceled',
            'timestamp' => (new DateTime('now', new DateTimeZone(self::TIMEZONE)))->format('Y-m-d H:i:s'),
            'from_datetime' => $current['start_at'] ?? null,
            'from_start_at' => $current['start_at'] ?? null,
            'from_end_at' => $current['end_at'] ?? null,
            'motivo_code' => $payload['motivo_code'] ?? null,
            'motivo_text' => $payload['motivo_text'] ?? null,
            'notify_patient' => $payload['notify_patient'] ?? 0,
            'contact_method' => $payload['contact_method'] ?? 'whatsapp',
            'actor_role' => $payload['actor_role'] ?? $payload['created_by_role'] ?? null,
            'actor_id' => $payload['actor_id'] ?? $payload['created_by_id'] ?? null,
            'channel_origin' => $payload['channel_origin'] ?? null,
        ];
        $this->insert($this->eventsTable, $eventData);
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
}
