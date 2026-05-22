<?php
namespace Agenda\Repositories;

use PDO;
use RuntimeException;

class AppointmentEventsRepository
{
    private PDO $pdo;
    private ?string $table = null;
    private bool $enabled = false;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $config = $this->loadConfig();
        $table = trim((string)($config['appointment_events_table'] ?? ''));
        $this->enabled = $table !== '';
        if ($this->enabled) {
            $this->table = $table;
        }
    }

    public function listByAppointmentId(string $appointmentId, int $limit): array
    {
        $this->ensureTable();

        $sql = sprintf('SELECT * FROM %s WHERE appointment_id = :appointment_id ORDER BY timestamp ASC LIMIT :limit', $this->table);
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':appointment_id', $appointmentId);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return array_map([$this, 'enrichEventDto'], $rows);
    }

    private function enrichEventDto(array $row): array
    {
        $eventType = $this->safeString($row['event_type'] ?? null);
        $appointmentId = $this->safeString($row['appointment_id'] ?? null);

        $metadata = $this->buildMetadataFromNotes($row['notes'] ?? null);

        if ($eventType === 'appointment_rescheduled') {
            $trace = $this->extractConsultorioTraceFromMetadata($metadata);
            $fromConsultorio = $this->safeString($row['from_consultorio_id'] ?? null);
            $toConsultorio = $this->safeString($row['to_consultorio_id'] ?? null);

            if ($fromConsultorio === '' && $trace['from_consultorio_id'] !== null) {
                $fromConsultorio = $trace['from_consultorio_id'];
            }
            if ($toConsultorio === '' && $trace['to_consultorio_id'] !== null) {
                $toConsultorio = $trace['to_consultorio_id'];
            }

            $row['from_consultorio_id'] = $fromConsultorio !== '' ? $fromConsultorio : null;
            $row['to_consultorio_id'] = $toConsultorio !== '' ? $toConsultorio : null;

            if ($row['from_consultorio_id'] !== null) {
                $metadata['from_consultorio_id'] = $row['from_consultorio_id'];
            }
            if ($row['to_consultorio_id'] !== null) {
                $metadata['to_consultorio_id'] = $row['to_consultorio_id'];
            }
        }

        $metadataActorRole = $this->safeString($metadata['actor_role'] ?? null);
        $metadataCreatedByRole = $this->safeString($metadata['created_by_role'] ?? null);
        $metadataActorId = $this->safeString($metadata['actor_id'] ?? null);
        $metadataCreatedById = $this->safeString($metadata['created_by_id'] ?? null);

        $actorRole = $this->safeString($row['actor_role'] ?? null);
        if ($actorRole === '') {
            $actorRole = $metadataActorRole !== '' ? $metadataActorRole : $metadataCreatedByRole;
        }

        $actorId = $this->safeString($row['actor_id'] ?? null);
        if ($actorId === '') {
            $actorId = $metadataActorId !== '' ? $metadataActorId : $metadataCreatedById;
        }

        $createdByRole = $metadataCreatedByRole !== '' ? $metadataCreatedByRole : $actorRole;
        $createdById = $metadataCreatedById !== '' ? $metadataCreatedById : $actorId;

        $occurredAt = $this->safeString($row['timestamp'] ?? null);
        if ($occurredAt === '') {
            $occurredAt = $this->safeString($row['created_at'] ?? null);
        }

        $row['action'] = $eventType !== '' ? $eventType : null;
        $row['entity_type'] = 'appointment';
        $row['entity_id'] = $appointmentId !== '' ? $appointmentId : null;
        $row['occurred_at'] = $occurredAt !== '' ? $occurredAt : null;

        // Preservamos campos legacy y completamos attribution de forma aditiva.
        $row['actor_role'] = $actorRole !== '' ? $actorRole : null;
        $row['actor_id'] = $actorId !== '' ? $actorId : null;

        $actorDisplayName = $this->safeString($metadata['actor_display_name'] ?? null);
        $row['actor_display_name'] = $actorDisplayName !== '' ? $actorDisplayName : null;

        $row['created_by_role'] = $createdByRole !== '' ? $createdByRole : null;
        $row['created_by_id'] = $createdById !== '' ? $createdById : null;

        $row['metadata'] = !empty($metadata) ? $metadata : (object)[];

        return $row;
    }

    private function buildMetadataFromNotes($notes): array
    {
        if (is_string($notes)) {
            $trimmed = trim($notes);
            if ($trimmed !== '') {
                $decoded = json_decode($trimmed, true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                    return $decoded;
                }
                return ['notes_text' => $notes];
            }
        }
        return [];
    }

    private function extractConsultorioTraceFromMetadata(array $metadata): array
    {
        $from = $this->safeString($metadata['from_consultorio_id'] ?? null);
        $to = $this->safeString($metadata['to_consultorio_id'] ?? null);

        return [
            'from_consultorio_id' => $from !== '' ? $from : null,
            'to_consultorio_id' => $to !== '' ? $to : null,
        ];
    }

    private function safeString($value): string
    {
        if ($value === null) {
            return '';
        }
        return trim((string)$value);
    }

    private function ensureTable(): void
    {
        if (!$this->enabled || !$this->table || !$this->tableExists($this->table)) {
            throw new RuntimeException('appointment events not ready');
        }
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

    private function tableExists(string $name): bool
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = :table');
        $stmt->execute(['table' => $name]);
        return (int)$stmt->fetchColumn() > 0;
    }
}
