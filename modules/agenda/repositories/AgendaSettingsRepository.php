<?php
namespace Agenda\Repositories;

use PDO;
use RuntimeException;

class AgendaSettingsRepository
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    private function ensureTable(): void
    {
        if (!$this->tableExists('agenda_settings')) {
            throw new RuntimeException('schema_not_ready');
        }
    }

    private function tableExists(string $name): bool
    {
        $stmt = $this->pdo->prepare(
            'SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = :table'
        );
        $stmt->execute(['table' => $name]);
        return (int)$stmt->fetchColumn() > 0;
    }

    public function getByDoctorConsultorio(string $doctorId, string $consultorioId): ?array
    {
        $this->ensureTable();
        $stmt = $this->pdo->prepare(
            'SELECT * FROM agenda_settings WHERE doctor_id = :doctor_id AND consultorio_id = :consultorio_id LIMIT 1'
        );
        $stmt->execute([
            'doctor_id' => $doctorId,
            'consultorio_id' => $consultorioId,
        ]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $row : null;
    }

    public function upsert(array $payload): void
    {
        $this->ensureTable();
        $stmt = $this->pdo->prepare(
            'INSERT INTO agenda_settings (
                doctor_id, consultorio_id, appointment_duration_min, gap_between_appointments_min,
                channels_json, cancellation_policy_hours, reminder_template, updated_at
            ) VALUES (
                :doctor_id, :consultorio_id, :appointment_duration_min, :gap_between_appointments_min,
                :channels_json, :cancellation_policy_hours, :reminder_template, NOW()
            )
            ON DUPLICATE KEY UPDATE
                appointment_duration_min = VALUES(appointment_duration_min),
                gap_between_appointments_min = VALUES(gap_between_appointments_min),
                channels_json = VALUES(channels_json),
                cancellation_policy_hours = VALUES(cancellation_policy_hours),
                reminder_template = VALUES(reminder_template),
                updated_at = NOW()'
        );
        $stmt->execute([
            'doctor_id' => (string)$payload['doctor_id'],
            'consultorio_id' => (string)$payload['consultorio_id'],
            'appointment_duration_min' => (int)$payload['appointment_duration_min'],
            'gap_between_appointments_min' => (int)$payload['gap_between_appointments_min'],
            'channels_json' => $payload['channels_json'],
            'cancellation_policy_hours' => $payload['cancellation_policy_hours'],
            'reminder_template' => $payload['reminder_template'],
        ]);
    }
}
