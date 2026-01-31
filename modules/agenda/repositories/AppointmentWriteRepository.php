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
    private array $columnsCache = [];

    private const TIMEZONE = 'America/Mexico_City';

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $config = $this->loadConfig();
        $this->appointmentsTable = $this->sanitizeIdentifier($config['appointments_table'] ?? '');
        $this->eventsTable = $this->sanitizeIdentifier($config['appointment_events_table'] ?? '');
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
